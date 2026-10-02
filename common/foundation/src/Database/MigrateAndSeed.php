<?php

namespace Common\Database;

use Common\Core\Manifest\BuildManifestFile;
use Common\Settings\GenerateFavicon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\Eloquent\Model;

class MigrateAndSeed
{
    public function execute(): void
    {
        // Migrate
        if (!app('migrator')->repositoryExists()) {
            app('migration.repository')->createRepository();
        }
        $migrator = app('migrator');
        $paths = $migrator->paths();
        $paths[] = app('path.database') . DIRECTORY_SEPARATOR . 'migrations';
        $migrator->run($paths);

        // Seed
        $seeder = app(DatabaseSeeder::class);
        $seeder->setContainer(app());
        Model::unguarded(function () use ($seeder) {
            $seeder->__invoke();
        });

        // Manifest
        app(BuildManifestFile::class)->execute();

        $defaultFaviconPath = public_path('images/favicon-original.png');
        if (file_exists($defaultFaviconPath) && !file_exists(public_path('favicon.ico'))) {
            app(GenerateFavicon::class)->execute($defaultFaviconPath);
        }
    }
}
