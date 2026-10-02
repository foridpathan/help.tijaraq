<?php

namespace Ai;

use Ai\Http\AssistantController;
use Ai\Http\AssistantSettingsController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class TijaraqAiServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::prefix('api/v1/tijaraq-ai')
            ->middleware(['api', 'auth:sanctum', 'throttle:20,1'])
            ->group(function () {
                Route::post('ask', AssistantController::class);
            });

        Route::prefix('api/v1/admin/tijaraq-ai')
            ->middleware(['api', 'auth:sanctum', 'isAdmin'])
            ->group(function () {
                Route::get('/', [AssistantSettingsController::class, 'show']);
                Route::put('/', [AssistantSettingsController::class, 'update']);
            });
    }
}
