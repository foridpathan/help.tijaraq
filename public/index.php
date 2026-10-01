<?php

use Illuminate\Http\Request;

// PHP 8.5 deprecations from vendor packages are noise; hide them
error_reporting(E_ALL & ~E_DEPRECATED);

if (version_compare(PHP_VERSION, '8.2') === -1) {
    exit('You need at least PHP 8.2 to install this application.');
}

// if not installed yet, redirect to public dir
if (!file_exists(__DIR__ . '/.htaccess')) {
    // create .htaccess files
    $htaccess = __DIR__ . '/.htaccess';
    $htaccessStub = __DIR__ . '/htaccess.example';

    $createdFile = @file_put_contents(
        $htaccess,
        @file_get_contents($htaccessStub),
    );
    if ($createdFile) {
        header('Refresh: 0');
    }
    exit();
}

define('LARAVEL_START', microtime(true));

if (
    file_exists(
        $maintenance = __DIR__ . '/../storage/framework/maintenance.php',
    )
) {
    require $maintenance;
}

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';

if (!file_exists(__DIR__ . '/../.env')) {
    $app->loadEnvironmentFrom('env.example');
}

$app->handleRequest(Request::capture());
