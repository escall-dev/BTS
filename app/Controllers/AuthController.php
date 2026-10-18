<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\UiSettings;
use App\Models\User;
use App\Models\LoginAttempt;

class AuthController extends Controller
{
    public function showLogin(): void
    {
        $this->view('auth/login', $this->loginPageData());
    }

    public function loginLockoutStatus(): void
    {
        $rawUsername = $_POST['username'] ?? '';
        $username = is_string($rawUsername) ? trim($rawUsername) : '';

        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store, private');

        if (!$this->isValidUsername($username)) {
            http_response_code(400);
            echo json_encode(['retryAfterSeconds' => 0]);
            return;
        }

        try {
            $seconds = (new LoginAttempt())->lockoutSeconds($username);
            echo json_encode(['retryAfterSeconds' => $seconds]);
        } catch (\Throwable) {
            http_response_code(503);
            echo json_encode(['retryAfterSeconds' => null]);
        }
    }

    public function login(): void
    {
        $rawUsername = $_POST['username'] ?? '';
        $rawPassword = $_POST['password'] ?? '';
        $username = is_string($rawUsername) ? trim($rawUsername) : '';
        $password = is_string($rawPassword) ? $rawPassword : '';
        $attempts = new LoginAttempt();

        $lockoutSeconds = $attempts->lockoutSeconds($username);
        if ($lockoutSeconds > 0) {
            $this->view('auth/login', $this->loginPageData('', $username, $lockoutSeconds));
            return;
        }

        $validInput = $this->isValidUsername($username)
            && strlen($password) <= 4096;
        $user = $validInput ? (new User())->findByUsername($username) : null;

        if ($user && $password !== '' && password_verify($password, $user['password'])) {
            $attempts->clear($username);
            session_regenerate_id(true);
            $_SESSION['user'] = [
                'id' => $user['id'],
                'username' => $user['username'],
                'role' => $user['role'],
            ];
            $_SESSION['show_login_loading'] = true;

            $this->redirect('/BTS/dashboard');
        }

        $lockoutSeconds = $attempts->recordFailure($username);
        $message = $lockoutSeconds > 0
            ? $this->lockoutMessage($lockoutSeconds)
            : 'Invalid username or password.';
        $this->view('auth/login', $this->loginPageData($lockoutSeconds > 0 ? '' : $message, $username, $lockoutSeconds));
    }

    private function isValidUsername(string $username): bool
    {
        return strlen($username) <= 64
            && preg_match('/\A[a-zA-Z0-9_.@-]{1,64}\z/D', $username) === 1;
    }

    private function lockoutMessage(int $seconds): string
    {
        return 'Too many failed attempts. Please wait '
            . intdiv($seconds, 60) . ':' . str_pad((string) ($seconds % 60), 2, '0', STR_PAD_LEFT)
            . ' before trying again.';
    }

    public function logout(): void
    {
        session_destroy();
        $this->redirect('/BTS/');
    }

    private function loginPageData(string $error = '', string $username = '', int $lockoutSeconds = 0): array
    {
        $appConfig = require __DIR__ . '/../../config/app.php';
        $data = [
            'title' => 'Login',
            'appVersion' => (string) ($appConfig['version'] ?? ''),
            'error' => $error,
            'username' => $username,
            'lockoutSeconds' => $lockoutSeconds,
            'lockoutMessage' => $lockoutSeconds > 0 ? $this->lockoutMessage($lockoutSeconds) : '',
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
