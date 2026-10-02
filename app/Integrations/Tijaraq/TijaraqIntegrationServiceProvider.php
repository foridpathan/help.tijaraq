<?php

namespace App\Integrations\Tijaraq;

use App\Attributes\Models\CustomAttribute;
use App\Conversations\Events\ConversationMessageCreated;
use App\Conversations\Events\ConversationsAssignedToAgent;
use App\Conversations\Events\ConversationsUpdated;
use App\Conversations\Models\Conversation;
use App\Conversations\Models\ConversationStatus;
use App\Integrations\Tijaraq\Console\RetryWebhooksCommand;
use App\Integrations\Tijaraq\Exceptions\IntegrationException;
use App\Integrations\Tijaraq\Http\Controllers\MetaController;
use App\Integrations\Tijaraq\Http\Middleware\HardenPasswordRoutes;
use App\Integrations\Tijaraq\Http\Middleware\ProvisionTijaraqCustomer;
use App\Integrations\Tijaraq\Http\Middleware\RestrictIntegrationIps;
use App\Integrations\Tijaraq\Http\Middleware\VerifyHmacSignature;
use App\Integrations\Tijaraq\Listeners\OnConversationMessageCreated;
use App\Integrations\Tijaraq\Listeners\OnConversationsAssignedToAgent;
use App\Integrations\Tijaraq\Listeners\OnConversationsUpdated;
use App\Integrations\Tijaraq\Services\IdempotentExecutor;
use App\Integrations\Tijaraq\Support\CreationHints;
use App\Integrations\Tijaraq\Support\TijaraqContext;
use App\Team\Models\Group;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Registered with one line in bootstrap/app.php. Everything that touches the
 * integration is a no-op when TIJARAQ_INTEGRATION_ENABLED=false; only the
 * password-route hardening (Phase 0) is always active.
 */
class TijaraqIntegrationServiceProvider extends ServiceProvider
{
    protected function enabled(): bool
    {
        return (bool) config('tijaraq-integration.enabled');
    }

    public function register(): void
    {
        // one instance so the admin controller and tests share the env file setting
        $this->app->singleton(\App\Integrations\Tijaraq\Services\IntegrationSetup::class);

        if (!$this->enabled()) {
            return;
        }

        // Registered here (register phase) so these listeners are attached
        // BEFORE AppServiceProvider::registerEvents() (boot phase) adds the
        // trigger-cycle listener that can return false and stop propagation.
        Event::listen(ConversationsUpdated::class, [
            OnConversationsUpdated::class,
            'handle',
        ]);
        Event::listen(ConversationsAssignedToAgent::class, [
            OnConversationsAssignedToAgent::class,
            'handle',
        ]);
        Event::listen(ConversationMessageCreated::class, [
            OnConversationMessageCreated::class,
            'handle',
        ]);
    }

    public function boot(Router $router): void
    {
        $this->hardenPasswordRoutes($router);

        // admin setup page: must exist while the integration is still off
        Route::group([], __DIR__ . '/routes/admin.php');

        if (!$this->enabled()) {
            return;
        }

        $router->aliasMiddleware('tijaraq.ip', RestrictIntegrationIps::class);
        $router->aliasMiddleware('tijaraq.hmac', VerifyHmacSignature::class);
        $router->aliasMiddleware('tijaraq.provision', ProvisionTijaraqCustomer::class);

        $this->defineRateLimiters();
        $this->registerRoutes();
        $this->registerExceptionRendering();
        $this->registerModelHooks();
        $this->registerConsole();
    }

    protected function hardenPasswordRoutes(Router $router): void
    {
        $router->pushMiddlewareToGroup('web', HardenPasswordRoutes::class);
        $router->pushMiddlewareToGroup('api', HardenPasswordRoutes::class);
    }

    protected function registerRoutes(): void
    {
        Route::group([], __DIR__ . '/routes/integration.php');
        Route::group([], __DIR__ . '/routes/sso.php');
    }

    protected function defineRateLimiters(): void
    {
        $rateLimited = fn(Request $request, array $headers) => IntegrationException::envelope(
            'rate_limited',
            'Too many requests.',
            429,
            null,
            $headers,
        );

        RateLimiter::for(
            'tijaraq-ip',
            fn(Request $request) => Limit::perMinute(
                (int) config('tijaraq-integration.rate_limit_per_ip', 600),
            )
                ->by('ip:' . $request->ip())
                ->response($rateLimited),
        );

        // keyed by the VERIFIED tenant (runs after the HMAC middleware)
        RateLimiter::for(
            'tijaraq-tenant',
            fn(Request $request) => Limit::perMinute(
                (int) config('tijaraq-integration.rate_limit_per_tenant', 120),
            )
                ->by('tenant:' . (TijaraqContext::current()?->tenant ?? $request->ip()))
                ->response($rateLimited),
        );

        RateLimiter::for(
            'tijaraq-sso',
            fn(Request $request) => Limit::perMinute(30)->by('sso:' . $request->ip()),
        );
    }

    // all errors below /api/integration come out as {error:{code,message}}
    protected function registerExceptionRendering(): void
    {
        $handler = $this->app->make(ExceptionHandler::class);
        if (!method_exists($handler, 'renderable')) {
            return;
        }

        $handler->renderable(function (Throwable $e, Request $request) {
            if (!$request->is('api/integration/*')) {
                return null;
            }

            if ($e instanceof IntegrationException) {
                return $e->render();
            }

            if ($e instanceof ValidationException) {
                return IntegrationException::envelope(
                    'validation_failed',
                    'The request failed validation.',
                    422,
                    $e->errors(),
                );
            }

            if ($e instanceof AuthenticationException) {
                return IntegrationException::envelope('invalid_signature', 'Unauthenticated.', 401);
            }

            if ($e instanceof ModelNotFoundException) {
                return IntegrationException::envelope('not_found', 'Resource not found.', 404);
            }

            if ($e instanceof HttpExceptionInterface) {
                $status = $e->getStatusCode();
                [$code, $message] = match ($status) {
                    401 => ['invalid_signature', 'Unauthenticated.'],
                    403 => ['forbidden', 'Forbidden.'],
                    404 => ['not_found', 'Resource not found.'],
                    405 => ['method_not_allowed', 'Method not allowed.'],
                    429 => ['rate_limited', 'Too many requests.'],
                    503 => ['maintenance', 'The service is temporarily unavailable.'],
                    default => ['error', 'Request failed.'],
                };
                return IntegrationException::envelope(
                    $code,
                    $message,
                    $status,
                    null,
                    $e->getHeaders(),
                );
            }

            // reported through the normal handler (Sentry); no details leak
            return IntegrationException::envelope('internal_error', 'Internal error.', 500);
        });
    }

    protected function registerModelHooks(): void
    {
        // copy external_company_id / priority onto integration tickets while
        // they are being created, so they are set before any event fires
        Conversation::creating(function (Conversation $conversation) {
            if ($hints = CreationHints::current()) {
                $conversation->external_company_id = $hints['external_company_id'];
                $conversation->priority = $hints['priority'];
            }
        });

        // /meta is cached for 10 minutes, drop it when its sources change
        foreach ([Group::class, CustomAttribute::class, ConversationStatus::class] as $model) {
            $model::saved(fn() => MetaController::forget());
            $model::deleted(fn() => MetaController::forget());
        }
    }

    protected function registerConsole(): void
    {
        $this->commands([RetryWebhooksCommand::class]);

        $this->app->booted(function () {
            if (!$this->app->runningInConsole()) {
                return;
            }

            $this->app
                ->make(Schedule::class)
                ->call(fn() => IdempotentExecutor::prune())
                ->name('tijaraq:prune-idempotency-keys')
                ->daily();
        });
    }
}
