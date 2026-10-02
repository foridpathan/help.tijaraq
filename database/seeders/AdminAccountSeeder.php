<?php

namespace Database\Seeders;

use App\Models\User;
use Common\Auth\Actions\CreateUser;
use Common\Auth\Permissions\Permission;
use Common\Auth\Roles\Role;
use Illuminate\Database\Seeder;

/**
 * Creates the admin account, or resets its password when it already exists
 * (handy when the password is forgotten and `php artisan tinker` is not
 * installed). Safe to run more than once.
 *
 *   php artisan db:seed --class="Database\Seeders\AdminAccountSeeder"
 *
 * Defaults can be overridden from the shell (PowerShell):
 *
 *   $env:ADMIN_SEED_PASSWORD='Str0ng#Pass'
 *   php artisan db:seed --class="Database\Seeders\AdminAccountSeeder"
 */
class AdminAccountSeeder extends Seeder
{
    public const DEFAULT_EMAIL = 'admin@tijaraq.test';
    public const DEFAULT_PASSWORD = '1234';

    public function run(): void
    {
        $email = env('ADMIN_SEED_EMAIL', self::DEFAULT_EMAIL);
        $password = env('ADMIN_SEED_PASSWORD', self::DEFAULT_PASSWORD);

        $user = User::where('email', $email)->first();

        if ($user) {
            // the model hashes plain text passwords
            $user->password = $password;
            $user->type = 'agent';
            $user->email_verified_at ??= now();
            $user->save();
        } else {
            $user = (new CreateUser())->execute([
                'name' => 'Admin',
                'email' => $email,
                'password' => $password,
                'email_verified_at' => now(),
                'type' => 'agent',
            ]);
        }

        // agent role + the "admin" permission, same as the installer's admin
        $agentRole = Role::where('type', 'agents')
            ->where('name', 'Agents')
            ->first();
        if ($agentRole) {
            $user->roles()->syncWithoutDetaching([$agentRole->id]);
        }

        $adminPermission = Permission::where('name', 'admin')->first();
        if ($adminPermission) {
            $user->permissions()->syncWithoutDetaching([$adminPermission->id]);
        }

        $this->command?->info("Admin ready: $email / $password");
    }
}
