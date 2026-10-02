<?php

namespace Livechat\Http;

use Common\Settings\Models\Setting;
use Common\Settings\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;

class ChatSettingsController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission('settings.update'), 403);

        return response()->json(['enabled' => (bool) settings('chat.enabled', true)]);
    }

    public function update(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission('settings.update'), 403);
        $data = $request->validate(['enabled' => 'required|boolean']);

        Setting::updateOrCreate(
            ['name' => 'chat.enabled'],
            ['value' => $data['enabled'] ? '1' : '0'],
        );
        Cache::forget('settings.public');
        app(Settings::class)->set('chat.enabled', (bool) $data['enabled']);

        return response()->json(['enabled' => $data['enabled']]);
    }
}
