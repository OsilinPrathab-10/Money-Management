<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Maintenance Mode & Server Down Controller (/devosilinprathab)
if (file_exists($serverGuard = __DIR__.'/bootstrap/server_guard.php')) {
    require_once $serverGuard;
}

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require __DIR__.'/vendor/autoload.php';

// CGI / ngrok: Authorization is often only in REDIRECT_HTTP_AUTHORIZATION after rewrite.
if (empty($_SERVER['HTTP_AUTHORIZATION'])) {
    foreach (['REDIRECT_HTTP_AUTHORIZATION', 'REDIRECT_REDIRECT_HTTP_AUTHORIZATION'] as $key) {
        if (! empty($_SERVER[$key])) {
            $_SERVER['HTTP_AUTHORIZATION'] = $_SERVER[$key];
            break;
        }
    }
}

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/bootstrap/app.php';

$app->handleRequest(Request::capture());
