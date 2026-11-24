<?php

namespace App\Http\Controllers;

use App\Http\Requests\PurchaseCreditsRequest;
use App\Http\Resources\CreditTransactionResource;
use App\Services\CreditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class CreditController extends Controller
{
    /**
     * Display credit balance and transaction history.
     */
    public function index(): Response
    {
        $user = Auth::user();

        $transactions = $user->creditTransactions()
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Credits/Index', [
            'balance' => $user->credit_balance,
            'transactions' => CreditTransactionResource::collection(
                $transactions
            ),
        ]);
    }

    /**
     * Add fake credits to the authenticated user.
     */
    public function purchase(
        PurchaseCreditsRequest $request,
        CreditService $creditService
    ): RedirectResponse {
        try {
            $creditService->purchase(
                $request->user(),
                $request->validated()['amount']
            );

            return back()->with(
                'success',
                'Credits added successfully!'
            );

        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors([
                'credits' => 'Unable to add credits. Please try again.',
            ]);
        }
    }
}
