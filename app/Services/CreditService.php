<?php

namespace App\Services;

use App\Models\CreditTransaction;
use App\Models\Generation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class CreditService
{
    /**
     * Add purchased credits to a user's balance.
     */
    public function purchase(User $user, string $amount): void
    {
        $amountNano = toNanoDollars($amount);

        if ($amountNano <= 0) {
            throw new RuntimeException('Invalid credit amount.');
        }

        DB::transaction(function () use ($user, $amountNano) {
            $lockedUser = User::query()
                ->lockForUpdate()
                ->findOrFail($user->id);

            $balance = toNanoDollars($lockedUser->credit_balance);

            $newBalance = $balance + $amountNano;

            $lockedUser->credit_balance = formatNanoDollars($newBalance);
            $lockedUser->save();

            CreditTransaction::create([
                'user_id' => $lockedUser->id,
                'type' => 'purchase',
                'amount' => formatNanoDollars($amountNano),
                'balance_after' => formatNanoDollars($newBalance),
                'reference' => 'purchase-' . Str::uuid(),
                'description' => 'Fake credit purchase',
            ]);
        });
    }

    /**
     * Reserve credits before submitting a generation.
     */
    public function reserve(
        User $user,
        Generation $generation,
        string $amount
    ): void {
        $amountNano = toNanoDollars($amount);

        DB::transaction(function () use (
            $user,
            $generation,
            $amountNano
        ) {
            $lockedUser = User::query()
                ->lockForUpdate()
                ->findOrFail($user->id);

            $balance = toNanoDollars($lockedUser->credit_balance);

            if ($balance < $amountNano) {
                throw new RuntimeException(
                    'Insufficient credits. Please buy more credits.'
                );
            }

            $newBalance = $balance - $amountNano;

            $lockedUser->credit_balance = formatNanoDollars($newBalance);
            $lockedUser->save();

            CreditTransaction::create([
                'user_id' => $lockedUser->id,
                'generation_id' => $generation->id,
                'type' => 'reservation',
                'amount' => formatNanoDollars($amountNano),
                'balance_after' => formatNanoDollars($newBalance),
                'reference' => 'generation-' . $generation->id . '-reservation',
                'description' => 'Credits reserved for image generation',
            ]);
        });
    }

    
    /**
     * Settle reserved credits after successful generation.
     *
     * Refund unused credits or charge additional actual costs.
     */
    public function settle(
        Generation $generation,
        string $actualCost
    ): void {
        DB::transaction(function () use ($generation, $actualCost) {

            // Lock generation to prevent concurrent settlement.
            $lockedGeneration = Generation::query()
                ->lockForUpdate()
                ->findOrFail($generation->id);

            // Retrieve and lock the original reservation.
            $reservation = CreditTransaction::query()
                ->where('generation_id', $lockedGeneration->id)
                ->where('type', 'reservation')
                ->lockForUpdate()
                ->first();

            if (!$reservation) {
                throw new RuntimeException(
                    'Generation credit reservation not found.'
                );
            }

            $actualNano = toNanoDollars($actualCost);
            $reservedNano = toNanoDollars($reservation->amount);

            if ($actualNano < 0 || $reservedNano <= 0) {
                throw new RuntimeException(
                    'Invalid generation settlement amount.'
                );
            }

            // Calculate the difference.
            $difference = abs($reservedNano - $actualNano);

            if ($difference === 0) {
                return;
            }

            $isRefund = $actualNano < $reservedNano;

            $reference = 'generation-' .
                $lockedGeneration->id .
                ($isRefund ? '-refund' : '-charge');

            // Prevent duplicate settlement transactions.
            if (
                CreditTransaction::query()
                    ->where('reference', $reference)
                    ->exists()
            ) {
                return;
            }

            // Lock the user's balance.
            $lockedUser = User::query()
                ->lockForUpdate()
                ->findOrFail($lockedGeneration->user_id);

            $balance = toNanoDollars(
                $lockedUser->credit_balance
            );

            // Refund unused credits or deduct additional costs.
            $newBalance = $isRefund
                ? $balance + $difference
                : $balance - $difference;

            $lockedUser->credit_balance = formatNanoDollars(
                $newBalance
            );

            $lockedUser->save();

            CreditTransaction::create([
                'user_id' => $lockedUser->id,
                'generation_id' => $lockedGeneration->id,
                'type' => $isRefund ? 'refund' : 'charge',
                'amount' => formatNanoDollars($difference),
                'balance_after' => formatNanoDollars($newBalance),
                'reference' => $reference,
                'description' => $isRefund
                    ? 'Unused generation credits refunded'
                    : 'Additional generation cost charged',
            ]);
        });
    }


    /**
     * Refund reserved credits after generation failure.
     */
    public function refundGeneration(Generation $generation): void
    {
        DB::transaction(function () use ($generation) {
            $reservation = CreditTransaction::query()
                ->where('generation_id', $generation->id)
                ->where('type', 'reservation')
                ->first();

            if (!$reservation) {
                return;
            }

            $amountNano = toNanoDollars($reservation->amount);

            $this->refund(
                $generation,
                $amountNano,
                'Generation failed. Reserved credits refunded'
            );
        });
    }

    /**
     * Perform an idempotent credit refund.
     */
    private function refund(
        Generation $generation,
        int $amountNano,
        string $description
    ): void {
        if ($amountNano <= 0) {
            return;
        }

        $reference = 'generation-' . $generation->id . '-refund';

        // Prevent duplicate refunds.
        if (
            CreditTransaction::query()
                ->where('reference', $reference)
                ->exists()
        ) {
            return;
        }

        $lockedUser = User::query()
            ->lockForUpdate()
            ->findOrFail($generation->user_id);

        $balance = toNanoDollars($lockedUser->credit_balance);

        $newBalance = $balance + $amountNano;

        $lockedUser->credit_balance = formatNanoDollars($newBalance);
        $lockedUser->save();

        CreditTransaction::create([
            'user_id' => $lockedUser->id,
            'generation_id' => $generation->id,
            'type' => 'refund',
            'amount' => formatNanoDollars($amountNano),
            'balance_after' => formatNanoDollars($newBalance),
            'reference' => $reference,
            'description' => $description,
        ]);
    }
}
