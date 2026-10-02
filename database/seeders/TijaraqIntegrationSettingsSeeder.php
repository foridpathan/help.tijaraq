<?php

namespace Database\Seeders;

use App\Attributes\Models\CustomAttribute;
use App\Conversations\Models\Conversation;
use App\Team\Models\Group;
use Common\Settings\Settings;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Settings for the TijaraQ integration. Idempotent: safe to run on every
 * deploy.
 *
 *   php artisan db:seed --class=Database\\Seeders\\TijaraqIntegrationSettingsSeeder
 */
class TijaraqIntegrationSettingsSeeder extends Seeder
{
    public const GROUP_NAME = 'Onboarding Call';

    // merchants arrive through SSO only
    public const SOCIAL_PROVIDERS = ['google', 'facebook', 'twitter'];

    public function run(): void
    {
        $this->lockDownAccess();
        $this->seedOnboardingCallGroup();
        $this->seedOnboardingCallCategory();
    }

    protected function lockDownAccess(): void
    {
        $settings = [
            'registration.disable' => '1',
            'tickets.guest_tickets' => '0',
        ];

        foreach (self::SOCIAL_PROVIDERS as $provider) {
            $settings["social.$provider.enable"] = '0';
        }

        // Settings::save() deletes falsy values, so "0" is stored as a string
        app(Settings::class)->save($settings);
    }

    protected function seedOnboardingCallGroup(): void
    {
        Group::firstOrCreate(
            ['name' => self::GROUP_NAME],
            ['default' => false, 'assignment_mode' => 'manual'],
        );
    }

    protected function seedOnboardingCallCategory(): void
    {
        $attribute = CustomAttribute::query()
            ->where('key', 'category')
            ->where('type', Conversation::MODEL_TYPE)
            ->first();

        if (!$attribute) {
            return;
        }

        $config = $attribute->config ?? [];
        $options = $config['options'] ?? [];
        $value = Str::slug(self::GROUP_NAME);

        if (collect($options)->contains('value', $value)) {
            return;
        }

        $options[] = [
            'label' => self::GROUP_NAME,
            'value' => $value,
            'hcCategories' => [],
        ];
        $config['options'] = $options;
        $attribute->config = $config;
        $attribute->save();
    }
}
