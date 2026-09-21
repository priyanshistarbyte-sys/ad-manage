<?php

/**
 * ad-manage global helpers — PIN authentication ported verbatim from the
 * Amaira / revenue_laravel app. Kept as global functions (autoloaded via
 * composer "files") so the ported controllers, middleware and Blade views can
 * call them exactly as the original did.
 *
 * Auth state lives in the Laravel session; data access goes through the
 * framework's PDO connection so the original SQL ports over verbatim.
 */

use Illuminate\Support\Facades\DB;

if (!defined('AUTH_SESSION_TTL')) {
    define('AUTH_SESSION_TTL', (int) env('AUTH_SESSION_TTL', 3 * 3600)); // 3 hours
}

/**
 * The underlying PDO handle, configured to match the original app
 * (associative fetch, exceptions on error).
 */
function getDB(): PDO
{
    static $configured = false;
    $pdo = DB::connection()->getPdo();
    if (!$configured) {
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $configured = true;
    }
    return $pdo;
}

// ── Settings (key/value) ────────────────────────────────────────────
function getSetting(string $key, string $default = ''): string
{
    try {
        $stmt = getDB()->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        return $row && $row['setting_value'] !== null ? $row['setting_value'] : $default;
    } catch (\Throwable $e) {
        return $default;
    }
}

function setSetting(string $key, ?string $value): void
{
    getDB()->prepare(
        "INSERT INTO settings (setting_key, setting_value, created_at, updated_at)
         VALUES (?, ?, NOW(), NOW())
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()"
    )->execute([$key, $value]);
}

// ── Users / PIN auth ────────────────────────────────────────────────
function hasUsers(): bool
{
    try {
        return (int) getDB()->query("SELECT COUNT(*) FROM users")->fetchColumn() > 0;
    } catch (\Throwable $e) {
        return false;
    }
}

/** Find the (active) user whose PIN matches. */
function verifyUserPin(string $pin): ?array
{
    try {
        $users = getDB()->query("SELECT * FROM users WHERE active = 1")->fetchAll();
    } catch (\Throwable $e) {
        return null;
    }
    foreach ($users as $u) {
        if (password_verify($pin, $u['pin_hash'])) return $u;
    }
    return null;
}

/** True if any user (active or not) already uses this PIN, excluding $excludeId. */
function pinInUse(string $pin, int $excludeId = 0): bool
{
    try {
        $users = getDB()->query("SELECT id, pin_hash FROM users")->fetchAll();
    } catch (\Throwable $e) {
        return false;
    }
    foreach ($users as $u) {
        if ((int) $u['id'] !== $excludeId && password_verify($pin, $u['pin_hash'])) return true;
    }
    return false;
}

function userById(int $id): ?array
{
    try {
        $stmt = getDB()->prepare("SELECT * FROM users WHERE id = ? AND active = 1");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    } catch (\Throwable $e) {
        return null;
    }
}

function hasSecondPin(array $u): bool
{
    return !empty($u['pin2_hash'] ?? null);
}

function verifySecondPin(array $u, string $pin): bool
{
    return hasSecondPin($u) && password_verify($pin, $u['pin2_hash']);
}

/** True when this user must clear a second PIN before the session is authenticated. */
function needsSecondPin(array $u): bool
{
    return !empty($u['is_admin']) && hasSecondPin($u);
}

function setSecondPin(int $userId, ?string $pin): void
{
    getDB()->prepare("UPDATE users SET pin2_hash = ? WHERE id = ?")
           ->execute([$pin === null ? null : password_hash($pin, PASSWORD_BCRYPT), $userId]);
}

function createUser(string $name, string $pin, bool $isAdmin = false): int
{
    $pdo = getDB();
    $pdo->prepare("INSERT INTO users (name, pin_hash, is_admin, active, created_at, updated_at) VALUES (?,?,?,1,NOW(),NOW())")
        ->execute([$name, password_hash($pin, PASSWORD_BCRYPT), $isAdmin ? 1 : 0]);
    return (int) $pdo->lastInsertId();
}

function allUsers(bool $activeOnly = false): array
{
    try {
        $sql = "SELECT id, name, is_admin, active, created_at FROM users"
             . ($activeOnly ? " WHERE active = 1" : "")
             . " ORDER BY is_admin DESC, name ASC";
        return getDB()->query($sql)->fetchAll();
    } catch (\Throwable $e) {
        return [];
    }
}

// ── Current session user ────────────────────────────────────────────
function currentUserId(): int      { return (int) session('user_id', 0); }
function currentUserName(): string { return (string) session('user_name', ''); }
function isAdmin(): bool            { return (bool) session('is_admin', false); }

// ── Session auth lifecycle ──────────────────────────────────────────
function isAuthenticated(): bool
{
    if (!session('auth_ok') || !session('auth_time') || !session('user_id')) return false;
    if (time() - (int) session('auth_time') > AUTH_SESSION_TTL) {
        authLogout();
        return false;
    }
    return true;
}

function authLogin(array $user): void
{
    session([
        'auth_ok'   => true,
        'auth_time' => time(),
        'user_id'   => (int) $user['id'],
        'user_name' => $user['name'],
        'is_admin'  => (int) $user['is_admin'] === 1,
    ]);
}

function authLogout(): void
{
    session()->forget(['auth_ok', 'auth_time', 'user_id', 'user_name', 'is_admin']);
}

/** Unix timestamp when the current session's PIN auth expires, or null. */
function authExpiresAt(): ?int
{
    if (!session('auth_time')) return null;
    return (int) session('auth_time') + AUTH_SESSION_TTL;
}
