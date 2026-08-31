<?php

declare(strict_types=1);

use App\Core\Database;
use App\Core\Env;

define('BASE_PATH', dirname(__DIR__));

$composer = BASE_PATH . '/vendor/autoload.php';
if (is_file($composer)) {
    require $composer;
} else {
    spl_autoload_register(static function (string $class): void {
        if (!str_starts_with($class, 'App\\')) return;
        $file = BASE_PATH . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (is_file($file)) require $file;
    });
}

Env::load(BASE_PATH . '/.env');

if (PHP_SAPI !== 'cli') {
    exit("Run this script from the command line.\n");
}

$name = trim((string) readline('Administrator name: '));
$email = strtolower(trim((string) readline('Administrator email: ')));
$password = (string) readline('Administrator password (12+ characters): ');

if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 12) {
    exit("Invalid details. Use a valid email and a password of at least 12 characters.\n");
}

$statement = Database::connection()->prepare(
    "INSERT INTO users (role_id, name, email, password_hash, status)
     SELECT id, :name, :email, :password, 'active' FROM roles WHERE slug = 'admin' LIMIT 1"
);
$statement->execute([
    'name' => $name,
    'email' => $email,
    'password' => password_hash($password, PASSWORD_DEFAULT),
]);

echo "Administrator created successfully.\n";
