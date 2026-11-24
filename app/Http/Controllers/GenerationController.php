<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGenerationRequest;
use App\Http\Resources\GenerationResource;
use App\Models\Generation;
use App\Services\GenerationService;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

/**
 *
 * Responsibilities:
 * - Display the authenticated user's generation history.
 * - Delegate generation creation and provider communication to GenerationService.
 *
 */
class GenerationController extends Controller
{
    /**
     * Display the authenticated user's generation history.
     */
    public function index()
    {
        $user = Auth::user();

        $generations = Generation::query()
            ->where('user_id', $user->id)
            ->latest()
            ->paginate(10);

        return Inertia::render('Generations/Index', [
            'generations' => GenerationResource::collection($generations),
            'balance' => $user->credit_balance,
        ]);
    }

    /**
     * Create and submit a new image generation request.
     *
     * GenerationService handles pricing, database creation,
     * Pixelforge submission, and provider response handling.
     */
    public function store(
        StoreGenerationRequest $request,
        GenerationService $generationService
    ) {
        $result = $generationService->create(
            $request->user(),
            $request->validated()
        );

        // Return validation-style errors if generation submission fails.
        if (!$result['success']) {
            return back()->withErrors([
                $result['field'] => $result['message'],
            ]);
        }

        // Redirect to generation history with the appropriate flash message.
        return redirect()
            ->route('generations.index')
            ->with(
                $result['flash_type'] ?? 'success',
                $result['message']
            );
    }
}
