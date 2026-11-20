<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\{
    GenerationPricingController, 
    PixelforgeWebhookController, 
    GenerationController 
};
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Home');
});

Route::post(
    '/webhooks/pixelforge',
    PixelforgeWebhookController::class
)->name('webhooks.pixelforge');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard', [
            'user' => auth()->user(),
        ]);
    })->name('dashboard');

    Route::get('/generations', [
        GenerationController::class,
        'index',
    ])->name('generations.index');

    Route::post('/generations', [
        GenerationController::class,
        'store',
    ])->name('generations.store');

    Route::get('/generations/create', function () {
        return Inertia::render('Generations/Create');
    })->name('generations.create');

    Route::post(
        '/generations/pricing',
        GenerationPricingController::class
    )->name('generations.pricing');
});