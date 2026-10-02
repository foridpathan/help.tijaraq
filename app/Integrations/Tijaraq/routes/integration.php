<?php

use App\Integrations\Tijaraq\Exceptions\IntegrationException;
use App\Integrations\Tijaraq\Http\Controllers\AttachmentsController;
use App\Integrations\Tijaraq\Http\Controllers\MessagesController;
use App\Integrations\Tijaraq\Http\Controllers\MetaController;
use App\Integrations\Tijaraq\Http\Controllers\TicketsController;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;

/*
 * Server-to-server API used by app.tijaraq.com. Deliberately NOT inside the
 * vendor optionalAuth:sanctum,verified,verifyApiAccess group: no session, no
 * CSRF, authentication is the HMAC signature only.
 */
Route::prefix('api/integration/v1')
    ->name('tijaraq.integration.')
    ->middleware([
        'tijaraq.ip',
        'throttle:tijaraq-ip',
        'tijaraq.hmac',
        'throttle:tijaraq-tenant',
        'tijaraq.provision',
        SubstituteBindings::class,
    ])
    ->group(function () {
        Route::get('meta', MetaController::class)->name('meta');

        Route::get('tickets', [TicketsController::class, 'index'])->name('tickets.index');
        Route::post('tickets', [TicketsController::class, 'store'])->name('tickets.store');
        Route::get('tickets/{id}', [TicketsController::class, 'show'])->name('tickets.show');
        Route::post('tickets/{id}/close', [TicketsController::class, 'close'])->name('tickets.close');
        Route::post('tickets/{id}/reopen', [TicketsController::class, 'reopen'])->name('tickets.reopen');

        Route::get('tickets/{id}/messages', [MessagesController::class, 'index'])->name('messages.index');
        Route::post('tickets/{id}/replies', [MessagesController::class, 'store'])->name('replies.store');

        Route::post('attachments', [AttachmentsController::class, 'store'])->name('attachments.store');
        Route::get('attachments/{id}', [AttachmentsController::class, 'show'])->name('attachments.show');
    });

// anything else below the prefix answers with the contract error envelope
Route::any('api/integration/{any?}', function () {
    throw IntegrationException::notFound();
})->where('any', '.*');
