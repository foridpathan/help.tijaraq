<?php

namespace App\Integrations\Tijaraq\Http\Controllers;

use App\Integrations\Tijaraq\Services\IntegrationSetup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use RuntimeException;

/**
 * Admin dashboard > Settings > TijaraQ integration. Admin only.
 */
class IntegrationSetupController extends Controller
{
    public function __construct(protected IntegrationSetup $setup) {}

    public function show(): JsonResponse
    {
        return $this->noStore(['status' => $this->setup->status()]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'enabled' => 'sometimes|boolean',
            'webhook_url' => 'sometimes|nullable|url|max:255',
            'sso_issuer' => 'sometimes|url|max:255',
            'sso_audience' => 'sometimes|url|max:255',
            'allowed_ips' => ['sometimes', 'nullable', 'string', 'max:500', 'regex:/^[0-9a-fA-F:.\/,\s]*$/'],
            'rate_limit_per_tenant' => 'sometimes|integer|min:10|max:100000',
        ]);

        if (array_key_exists('allowed_ips', $data)) {
            $data['allowed_ips'] = preg_replace('/\s+/', '', (string) $data['allowed_ips']);
        }
        if (array_key_exists('webhook_url', $data)) {
            $data['webhook_url'] = (string) $data['webhook_url'];
        }

        return $this->run(fn() => ['status' => $this->setup->updateSettings($data)]);
    }

    public function generate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'parts' => 'required|array|min:1',
            'parts.*' => 'in:' . implode(',', IntegrationSetup::PARTS),
            'main_app_url' => 'required|url|max:255',
            'helpdesk_url' => 'required|url|max:255',
            'key_id' => ['nullable', 'string', 'regex:/^[A-Za-z0-9_-]{1,32}$/'],
            'sso_kid' => ['nullable', 'string', 'regex:/^[A-Za-z0-9._-]{1,48}$/'],
            'keep_old_api_key' => 'boolean',
            'enable' => 'boolean',
        ]);

        return $this->run(fn() => $this->setup->generate($data));
    }

    protected function run(callable $callback): JsonResponse
    {
        try {
            return $this->noStore($callback());
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // the generate response contains secrets: never cache it
    protected function noStore(array $data): JsonResponse
    {
        return response()->json($data)->withHeaders([
            'Cache-Control' => 'no-store, private',
            'Pragma' => 'no-cache',
        ]);
    }
}
