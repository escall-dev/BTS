<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\UiSettings;
use App\Models\User;

class AuthController extends Controller
{
    public function showLogin(): void
    {
        $this->view('auth/login', $this->loginPageData());
    }

    public function login(): void
    {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        $user = (new User())->findByUsername($username);

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user'] = [
                'id' => $user['id'],
                'username' => $user['username'],
                'role' => $user['role'],
            ];
            $_SESSION['show_login_loading'] = true;

            $this->redirect('/BTS/dashboard');
        }

        $this->view('auth/login', $this->loginPageData('Invalid username or password.'));
    }

    public function logout(): void
    {
        session_destroy();
        $this->redirect('/BTS/');
    }

    private function loginPageData(string $error = ''): array
    {
        $appConfig = require __DIR__ . '/../../config/app.php';
        $data = [
            'title' => 'Login',
            'appVersion' => (string) ($appConfig['version'] ?? ''),
            'error' => $error,
            'uiSettings' => [],
            'uiTheme' => [],
            'sidebarTitle' => 'BrgyTreasurySystem',
        ];

        try {
            $uiModel = new UiSettings();
            $data['uiSettings'] = $uiModel->all();
            $data['uiTheme'] = $uiModel->theme($data['uiSettings']);
            $data['sidebarTitle'] = trim((string) ($data['uiSettings']['UI_APP_TITLE'] ?? 'BrgyTreasurySystem')) ?: 'BrgyTreasurySystem';
        } catch (\Throwable) {
            // Keep the login page available with its default branding if settings cannot be read.
        }

        return $data;
    }
}
