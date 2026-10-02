<?php

use App\Core\Middleware\ConfigureCookies;
use App\Core\Middleware\SecurityHeaders;
use App\Integrations\Tijaraq\TijaraqIntegrationServiceProvider;
use Ai\TijaraqAiServiceProvider;
use Common\Core\Application;
use Common\Core\Middleware\BroadcastServiceProvider;
use Livechat\TijaraqChatServiceProvider;

return Application::create(
    basePath: dirname(__DIR__),
    providers: [
        BroadcastServiceProvider::class,
        TijaraqIntegrationServiceProvider::class,
        TijaraqChatServiceProvider::class,
        TijaraqAiServiceProvider::class,
    ],
    middleware: [ConfigureCookies::class, SecurityHeaders::class],
);
