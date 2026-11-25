<?php

namespace App\Services;

use App\Models\Generation;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;
use UnexpectedValueException;
use RuntimeException;

/**
 * Manages the complete image generation lifecycle.
 *
 * Responsibilities:
 * - Calculate estimated customer pricing.
 * - Create local generation records.
 * - Submit Pixelforge generation requests with idempotency protection.
 * - Synchronize provider information with local records.
 */
class GenerationService
{
    public function __construct(
        private PixelforgeService $pixelforge,
        private PixelforgePricingService $pricingService,
        private CreditService $creditService
    ) {}

    /**
     * Create a local generation and submit it to Pixelforge.
     *
     * A local record is created before contacting Pixelforge
     * so that every submission has a persistent generation ID.
     *
     * @param Authenticatable $user Authenticated user.
     * @param array $validated Validated generation request data.
     * @return array Submission result and response message.
     */
    public function create(
        Authenticatable $user,
        array $validated
    ): array {
        // Calculate the maximum estimated customer cost before submission.
        try {
            $estimatedCost = $this->calculatePrice($validated);
            
        } catch (Throwable $exception) {
            report($exception);

            return $this->failure(
                'pricing',
                'Unable to verify pricing. Please try again.'
            );
        }

        $referenceImages = $validated['reference_images'] ?? [];

        // Create the local generation before contacting the provider.
        try {
            $generation = DB::transaction(function () use (
                $user,
                $validated,
                $referenceImages,
                $estimatedCost
            ) {
                $generation = Generation::create([
                    'user_id' => $user->id,
                    'prompt' => $validated['prompt'],
                    'size' => $validated['size'],
                    'quality' => $validated['quality'],
                    'reference_urls' => $referenceImages,
                    'status' => 'queued',
                    'estimated_cost' => $estimatedCost,
                ]);

                $this->creditService->reserve(
                    $user,
                    $generation,
                    $estimatedCost
                );

                return $generation;
            });

        } catch (\RuntimeException $exception) {
            if (str_contains($exception->getMessage(), 'Insufficient credits')) {
                return $this->failure(
                    'credits',
                    'Insufficient credits. Please buy more credits.'
                );
            }

            report($exception);

            return $this->failure(
                'generation',
                'Unable to reserve credits. Please try again.'
            );

        } catch (\Throwable $exception) {

            report($exception);

            return $this->failure(
                'generation',
                'Something went wrong. Please try again.'
            );
        }

        /*
         * Generate an idempotency key for this submission.
         *
         * The same key must be reused when retrying an uncertain
         * provider request to avoid creating duplicate generations.
         */
        $idempotencyKey = 'generation-' . $generation->id . '-' . Str::uuid();

        // Prepare the request payload expected by Pixelforge.
        $payload = [
            'prompt' => $validated['prompt'],
            'size' => $validated['size'],
            'quality' => $validated['quality'],
            'reference_images' => $referenceImages,
            'metadata' => [
                'generation_id' => (string) $generation->id,
            ],
        ];

        try {
            // Submit generation request to Pixelforge with automatic retries for overloaded responses
            $result = $this->createGenerationWithRetries(
                $payload,
                $idempotencyKey
            );

            $providerData = $result['data'];

            // Ensure the provider returned a valid generation response.
            $this->validateProviderResponse($result, $providerData);

            /*
             * Save the provider generation ID and pricing information.
             *
             * The database transaction locks the local record to prevent
             * webhook updates and API response updates from conflicting.
             */
            $this->saveProviderResponse($generation, $providerData);

            return [
                'success' => true,
                'message' => 'Your image generation request has been submitted successfully.',
            ];

        } catch (RequestException $exception) {
            // Pixelforge explicitly rejected the request with an HTTP error.
            return $this->handleProviderError(
                $generation,
                $exception
            );

        } catch (ConnectionException $exception) {
            /*
             * A connection failure has an uncertain outcome.
             *
             * Pixelforge might have received and accepted the request
             * even though our application did not receive its response.
             *
             * Keep the local generation queued for reconciliation.
             * Do not mark it failed or create another generation.
             */
            Log::warning('Pixelforge connection outcome uncertain', [
                'generation_id' => $generation->id,
            ]);

            return [
                'success' => true,
                'flash_type' => 'warning',
                'message' => 'Your request is being checked. Please refresh your generation history shortly.',
            ];

        } catch (Throwable $exception) {
            // Log unexpected failures for investigation.
            report($exception);

            Log::error('Pixelforge generation submission failed', [
                'generation_id' => $generation->id,
            ]);

            return $this->failure(
                'generation',
                'Unable to submit your generation request. Please contact support if the request remains pending.'
            );
        }
    }

    
    /**
     * Submit generation with automatic retries for overloaded responses.
     *
     * Maximum 3 attempts (1 initial + 2 retries).
     * Reuses the same idempotency key for every attempt.
     */
    private function createGenerationWithRetries(
        array $payload,
        string $idempotencyKey
    ): array {
        $maxAttempts = 3;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                return $this->pixelforge->createGeneration(
                    $payload,
                    $idempotencyKey
                );

            } catch (RequestException $exception) {
                $response = $exception->response;

                $httpStatus = $response?->status();
                $errorCode = $response?->json('error.code');

                // Identify documented retryable errors.
                $retryable =
                    (
                        $httpStatus === 503 &&
                        $errorCode === 'overloaded'
                    ) ||
                    (
                        $httpStatus === 429 &&
                        in_array($errorCode, [
                            'rate_limit_exceeded',
                            'concurrency_limit_exceeded',
                        ], true)
                    );

                // Do not retry unknown errors or after final attempt.
                if (!$retryable || $attempt === $maxAttempts) {
                    throw $exception;
                }

                // Pixelforge provides Retry-After in seconds.
                $retryAfter = $response->header('Retry-After');

                // Increasing fallback backoff: 2 seconds, then 4 seconds.
                $backoff = 2 ** $attempt;

                // Respect Retry-After; never retry earlier than requested.
                $delay = is_numeric($retryAfter)
                    ? max((int) $retryAfter, $backoff)
                    : $backoff;

                Log::warning('Pixelforge request rate limited or overloaded. Retrying.', [
                    'generation_id' => $payload['metadata']['generation_id'],
                    'http_status' => $httpStatus,
                    'error_code' => $errorCode,
                    'attempt' => $attempt,
                    'next_attempt' => $attempt + 1,
                    'delay_seconds' => $delay,
                ]);

                sleep($delay);
            }
        }

        throw new RuntimeException(
            'Pixelforge retry attempts exhausted.'
        );
    }


    /**
     * Calculate the estimated customer price.
     */
    private function calculatePrice(array $validated): string
    {
        $estimate = $this->pricingService->calculate(
            $validated['prompt'],
            $validated['size'],
            $validated['quality'],
            $validated['reference_images'] ?? []
        );

        $providerEstimate = $estimate['cost']['maximum'];  // Pixelforge estimated cost
        
        $providerNano = toNanoDollars($providerEstimate);
        $estimatedNano = intdiv($providerNano * 125 + 99, 100);    // Apply 25% markup
        $estimatedCost = formatNanoDollars($estimatedNano);
        
        return $estimatedCost;
    }

    /**
     * Validate the Pixelforge generation response.
     *
     * Only accept HTTP 202 responses containing a valid generation ID,
     * expected object type, and recognized generation status.
     *
     */
    private function validateProviderResponse(
        array $result,
        array $providerData
    ): void {
        if (
            $result['http_status'] !== 202 ||
            empty($providerData['id']) ||
            ($providerData['object'] ?? null) !== 'generation' ||
            !in_array(
                $providerData['status'] ?? null,
                ['queued', 'processing', 'succeeded', 'failed'],
                true
            )
        ) {
            throw new UnexpectedValueException(
                'Invalid response received from Pixelforge.'
            );
        }
    }

    /**
     * Persist Pixelforge generation information.
     *
     * Uses a database transaction and row lock to prevent race conditions with incoming webhooks.
     *
     * Never overwrite an already completed local status.
     */
    private function saveProviderResponse(
        Generation $generation,
        array $providerData
    ): void {
        DB::transaction(function () use ($generation, $providerData) {
            $localGeneration = Generation::query()
                ->lockForUpdate()
                ->findOrFail($generation->id);

            // Store the provider's generation ID and pricing rates.
            $localGeneration->provider_id = $providerData['id'];
            $localGeneration->provider_rates = $providerData['rates'] ?? null;

            // Only advance non-terminal generations to processing.
            if (
                !in_array(
                    $localGeneration->status,
                    ['succeeded', 'failed', 'rejected'],
                    true
                ) &&
                ($providerData['status'] ?? null) === 'processing'
            ) {
                $localGeneration->status = 'processing';
            }

            $localGeneration->save();
        });
    }

    /**
     * Handle explicit HTTP errors returned by Pixelforge.
     *
     */
    private function handleProviderError(
        Generation $generation,
        RequestException $exception
    ): array {
        $response = $exception->response;

        $httpStatus = $response?->status();
        $providerError = $response?->json('error');
        $errorCode = $providerError['code'] ?? null;

        Log::warning('Pixelforge generation submission rejected', [
            'generation_id' => $generation->id,
            'http_status' => $httpStatus,
            'error_code' => $errorCode,
        ]);

        // Only classify documented submission rejections here.
        $rejections = [
            400 => ['content_policy_violation'],
            429 => [
                'rate_limit_exceeded',
                'concurrency_limit_exceeded',
                'daily_limit_exceeded',
            ],
            503 => ['overloaded'],
        ];

        if (in_array($errorCode, $rejections[$httpStatus] ?? [], true)) {
            $messages = [
                'content_policy_violation' =>
                    'Your prompt was rejected by the content policy. Please modify your prompt and try again.',

                'overloaded' =>
                    'Pixelforge is temporarily overloaded. Please try again shortly.',

                'rate_limit_exceeded' =>
                    'Too many API requests. Please wait a moment and try again.',

                'concurrency_limit_exceeded' =>
                    'Pixelforge is currently processing its maximum number of generations. Please try again shortly.',

                'daily_limit_exceeded' =>
                    'Pixelforge daily 500 generations quota has been reached. Please try again after the UTC daily quota resets.',
            ];

            $message = $messages[$errorCode];

            DB::transaction(function () use ($generation) {
                $local = Generation::query()
                    ->lockForUpdate()
                    ->findOrFail($generation->id);

                // Do not overwrite an already finalized generation.
                if (in_array($local->status, [
                    'succeeded',
                    'failed',
                    'rejected',
                ], true)) {
                    return;
                }

                $local->update([
                    'status' => 'rejected',
                    'provider_cost' => '0.000000000',
                    'actual_cost' => '0.000000000',
                    'provider_usage' => null,
                    'image_url' => null,
                    'completed_at' => null,
                ]);

                // Release the reserved credits.
                $this->creditService->refundGeneration($local);
            });

            return $this->failure('generation', $message);
        }

        // Other HTTP errors should not automatically be classified
        // as either a processing failure or a documented rejection.
        // Team need to be manual review.
        Log::error('Unexpected Pixelforge submission error', [
            'generation_id' => $generation->id,
            'http_status' => $httpStatus,
            'error_code' => $errorCode,
        ]);

        return $this->failure(
            'generation',
            $providerError['message']
                ?? 'Unable to submit your generation request. Please contact support if the request remains pending.'
        );
    }

    /**
     * Build a consistent failure response for the controller.
     */
    private function failure(string $field, string $message): array
    {
        return [
            'success' => false,
            'field' => $field,
            'message' => $message,
        ];
    }
}
