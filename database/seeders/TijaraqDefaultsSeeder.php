<?php

namespace Database\Seeders;

use App\Attributes\Models\CustomAttribute;
use App\Conversations\Models\Conversation;
use App\Team\Models\Group;
use Common\Localizations\Localization;
use Common\Localizations\LocalizationsRepository;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Defaults for the TijaraQ support portal. Safe to run more than once.
 */
class TijaraqDefaultsSeeder extends Seeder
{
    // "General" is created by DefaultGroupSeeder
    public const DEPARTMENTS = [
        'Channels & Sync',
        'Orders & Inventory',
        'Couriers & Shipping',
        'Billing & Plans',
        'POS',
        'Account & Access',
        'General',
    ];

    public const CHANNELS = [
        'shopify' => 'Shopify',
        'woocommerce' => 'WooCommerce',
        'daraz' => 'Daraz',
        'pos' => 'POS',
        'other' => 'Other',
    ];

    public function run(): void
    {
        $this->seedDepartments();
        $this->seedCategoryOptions();
        $this->seedTijaraqAttributes();
        $this->seedLocales();
    }

    // English is the default. Bangla and Arabic start with English text and are
    // translated from Admin > Localization. NOTE: the client has no RTL layout
    // support, so Arabic renders left-to-right.
    protected function seedLocales(): void
    {
        foreach (['bn' => 'Bangla', 'ar' => 'Arabic'] as $language => $name) {
            if (!Localization::where('language', $language)->exists()) {
                app(LocalizationsRepository::class)->create([
                    'name' => $name,
                    'language' => $language,
                ]);
            }
        }
    }

    protected function seedDepartments(): void
    {
        foreach (self::DEPARTMENTS as $name) {
            if ($name === 'General') {
                continue;
            }
            Group::firstOrCreate(
                ['name' => $name],
                ['default' => false, 'assignment_mode' => 'manual'],
            );
        }
    }

    // ticket "Category" dropdown mirrors the departments
    protected function seedCategoryOptions(): void
    {
        $attribute = CustomAttribute::where('key', 'category')
            ->where('type', Conversation::MODEL_TYPE)
            ->first();

        if (!$attribute || !empty($attribute->config['options'] ?? [])) {
            return;
        }

        $attribute->config = [
            'options' => collect(self::DEPARTMENTS)
                ->map(fn($name) => [
                    'label' => $name,
                    'value' => Str::slug($name),
                    'hcCategories' => [],
                ])
                ->all(),
        ];
        $attribute->save();
    }

    // filled by SSO / API later, only agents see and edit them
    protected function seedTijaraqAttributes(): void
    {
        $this->attribute('tijaraq_company_id', 'TijaraQ company ID', 'text');
        $this->attribute('tijaraq_plan', 'Plan', 'text');
        $this->attribute('tijaraq_channel', 'Channel', 'dropdown', [
            'options' => collect(self::CHANNELS)
                ->map(fn($label, $value) => compact('label', 'value'))
                ->values()
                ->all(),
        ]);
    }

    protected function attribute(
        string $key,
        string $name,
        string $format,
        ?array $config = null,
    ): void {
        if (
            CustomAttribute::where('key', $key)
                ->where('type', Conversation::MODEL_TYPE)
                ->exists()
        ) {
            return;
        }

        CustomAttribute::create([
            'name' => $name,
            'key' => $key,
            'format' => $format,
            'permission' => CustomAttribute::PERMISSION_AGENT_CAN_EDIT,
            'type' => Conversation::MODEL_TYPE,
            'required' => false,
            'internal' => false,
            'active' => true,
            'config' => $config,
        ]);
    }
}
