<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;

final class AuthController
{
    public function showLogin(): void
    {
        View::render('auth/login', [
            'title' => 'Sign in',
            'error' => $_SESSION['_flash_error'] ?? null,
        ], 'guest');
        unset($_SESSION['_flash_error']);
    }

    public function login(): never
    {
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '' || !Auth::attempt($email, $password)) {
            $_SESSION['_flash_error'] = 'Those login details were not recognised.';
            header('Location: /login');
            exit;
        }

        header('Location: /');
        exit;
    }

    public function logout(): never
    {
        Auth::logout();
        header('Location: /login');
        exit;
    }
}
