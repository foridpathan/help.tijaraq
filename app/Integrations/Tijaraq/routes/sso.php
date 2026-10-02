<?php

use App\Integrations\Tijaraq\Http\Controllers\SsoController;
use Illuminate\Support\Facades\Route;

// browser redirect from app.tijaraq.com, needs the session (web group)
Route::middleware(['web', 'throttle:tijaraq-sso'])->get(
    'sso/tijaraq',
    SsoController::class,
)->name('tijaraq.sso');
