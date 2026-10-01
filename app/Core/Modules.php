<?php

namespace App\Core;

use Ai\AiServiceProvider;
use Envato\EnvatoServiceProvider;
use Illuminate\Foundation\Application;
use Livechat\LiveChatServiceProvider;

class Modules
{
    public static function livechatInstalled(): bool
    {
        return config('modules.livechat.installed') ?? false;
    }

    public static function aiInstalled(): bool
    {
        return config('modules.ai.installed') ?? false;
    }

    public static function envatoInstalled(): bool
    {
        return config('modules.envato.installed') ?? false;
    }

    public static function register(Application $app): void
    {
        if (static::safeClassExists(EnvatoServiceProvider::class)) {
            config()->set([
                'modules.envato.installed' => true,
            ]);
            (new EnvatoServiceProvider($app))->register();
        }

        if (static::safeClassExists(LiveChatServiceProvider::class)) {
            config()->set([
                'modules.livechat.installed' => true,
                'modules.livechat.setup' => true,
            ]);
            (new LiveChatServiceProvider($app))->register();
        }

        if (static::safeClassExists(AiServiceProvider::class)) {
            config()->set([
                'modules.ai.installed' => true,
            ]);
            (new AiServiceProvider($app))->register();
        }
    }

    public static function boot(Application $app): void
    {
        if (static::safeClassExists(EnvatoServiceProvider::class)) {
            (new EnvatoServiceProvider($app))->boot();
            config()->set([
                'modules.envato.setup' =>
                    config('services.envato.client_id') &&
                    config('services.envato.client_secret') &&
                    config('services.envato.personal_token') &&
                    settings('envato.enable'),
            ]);
        }

        if (static::safeClassExists(LiveChatServiceProvider::class)) {
            (new LiveChatServiceProvider($app))->boot();
        }

        if (static::safeClassExists(AiServiceProvider::class)) {
            (new AiServiceProvider($app))->boot();
            config()->set([
                'modules.ai.setup' => !!(
                    config('services.openai.api_key') ||
                    config('services.anthropic.api_key') ||
                    config('services.gemini.api_key') ||
                    config('services.openrouter.api_key')
                ),
            ]);
        }
    }

    protected static function safeClassExists(string $className): bool
    {
        try {
            return class_exists($className);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
