<?php

namespace Database\Seeders;

use Common\Settings\Models\Setting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

class TijaraqBrandingSeeder extends Seeder
{
    public function run(): void
    {
        $replacements = [
            'branding.site_name' => [['BeDesk'], 'TijaraQ Help'],
            'branding.favicon' => [
                ['images/favicon.ico', 'images/tijaraq-mark-dark.svg'],
                'images/favicon.png',
            ],
            'branding.logo_dark' => [
                ['images/logo-dark.png', 'images/tijaraq-logo-dark.svg'],
                'images/logo_dark.png',
            ],
            'branding.logo_dark_mobile' => [
                ['images/logo-dark-mobile.png', 'images/tijaraq-mark-dark.svg'],
                'images/favicon.png',
            ],
            'branding.logo_light' => [
                ['images/logo-light.png', 'images/tijaraq-logo-light.svg'],
                'images/logo_light.png',
            ],
            'branding.logo_light_mobile' => [
                ['images/logo-light-mobile.png', 'images/tijaraq-mark-light.svg'],
                'images/faviconDark.png',
            ],
        ];

        foreach ($replacements as $name => [$oldValues, $newValue]) {
            Setting::where('name', $name)
                ->whereIn('value', $oldValues)
                ->update(['value' => $newValue]);
        }

        $menusSetting = Setting::where('name', 'menus')->first();
        if ($menusSetting) {
            $menus = $menusSetting->value;
            foreach ($menus as &$menu) {
                $newItems = match ($menu['name'] ?? '') {
                    'Dashboard sidebar' => [
                        ['id' => 'tijaraq-livechat-agent', 'label' => 'Livechat', 'action' => '/dashboard/livechat', 'type' => 'route', 'permissions' => ['conversations.update']],
                        ['id' => 'tijaraq-ai-assistant', 'label' => 'AI assistant', 'action' => '/dashboard/ai-assistant', 'type' => 'route', 'permissions' => ['conversations.update']],
                    ],
                    'Header Menu' => [
                        ['id' => 'tijaraq-livechat-customer', 'label' => 'Livechat', 'action' => '/livechat', 'type' => 'route'],
                    ],
                    default => [],
                };
                foreach ($newItems as $newItem) {
                    if (!collect($menu['items'])->contains(fn($item) => ($item['action'] ?? null) === $newItem['action'])) {
                        $newItem['order'] = count($menu['items']);
                        $newItem['position'] = 0;
                        $menu['items'][] = $newItem;
                    }
                }

                if ($menu['name'] !== 'Footer') {
                    continue;
                }

                $menu['items'] = array_values(array_filter(
                    $menu['items'],
                    fn($item) => ($item['action'] ?? null) !== '/api-docs',
                ));

                foreach ($menu['items'] as &$item) {
                    $destinations = [
                        '/pages/privacy-policy' => 'https://tijaraq.com/privacy-policy/',
                        '/pages/terms-of-service' => 'https://tijaraq.com/terms-of-service/',
                    ];
                    if (isset($destinations[$item['action']])) {
                        $item['action'] = $destinations[$item['action']];
                        $item['type'] = 'link';
                    }
                }
                unset($item);

                if (!collect($menu['items'])->contains(fn($item) => $item['action'] === 'https://tijaraq.com/about-us/')) {
                    $menu['items'][] = [
                        'type' => 'link',
                        'id' => 'tijaraq-about',
                        'order' => count($menu['items']),
                        'position' => 4,
                        'label' => 'About TijaraQ',
                        'action' => 'https://tijaraq.com/about-us/',
                    ];
                }
            }
            unset($menu);

            $menusSetting->value = $menus;
            $menusSetting->save();
        }

        Cache::forget('settings.public');
    }
}
