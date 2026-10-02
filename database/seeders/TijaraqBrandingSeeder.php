<?php

namespace Database\Seeders;

use Common\Settings\Models\Setting;
use Illuminate\Database\Seeder;

class TijaraqBrandingSeeder extends Seeder
{
    public function run(): void
    {
        $replacements = [
            'branding.site_name' => ['BeDesk', 'TijaraQ Help'],
            'branding.logo_dark' => [
                'images/logo-dark.png',
                'images/tijaraq-logo-dark.svg',
            ],
            'branding.logo_dark_mobile' => [
                'images/logo-dark-mobile.png',
                'images/tijaraq-mark-dark.svg',
            ],
            'branding.logo_light' => [
                'images/logo-light.png',
                'images/tijaraq-logo-light.svg',
            ],
            'branding.logo_light_mobile' => [
                'images/logo-light-mobile.png',
                'images/tijaraq-mark-light.svg',
            ],
        ];

        foreach ($replacements as $name => [$oldValue, $newValue]) {
            Setting::where('name', $name)
                ->where('value', $oldValue)
                ->update(['value' => $newValue]);
        }
    }
}
