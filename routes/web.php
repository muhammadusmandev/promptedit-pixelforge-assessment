<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\{
    GenerationPricingController, 
    PixelforgeWebhookController, 
    GenerationController,
    CreditController
};
use App\Http\Controllers\Admin\AdminReportController;
use App\Http\Middleware\EnsureUserIsAdmin;
use Inertia\Inertia;

Route::post(
    '/webhooks/pixelforge',
    PixelforgeWebhookController::class
)->name('webhooks.pixelforge');

Route::middleware('auth')->group(function () {
    Route::get('/{path?}', function () {
        return Inertia::render('Dashboard', [
            'user' => auth()->user(),
        ]);
    })->where('path', 'dashboard?')->name('dashboard');

    Route::get('/generations', [
        GenerationController::class,
        'index',
    ])->name('generations.index');

    Route::post('/generations', [
        GenerationController::class,
        'store',
    ])->name('generations.store');

    Route::get('/generations/create', function () {
        return Inertia::render('Generations/Create', [
            'balance' => auth()->user()->credit_balance,
        ]);
    })->name('generations.create');

    Route::post(
        '/generations/pricing',
        GenerationPricingController::class
    )->name('generations.pricing');

    Route::get('/credits', [
        CreditController::class,
        'index',
    ])->name('credits.index');

    Route::post('/credits/purchase', [
        CreditController::class,
        'purchase',
    ])->name('credits.purchase');
});

Route::middleware(['auth', EnsureUserIsAdmin::class])
    ->prefix('admin')->name('admin.')->group(function () {
    Route::get(
        '/reports',
        [AdminReportController::class, 'index']
    )->name('reports');

    Route::get(
        '/reports/data',
        [AdminReportController::class, 'data']
    )->name('reports.data');

});