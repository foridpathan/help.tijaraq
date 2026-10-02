<?php

namespace Database\Seeders;

use App\Models\User;
use Common\Auth\Roles\Role;
use Common\Database\CreateDefaultMenus;
use Common\Database\InsertDefaultSettings;
use Common\Database\Seeders\CssThemesTableSeeder;
use Common\Database\Seeders\LocalizationsTableSeeder;
use Common\Database\Seeders\PermissionTableSeeder;
use Common\Database\Seeders\RolesTableSeeder;
use Common\Database\Seeders\UploadBackendsSeeder;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(PermissionTableSeeder::class);
        $this->call(RolesTableSeeder::class);

        (new InsertDefaultSettings())->execute();
        (new CreateDefaultMenus())->execute();
        $this->call(TijaraqBrandingSeeder::class);

        $this->call(AdminAccountSeeder::class);
        $this->call(CssThemesTableSeeder::class);
        $this->call(LocalizationsTableSeeder::class);
        app(UploadBackendsSeeder::class)->run();

        $this->call(DefaultGroupSeeder::class);
        $this->call(InternalAttributesSeeder::class);
        $this->call(TijaraqDefaultsSeeder::class);
        $this->call(TijaraqIntegrationSettingsSeeder::class);
        $this->call(ConversationStatusesSeeder::class);
        $this->call(DefaultViewsSeeder::class);

        $adminUser = app(User::class)->findAdmin();
        $agentRole = Role::where('name', 'Agents')->first();
        if ($adminUser && $agentRole) {
            $adminUser->roles()->sync([$agentRole->id], false);
        }
    }
}
