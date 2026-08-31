<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

final class Auth
{
    private static ?array $user = null;

    public static function attempt(string $email, string $password): bool
    {
        $statement = Database::connection()->prepare(
            'SELECT users.id, users.name, users.email, users.password_hash, users.status, roles.slug AS role
             FROM users INNER JOIN roles ON roles.id = users.role_id
             WHERE users.email = :email LIMIT 1'
        );
        $statement->execute(['email' => strtolower(trim($email))]);
        $user = $statement->fetch(PDO::FETCH_ASSOC);

        if (!$user || $user['status'] !== 'active' || !password_verify($password, $user['password_hash'])) {
            return false;
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        unset($user['password_hash']);
        self::$user = $user;

        Database::connection()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id')
            ->execute(['id' => $user['id']]);

        return true;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function user(): ?array
    {
        if (self::$user !== null) {
            return self::$user;
        }

        if (empty($_SESSION['user_id'])) {
            return null;
        }

        $statement = Database::connection()->prepare(
            'SELECT users.id, users.name, users.email, users.status, roles.slug AS role
             FROM users INNER JOIN roles ON roles.id = users.role_id
             WHERE users.id = :id LIMIT 1'
        );
        $statement->execute(['id' => (int) $_SESSION['user_id']]);
        $user = $statement->fetch(PDO::FETCH_ASSOC);

        if (!$user || $user['status'] !== 'active') {
            self::logout();
            return null;
        }

        return self::$user = $user;
    }

    public static function logout(): void
    {
        self::$user = null;
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }
}
