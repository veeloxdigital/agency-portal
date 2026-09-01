<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;
use App\Core\Database;
use App\Services\ActivityService;
use Throwable;

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

        if ($this->blocked($email)) {
            $_SESSION['_flash_error'] = 'Too many sign-in attempts. Please wait 15 minutes and try again.';
            header('Location: /login');
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '' || !Auth::attempt($email, $password)) {
            $this->recordFailure($email);
            $_SESSION['_flash_error'] = 'Those login details were not recognised.';
            header('Location: /login');
            exit;
        }

        $this->clearFailures($email);ActivityService::log('auth.login','user',(int)(Auth::user()['id']??0),'Successful sign in');

        header('Location: /');
        exit;
    }

    public function logout(): never
    {
        ActivityService::log('auth.logout','user',(int)(Auth::user()['id']??0),'Signed out');
        Auth::logout();
        header('Location: /login');
        exit;
    }

    public function passwordForm(): void{View::render('auth/password',['title'=>'Change password','flash'=>$_SESSION['_password_flash']??null]);unset($_SESSION['_password_flash']);}
    public function changePassword(): never
    {
        $current=(string)($_POST['current_password']??'');$password=(string)($_POST['new_password']??'');$confirmation=(string)($_POST['new_password_confirmation']??'');$s=Database::connection()->prepare('SELECT password_hash FROM users WHERE id=:id');$s->execute(['id'=>Auth::user()['id']]);$hash=(string)$s->fetchColumn();if(!password_verify($current,$hash))$this->passwordError('Your current password is incorrect.');if(strlen($password)<12)$this->passwordError('Your new password must contain at least 12 characters.');if($password!==$confirmation)$this->passwordError('The new passwords do not match.');if(password_verify($password,$hash))$this->passwordError('Choose a password different from your current password.');Database::connection()->prepare('UPDATE users SET password_hash=:password WHERE id=:id')->execute(['password'=>password_hash($password,PASSWORD_DEFAULT),'id'=>Auth::user()['id']]);ActivityService::log('auth.password_changed','user',(int)Auth::user()['id'],'Account password changed');$_SESSION['_password_flash']=['type'=>'success','message'=>'Your password was changed successfully.'];header('Location: /account/password');exit;
    }

    private function blocked(string $email): bool{try{$s=Database::connection()->prepare('SELECT COUNT(*) FROM login_attempts WHERE (email=:email OR ip_address=:ip) AND attempted_at>=DATE_SUB(NOW(),INTERVAL 15 MINUTE)');$s->execute(['email'=>strtolower($email),'ip'=>$this->ip()]);return(int)$s->fetchColumn()>=5;}catch(Throwable){return false;}}
    private function recordFailure(string $email): void{try{$db=Database::connection();$db->prepare('INSERT INTO login_attempts (email,ip_address) VALUES (:email,:ip)')->execute(['email'=>substr(strtolower($email),0,190),'ip'=>$this->ip()]);$db->exec('DELETE FROM login_attempts WHERE attempted_at<DATE_SUB(NOW(),INTERVAL 1 DAY)');}catch(Throwable){}}
    private function clearFailures(string $email): void{try{$s=Database::connection()->prepare('DELETE FROM login_attempts WHERE email=:email OR ip_address=:ip');$s->execute(['email'=>strtolower($email),'ip'=>$this->ip()]);}catch(Throwable){}}
    private function ip(): string{return substr((string)($_SERVER['REMOTE_ADDR']??'unknown'),0,45);}
    private function passwordError(string $message): never{$_SESSION['_password_flash']=['type'=>'error','message'=>$message];header('Location: /account/password');exit;}
}
