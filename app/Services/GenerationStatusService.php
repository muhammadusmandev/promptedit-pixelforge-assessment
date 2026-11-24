<?php

namespace App\Services;

use App\Models\Generation;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class GenerationStatusService
{
    public function __construct(
        private CreditService $creditService
    ) {}

    /**
     * Apply the latest provider generation object.
     */
    public function apply(
        Generation $generation,
        array $providerData
    ): void {
        $status = $providerData['status'] ?? null;
        
        if (!in_array($status, ['queued', 'processing', 'succeeded', 'failed'], true)) {
            throw new InvalidArgumentException('Invalid Pixelforge generation status.');
        }

        DB::transaction(function () use (
            $generation,
            $providerData,
            $status
        ) {
            $local = Generation::query()
                ->lockForUpdate()
                ->findOrFail($generation->id);

            // Save provider ID even when webhook arrives
            // before the original create request returns.
            if (!empty($providerData['id'])) {
                if (
                    $local->provider_id !== null &&
                    $local->provider_id !== $providerData['id']
                ) {
                    throw new InvalidArgumentException('Provider generation ID mismatch.');
                }

                $local->provider_id = $providerData['id'];
            }

            // Save the original rates supplied by Pixelforge.
            if (!empty($providerData['rates'])) {
                $local->provider_rates = $providerData['rates'];
            }

            // Never change an already finalized generation.
            if (in_array($local->status, ['succeeded', 'failed', 'rejected'], true)) {
                $local->save();
                return;
            }

            // A queued event must not overwrite processing.
            if ($status === 'queued') {
                $local->save();
                return;
            }

            if ($status === 'processing') {
                $local->status = 'processing';
                $local->save();
                return;
            }

            // Failed generations are never billed.
            if ($status === 'failed') {
                $local->status = 'failed';
                $local->provider_cost = '0.000000000';
                $local->actual_cost = '0.000000000';
                $local->image_url = null;
                $local->provider_usage = null;
                $local->completed_at = $providerData['completed_at'] ?? now();

                $local->save();

                $this->creditService->refundGeneration($local);

                return;
            }

            // Successful generations must have usage and rates.
            if (
                empty($providerData['usage']) ||
                empty($providerData['image_url'])
            ) {
                throw new InvalidArgumentException('Successful generation is missing usage or image URL.');
            }

            $rates = $providerData['rates'] ?? $local->provider_rates;

            if (!$rates) {
                throw new InvalidArgumentException('Provider rates are missing.');
            }

            $usage = $providerData['usage'];

            $providerCostNano = $this->calculateProviderCost(
                $usage,
                $rates
            );

            // Apply 25% markup. actual_cost = provider_cost × 1.25
            $customerCostNano = intdiv($providerCostNano * 125 + 99, 100);   

            $local->status = 'succeeded';
            $local->image_url = $providerData['image_url'];
            $local->provider_usage = $usage;
            $local->provider_cost = formatNanoDollars($providerCostNano);
            $local->actual_cost = formatNanoDollars($customerCostNano);
            $local->completed_at = $providerData['completed_at'] ?? now();

            $local->save();

            $this->creditService->settle(
                $local,
                $local->actual_cost
            );
        });
    }

    /**
     * Calculate provider cost using integer nanodollars.
     *
     * Rates are USD per million tokens.
     * Supports up to 9 decimal places.
     * 
     * Formula: Provider Cost = Token Usage × Rate Per 1M / 1,000,000
     * 
     */
    private function calculateProviderCost(
        array $usage,
        array $rates
    ): int {
        $types = [
            'input_text_tokens',
            'input_image_tokens',
            'output_tokens',
            'reasoning_tokens',
        ];

        $total = 0;

        foreach ($types as $type) {
            if (!isset($usage[$type]) || !is_numeric($usage[$type]) || (int) $usage[$type] < 0 || !isset($rates[$type])) {
                throw new InvalidArgumentException("Invalid usage or rate for {$type}.");
            }

            if (!is_int($usage[$type]) && !ctype_digit((string) $usage[$type])
            ) {
                throw new InvalidArgumentException("Usage must be a non-negative integer for {$type}.");
            }

            // Rates are USD per million tokens.
            $rate = (string) $rates[$type];

            if (!preg_match('/^\d+(\.\d{1,9})?$/', $rate)) {
                throw new InvalidArgumentException("Invalid rate for {$type}.");
            }

            $parts = explode('.', $rate);

            $whole = (int) $parts[0];

            // Preserve all 9 decimal places.
            $fraction = str_pad($parts[1] ?? '', 9, '0');

            $rateNano = ($whole * 1_000_000_000) + (int) $fraction;

            // USD per million tokens -> nanodollars per token.
            // Divide by 1,000,000.
            $numerator = (int) $usage[$type] * $rateNano;

            $costNano = intdiv($numerator + 999_999, 1_000_000);

            $total += $costNano;
        }

        return $total;
    }
}
