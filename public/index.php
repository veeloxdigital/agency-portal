<?php

declare(strict_types=1);

use App\Core\Application;
use App\Core\Env;

define('BASE_PATH', dirname(__DIR__));

$composer = BASE_PATH . '/vendor/autoload.php';
if (is_file($composer)) {
    require $composer;
} else {
    spl_autoload_register(static function (string $class): void {
        $prefix = 'App\\';
        if (!str_starts_with($class, $prefix)) {
            return;
        }
        $file = BASE_PATH . '/app/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($file)) {
            require $file;
        }
    });
}

Env::load(BASE_PATH . '/.env');

date_default_timezone_set(Env::get('APP_TIMEZONE', 'Europe/London'));

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('veelox_portal');
    session_set_cookie_params([
        'httponly' => true,
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
        'path' => '/',
    ]);
    session_start();
}

$app = new Application(require BASE_PATH . '/config/routes.php');
$app->run();
