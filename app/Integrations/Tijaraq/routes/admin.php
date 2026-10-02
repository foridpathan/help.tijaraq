<?php

use App\Integrations\Tijaraq\Http\Controllers\IntegrationSetupController;
use Illuminate\Support\Facades\Route;

/*
 * Admin dashboard setup page. Registered even while the integration is
 * switched off, because this is where it gets switched on.
 */
Route::prefix('api/v1/admin/tijaraq-integration')
    ->middleware(['api', 'auth:sanctum', 'isAdmin'])
    ->name('tijaraq.admin.')
    ->group(function () {
        Route::get('/', [IntegrationSetupController::class, 'show'])->name('show');
        Route::put('/', [IntegrationSetupController::class, 'update'])->name('update');
        Route::post('generate', [IntegrationSetupController::class, 'generate'])->name('generate');
    });
