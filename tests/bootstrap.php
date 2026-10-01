<?php

/**
 * Builds the throw-away test database once per run: drops every table in
 * help_tijrak_test, migrates and seeds it the same way the installer does.
 */

use Common\Core\Install\CreateDefaultCustomPages;
use Common\Core\Install\CreateDefaultMenus;
use Common\Core\Install\InsertDefaultSettings;
use Common\Database\MigrateAndSeed;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

// never wipe a real database
if (DB::connection()->getDatabaseName() !== 'help_tijrak_test') {
    fwrite(
        STDERR,
        "Refusing to run: tests must use the help_tijrak_test database.\n",
    );
    exit(1);
}

Schema::defaultStringLength(191);
Schema::dropAllTables();

(new MigrateAndSeed())->execute(function () {
    (new InsertDefaultSettings())->execute();
    (new CreateDefaultMenus())->execute();
    (new CreateDefaultCustomPages())->execute();
});
