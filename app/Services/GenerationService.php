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
        private PixelforgePricingService $pricingService
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
        $generation = Generation::create([
            'user_id' => $user->id,
            'prompt' => $validated['prompt'],
            'size' => $validated['size'],
            'quality' => $validated['quality'],
            'reference_urls' => $referenceImages,
            'status' => 'queued',
            'estimated_cost' => $estimatedCost,
        ]);

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
            // Submit the generation request to Pixelforge.
            $result = $this->pixelforge->createGeneration(
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
                    ['succeeded', 'failed'],
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
        $providerError = $response?->json('error');

        Log::error('Pixelforge generation request rejected', [
            'generation_id' => $generation->id,
            'http_status' => $response?->status(),
            'provider_error' => $providerError,
        ]);

        $generation->update([
            'status' => 'failed',
        ]);

        return $this->failure(
            'generation',
            $providerError['message']
                ?? 'Pixelforge rejected your generation request. Please try again.'
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
