<?php

namespace App\Http\Controllers;

use App\Models\Generation;
use App\Models\WebhookEvent;
use App\Services\GenerationStatusService;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class PixelforgeWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        GenerationStatusService $statusService
    ): Response {
        // 1. Get raw request body.
        $rawBody = $request->getContent();

        // 2. Verify signature before decoding JSON.
        $this->verifySignature($request, $rawBody);

        // 3. Decode event.
        $event = json_decode($rawBody, true);

        if (
            !is_array($event) ||
            empty($event['id']) ||
            empty($event['type'])
        ) {
            return response('Invalid event payload.', 400);
        }

        $eventId = $event['id'];
        $eventType = $event['type'];

        // Ensure headers and payload agree.
        if (
            $request->header('Pixelforge-Event-Id') !== $eventId ||
            $request->header('Pixelforge-Event-Type') !== $eventType
        ) {
            return response('Event header mismatch.', 400);
        }

        $allowedEvents = [
            'ping',
            'generation.processing',
            'generation.succeeded',
            'generation.failed',
        ];

        if (!in_array($eventType, $allowedEvents, true)) {
            return response('Unsupported event type.', 400);
        }

        try {
            DB::transaction(function () use (
                $event,
                $eventId,
                $eventType,
                $statusService
            ) {
                // 4. Find local generation.
                $generation = null;
                $generationData = $event['data']['object'] ?? [];

                if (str_starts_with($eventType, 'generation.')) {
                    if (
                        ($generationData['object'] ?? null) !== 'generation' ||
                        empty($generationData['id']) ||
                        empty($generationData['metadata']['generation_id'])
                    ) {
                        throw new \UnexpectedValueException('Invalid generation webhook data.');
                    }

                    $localId = $generationData['metadata']['generation_id'];

                    if (!ctype_digit((string) $localId)) {
                        throw new \UnexpectedValueException('Invalid local generation ID.');
                    }

                    $generation = Generation::query()
                        ->lockForUpdate()
                        ->find($localId);

                    if (!$generation) {
                        throw new \RuntimeException('Local generation not found.');
                    }

                    // Ensure this event belongs to this provider ID.
                    if (
                        $generation->provider_id !== null &&
                        $generation->provider_id !== $generationData['id']
                    ) {
                        throw new \UnexpectedValueException('Provider generation ID mismatch.');
                    }
                }

                // 5. Insert event.
                // Database unique constraint prevents duplicates.
                $webhook = WebhookEvent::create([
                    'event_id' => $eventId,
                    'generation_id' => $generation?->id,
                    'event_type' => $eventType,
                    'payload' => $event,
                ]);

                // 6. Apply generation update.
                if ($generation) {
                    $statusService->apply($generation, $generationData);
                }

                // 7. Mark event processed.
                $webhook->update([
                    'processed_at' => now(),
                ]);
            });

        } catch (QueryException $exception) {
            // Duplicate event ID: acknowledge successfully.
            if ($this->isUniqueViolation($exception)) {
                return response()->noContent();
            }

            Log::error('Pixelforge webhook database error', [
                'event_id' => $eventId,
            ]);

            return response('Webhook processing failed.', 500);

        } catch (Throwable $exception) {
            Log::error('Pixelforge webhook processing error', [
                'event_id' => $eventId,
                'message' => $exception->getMessage(),
            ]);

            // Non-2xx makes Pixelforge retry.
            return response('Webhook processing failed.', 500);
        }

        return response()->noContent();
    }

    /**
     * Verify HMAC SHA256 signature.
     */
    private function verifySignature(
        Request $request,
        string $rawBody
    ): void {
        $header = (string) $request->header(
            'Pixelforge-Signature'
        );

        $parts = [];

        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(
                explode('=', trim($part), 2),
                2,
                ''
            );

            $parts[$key] = $value;
        }

        $timestamp = filter_var(
            $parts['t'] ?? null,
            FILTER_VALIDATE_INT
        );

        $signature = $parts['v1'] ?? '';

        $secret = config('services.pixelforge.webhook_secret');

        if (
            !$timestamp ||
            !$secret ||
            !preg_match('/^[a-f0-9]{64}$/i', $signature) ||
            abs(time() - $timestamp) > 300
        ) {
            abort(400, 'Invalid Pixelforge signature.');
        }

        $expected = hash_hmac(
            'sha256',
            $timestamp . '.' . $rawBody,
            $secret
        );

        if (!hash_equals($expected, $signature)) {
            abort(400, 'Invalid Pixelforge signature.');
        }
    }

    /**
     * Detect unique constraint violations across common databases.
     */
    private function isUniqueViolation(
        QueryException $exception
    ): bool {
        $sqlState = $exception->errorInfo[0] ?? null;
        $driverCode = $exception->errorInfo[1] ?? null;

        return $sqlState === '23505' // PostgreSQL
            || $driverCode === 1062 // MySQL
            || ($sqlState === '23000' && $driverCode === 19); // SQLite
    }
}