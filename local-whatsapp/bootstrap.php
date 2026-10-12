<?php

declare(strict_types=1);

ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => getenv('LOCAL_WHATSAPP_COOKIE_PATH') ?: '/cmr/local-whatsapp/',
    'secure' => filter_var(getenv('LOCAL_WHATSAPP_SECURE_COOKIE') ?: false, FILTER_VALIDATE_BOOL)
        || (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    'httponly' => true,
    'samesite' => 'Strict',
]);
session_start();

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; form-action 'self' https://wa.me; base-uri 'none'; frame-ancestors 'none'");

function database(): PDO
{
    static $database;
    if ($database instanceof PDO) {
        return $database;
    }

    $databasePath = getenv('LOCAL_WHATSAPP_DB_PATH') ?: __DIR__.'/data/users.sqlite';
    $databaseDirectory = dirname($databasePath);
    if (!is_dir($databaseDirectory) && !mkdir($databaseDirectory, 0700, true) && !is_dir($databaseDirectory)) {
        throw new RuntimeException('The local data directory could not be created.');
    }
    $database = new PDO('sqlite:'.$databasePath, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $database->exec('PRAGMA foreign_keys = ON');
    $database->exec('PRAGMA journal_mode = WAL');
    $database->exec(
        'CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            username TEXT NOT NULL UNIQUE COLLATE NOCASE,
            password_hash TEXT NOT NULL,
            role TEXT NOT NULL CHECK (role IN (\'admin\', \'sender\')),
            active INTEGER NOT NULL DEFAULT 1 CHECK (active IN (0, 1)),
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )'
    );
    $database->exec(
        'CREATE TABLE IF NOT EXISTS audit_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            event TEXT NOT NULL,
            details TEXT,
            created_at TEXT NOT NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        )'
    );
    $database->exec(
        'CREATE TABLE IF NOT EXISTS settings (
            key TEXT PRIMARY KEY,
            value TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )'
    );

    return $database;
}

function has_users(): bool
{
    return (int) database()->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0;
}

function csrf_token(): string
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(): void
{
    $submitted = (string) ($_POST['csrf_token'] ?? '');
    if ($submitted === '' || !hash_equals(csrf_token(), $submitted)) {
        http_response_code(419);
        exit('Your session expired. Return to the previous page and try again.');
    }
}

function current_user(): ?array
{
    $userId = filter_var($_SESSION['user_id'] ?? null, FILTER_VALIDATE_INT);
    if (!$userId) {
        return null;
    }

    $statement = database()->prepare('SELECT id, name, username, role, active FROM users WHERE id = :id LIMIT 1');
    $statement->execute(['id' => $userId]);
    $user = $statement->fetch();
    if (!$user || (int) $user['active'] !== 1) {
        unset($_SESSION['user_id']);
        return null;
    }

    return $user;
}

function require_login(): array
{
    if (!has_users()) {
        header('Location: setup.php');
        exit;
    }

    $user = current_user();
    if ($user === null) {
        header('Location: login.php');
        exit;
    }

    return $user;
}

function require_admin(): array
{
    $user = require_login();
    if ($user['role'] !== 'admin') {
        http_response_code(403);
        exit('Administrator access is required.');
    }
    return $user;
}

function log_event(?int $userId, string $event, ?string $details = null): void
{
    $statement = database()->prepare(
        'INSERT INTO audit_logs (user_id, event, details, created_at) VALUES (:user_id, :event, :details, :created_at)'
    );
    $statement->execute([
        'user_id' => $userId,
        'event' => $event,
        'details' => $details,
        'created_at' => gmdate('c'),
    ]);
}

function valid_username(string $username): bool
{
    return preg_match('/^[A-Za-z0-9._@-]{3,100}$/', $username) === 1;
}

function auth_e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function local_data_directory(): string
{
    $databasePath = getenv('LOCAL_WHATSAPP_DB_PATH') ?: __DIR__.'/data/users.sqlite';
    return dirname($databasePath);
}

function setting(string $key, string $default = ''): string
{
    $statement = database()->prepare('SELECT value FROM settings WHERE key = :key');
    $statement->execute(['key' => $key]);
    $value = $statement->fetchColumn();
    return $value === false ? $default : (string) $value;
}

function save_settings(array $settings): void
{
    $statement = database()->prepare(
        'INSERT INTO settings (key, value, updated_at) VALUES (:key, :value, :updated_at)
         ON CONFLICT(key) DO UPDATE SET value = excluded.value, updated_at = excluded.updated_at'
    );
    $now = gmdate('c');
    database()->beginTransaction();
    try {
        foreach ($settings as $key => $value) {
            $statement->execute(['key' => $key, 'value' => $value, 'updated_at' => $now]);
        }
        database()->commit();
    } catch (Throwable $exception) {
        database()->rollBack();
        throw $exception;
    }
}

function local_encryption_key(): string
{
    $path = getenv('LOCAL_WHATSAPP_KEY_PATH') ?: __DIR__.'/data/.local-key';
    if (!is_file($path)) {
        $key = random_bytes(32);
        if (file_put_contents($path, $key, LOCK_EX) !== 32) {
            throw new RuntimeException('The local encryption key could not be created.');
        }
    }
    $key = file_get_contents($path);
    if ($key === false || strlen($key) !== 32) {
        throw new RuntimeException('The local encryption key is invalid.');
    }
    return $key;
}

function encrypt_local_secret(string $plainText): string
{
    if ($plainText === '') {
        return '';
    }
    $iv = random_bytes(12);
    $tag = '';
    $encrypted = openssl_encrypt($plainText, 'aes-256-gcm', local_encryption_key(), OPENSSL_RAW_DATA, $iv, $tag);
    if ($encrypted === false) {
        throw new RuntimeException('The SMTP password could not be encrypted.');
    }
    return base64_encode($iv.$tag.$encrypted);
}

function decrypt_local_secret(string $payload): string
{
    if ($payload === '') {
        return '';
    }
    $decoded = base64_decode($payload, true);
    if ($decoded === false || strlen($decoded) < 29) {
        throw new RuntimeException('The stored SMTP password is invalid.');
    }
    $decrypted = openssl_decrypt(substr($decoded, 28), 'aes-256-gcm', local_encryption_key(), OPENSSL_RAW_DATA, substr($decoded, 0, 12), substr($decoded, 12, 16));
    if ($decrypted === false) {
        throw new RuntimeException('The SMTP password could not be decrypted.');
    }
    return $decrypted;
}
