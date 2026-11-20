<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\RequestException;
use RuntimeException;

class PixelforgeService
{
    /**
     * Create a generation on Pixelforge.
     *
     * @throws RequestException
     */
    public function createGeneration(
        array $payload,
        string $idempotencyKey
    ): array {
        $apiUrl = rtrim(
            config('services.pixelforge.api_url'),
            '/'
        );

        $apiKey = config('services.pixelforge.api_key');

        if (!$apiUrl || !$apiKey) {
            throw new RuntimeException(
                'Pixelforge API configuration is missing.'
            );
        }

        $response = Http::baseUrl($apiUrl)
            ->withToken($apiKey)
            ->acceptJson()
            ->asJson()
            ->withHeaders([
                'Idempotency-Key' => $idempotencyKey,
            ])
            ->timeout(30)
            ->post('/generations', $payload);

        $response->throw();

        return [
            'http_status' => $response->status(),
            'data' => $response->json(),
            'idempotent_replayed' =>
                $response->header('Idempotent-Replayed') === 'true',
        ];
    }

    
    /**
     * Retrieve an existing generation from Pixelforge.
     */
    public function retrieveGeneration(string $providerId): array
    {
        $apiUrl = rtrim(
            config('services.pixelforge.api_url'),
            '/'
        );

        $apiKey = config('services.pixelforge.api_key');

        if (!$apiUrl || !$apiKey) {
            throw new \RuntimeException(
                'Pixelforge API configuration is missing.'
            );
        }

        $response = \Illuminate\Support\Facades\Http::baseUrl($apiUrl)
            ->withToken($apiKey)
            ->acceptJson()
            ->timeout(15)
            ->get('/generations/' . rawurlencode($providerId));

        $response->throw();

        return $response->json();
    }

}
