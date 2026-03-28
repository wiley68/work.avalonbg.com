<?php

declare(strict_types=1);

final class Auth
{
    public static function startSession(): void
    {
        global $config;
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        session_name($config['session_name']);
        $cookiePath = '/';
        global $config;
        $bp = isset($config['base_path']) ? rtrim((string) $config['base_path'], '/') : '';
        if ($bp !== '') {
            $cookiePath = $bp . '/';
        }
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => $cookiePath,
            'httponly' => true,
            'samesite' => 'Lax',
            'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        ]);
        session_start();
    }

    public static function login(string $password): bool
    {
        $pdo = Db::pdo();
        $row = $pdo->query('SELECT password_hash FROM users WHERE id = 1')->fetch();
        $hash = (string) ($row['password_hash'] ?? '');
        if ($hash === '') {
            return false;
        }
        if (!password_verify($password, $hash)) {
            return false;
        }
        self::startSession();
        $_SESSION['ws_auth'] = true;
        session_regenerate_id(true);
        return true;
    }

    public static function logout(): void
    {
        self::startSession();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    public static function check(): bool
    {
        self::startSession();
        return !empty($_SESSION['ws_auth']);
    }

    public static function requireAuth(): void
    {
        if (!self::check()) {
            Api::json(['error' => 'unauthorized'], 401);
        }
    }
}
