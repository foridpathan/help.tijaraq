<?php

namespace Livechat;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livechat\Http\ChatController;
use Livechat\Http\ChatSettingsController;

class TijaraqChatServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::prefix('api/v1/tijaraq-chat')
            ->middleware(['api', 'auth:sanctum', 'throttle:60,1'])
            ->group(function () {
                Route::get('conversations', [ChatController::class, 'index']);
                Route::post('conversations', [ChatController::class, 'store']);
                Route::get('conversations/{conversation}', [ChatController::class, 'show']);
                Route::post('conversations/{conversation}/messages', [ChatController::class, 'reply']);
            });

        Route::prefix('api/v1/admin/tijaraq-chat')
            ->middleware(['api', 'auth:sanctum', 'isAdmin'])
            ->group(function () {
                Route::get('/', [ChatSettingsController::class, 'show']);
                Route::put('/', [ChatSettingsController::class, 'update']);
            });
    }
}
