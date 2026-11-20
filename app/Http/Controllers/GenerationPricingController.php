<?php

namespace App\Http\Controllers;

use App\Services\PixelforgePricingService;
use App\Http\Requests\StoreGenerationRequest;
use Illuminate\Validation\Rule;
use Throwable;

/**
 *
 * Responsibilities:
 * - Calculate estimated pricing before sending create generation request to PixelForge 
 *
 */
class GenerationPricingController extends Controller
{
    public function __invoke(
        StoreGenerationRequest $request,
        PixelforgePricingService $pricingService
    ) {
        $validated = $request->validated();
        
        try {
            $pricing = $pricingService->calculate(
                $validated['prompt'] ?? '',
                $validated['size'],
                $validated['quality'],
                $validated['reference_images'] ?? []
            );

            return response()->json([ 'success' => true, 'pricing' => $pricing ]);

        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => 'Unable to calculate pricing right now. Please try again.',
            ], 503);
        }
    }
}
