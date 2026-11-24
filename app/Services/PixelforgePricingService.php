<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PixelforgePricingService
{
    private const PRICING_URL = 'https://pixelforge.promptedit.dev/v1/pricing';

    /**
     * Fetch pricing from Pixelforge.
     * Cache rates for 5 minutes.
     */
    public function getPricing(): array
    {
        return Cache::remember(
            'pixelforge_pricing',
            now()->addMinutes(5),
            function () {
                $response = Http::acceptJson()
                    ->timeout(10)
                    ->get(self::PRICING_URL);

                if (!$response->successful()) {
                    throw new RuntimeException(
                        'Unable to retrieve Pixelforge pricing.'
                    );
                }

                $pricing = $response->json();

                if (
                    !isset(
                        $pricing['rates'],
                        $pricing['tokens']['output'],
                        $pricing['tokens']['reasoning']
                    )
                ) {
                    throw new RuntimeException(
                        'Invalid pricing response from Pixelforge.'
                    );
                }

                return $pricing;
            }
        );
    }

    /**
     * Calculate estimated generation pricing.
     */
    public function calculate(
        string $prompt,
        string $size,
        string $quality,
        array $referenceImages = []
    ): array {
        $pricing = $this->getPricing();

        $tokens = $pricing['tokens'];
        $rates = $pricing['rates'];

        // Count Unicode characters, not bytes.
        $characters = mb_strlen($prompt);

        // Estimated text tokens.
        $estimatedTextTokens = (int) ceil(
            $characters / $tokens['characters_per_text_token']
        );

        // Text token variance: +/- 15%.
        $minimumTextTokens = (int) floor(
            $estimatedTextTokens *
            (1 - $tokens['text_token_variance'])
        );

        $maximumTextTokens = (int) ceil(
            $estimatedTextTokens *
            (1 + $tokens['text_token_variance'])
        );

        // Reference image tokens.
        $imageTokens = count($referenceImages) *
            $tokens['per_reference_image'];

        // Exact output tokens.
        $outputTokens = $tokens['output'][$quality][$size];

        // Reasoning token range.
        $reasoning = $tokens['reasoning'][$quality];

        // Calculate minimum and maximum cost in nanodollars.
        $minimumCost = $this->calculateCost([
            'input_text_tokens' => $minimumTextTokens,
            'input_image_tokens' => $imageTokens,
            'output_tokens' => $outputTokens,
            'reasoning_tokens' => $reasoning['min'],
        ], $rates);

        $maximumCost = $this->calculateCost([
            'input_text_tokens' => $maximumTextTokens,
            'input_image_tokens' => $imageTokens,
            'output_tokens' => $outputTokens,
            'reasoning_tokens' => $reasoning['max'],
        ], $rates);

        $maximumCostWithMarkup = intdiv($maximumCost * 125 + 99, 100);    // Apply 25% markup

        return [
            'currency' => $pricing['currency'],

            'inputs' => [
                'prompt_characters' => $characters,
                'reference_images' => count($referenceImages),
                'size' => $size,
                'quality' => $quality,
            ],

            'tokens' => [
                'input_text' => [
                    'estimated' => $estimatedTextTokens,
                    'minimum' => $minimumTextTokens,
                    'maximum' => $maximumTextTokens,
                ],

                'input_image' => [
                    'estimated' => $imageTokens,
                ],

                'output' => [
                    'estimated' => $outputTokens,
                ],

                'reasoning' => [
                    'minimum' => $reasoning['min'],
                    'maximum' => $reasoning['max'],
                ],
            ],

            'cost' => [
                'minimum' => formatNanoDollars($minimumCost),
                'maximum' => formatNanoDollars($maximumCost),
                'maximum_with_markup' => formatNanoDollars($maximumCostWithMarkup)
            ],

            'rates' => $rates,
        ];
    }

    /**
     * Calculate total cost using integer nanodollars.
     *
     * Rates are expressed in USD per 1 million tokens.
     * Example: $5.00 = 5000 nanodollars per token.
     */
    private function calculateCost(
        array $tokenCounts,
        array $rates
    ): int {
        $total = 0;

        foreach ($tokenCounts as $type => $count) {
            $rateCents = (int) round(
                ((float) $rates[$type]) * 100
            );

            $nanoDollarsPerToken = $rateCents * 10;

            $total += $count * $nanoDollarsPerToken; // Token count × Cost per token = Total cost
        }

        return $total;
    }
}
