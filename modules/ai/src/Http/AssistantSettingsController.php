<?php

namespace Ai\Http;

use Common\Settings\DotEnvEditor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\Rule;

class AssistantSettingsController extends Controller
{
    private const PROVIDERS = ['openai', 'anthropic', 'gemini', 'openrouter'];

    public function show(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission('settings.update'), 403);

        return response()->json(['settings' => $this->settings()]);
    }

    public function update(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission('settings.update'), 403);
        $data = $request->validate([
            'provider' => ['required', Rule::in(self::PROVIDERS)],
            'model' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9._:\/\-]+$/'],
            'api_key' => ['nullable', 'string', 'max:500', 'regex:/^[^\s]+$/'],
        ]);

        $provider = $data['provider'];
        $envPrefix = strtoupper($provider);
        $values = [
            'LLM_PROVIDER' => $provider,
            $envPrefix . '_TEXT_MODEL' => $data['model'],
        ];
        if (!empty($data['api_key'])) {
            $values[$envPrefix . '_API_KEY'] = $data['api_key'];
        }

        (new DotEnvEditor())->write($values);
        config()->set('services.llm_provider', $provider);
        config()->set("services.$provider.text_model", $data['model']);
        if (!empty($data['api_key'])) {
            config()->set("services.$provider.api_key", $data['api_key']);
            config()->set("prism.providers.$provider.api_key", $data['api_key']);
        }
        if (file_exists(app()->getCachedConfigPath())) {
            Artisan::call('config:clear');
        }

        return response()->json(['settings' => $this->settings()]);
    }

    private function settings(): array
    {
        $providers = [];
        foreach (self::PROVIDERS as $name) {
            $providers[$name] = [
                'configured' => (bool) config("services.$name.api_key"),
                'model' => (string) config("services.$name.text_model", ''),
            ];
        }

        return [
            'provider' => config('services.llm_provider', 'openai'),
            'providers' => $providers,
        ];
    }
}
