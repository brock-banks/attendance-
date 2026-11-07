<?php
class Auth
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public static function attempt(string $username, string $password): bool
    {
        $row = Database::q("SELECT * FROM admins WHERE username = ?", [$username])->fetch();
        if ($row && password_verify($password, $row['password_hash'])) {
            self::start();
            session_regenerate_id(true);
            $_SESSION['admin_id']   = (int)$row['id'];
            $_SESSION['admin_user'] = $row['username'];
            return true;
        }
        return false;
    }

    public static function check(): bool
    {
        self::start();
        return !empty($_SESSION['admin_id']);
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            header('Location: ' . APP_BASE_URL . '/admin/login.php');
            exit;
        }
    }

    public static function logout(): void
    {
        self::start();
        $_SESSION = [];
        session_destroy();
    }

    public static function user(): ?string
    {
        self::start();
        return $_SESSION['admin_user'] ?? null;
    }
}
