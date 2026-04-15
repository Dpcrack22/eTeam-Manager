<?php
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!defined('ETEAM_REMEMBER_ME_COOKIE')) {
    define('ETEAM_REMEMBER_ME_COOKIE', 'eteam_remember_me');
}

if (!defined('ETEAM_REMEMBER_ME_LIFETIME')) {
    define('ETEAM_REMEMBER_ME_LIFETIME', 60 * 60 * 24 * 30);
}

function rememberMeCookieOptions(int $expires = 0): array
{
    $params = session_get_cookie_params();

    return [
        'expires' => $expires,
        'path' => $params['path'] ?: '/',
        'domain' => $params['domain'] ?: '',
        'secure' => !empty($params['secure']),
        'httponly' => true,
        'samesite' => 'Lax',
    ];
}

function base64UrlEncode(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function base64UrlDecode(string $value): string
{
    $paddingLength = strlen($value) % 4;
    if ($paddingLength > 0) {
        $value .= str_repeat('=', 4 - $paddingLength);
    }

    return (string) base64_decode(strtr($value, '-_', '+/'), true);
}

function ensureRememberMeStorage(PDO $conn): void
{
    try {
        $conn->exec(
            'CREATE TABLE IF NOT EXISTS remember_tokens (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id BIGINT UNSIGNED NOT NULL,
                selector VARCHAR(64) NOT NULL,
                token_hash VARCHAR(64) NOT NULL,
                expires_at DATETIME NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                last_used_at TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_remember_tokens_selector (selector),
                KEY idx_remember_tokens_user_id (user_id),
                KEY idx_remember_tokens_expires_at (expires_at),
                CONSTRAINT fk_remember_tokens_user
                    FOREIGN KEY (user_id) REFERENCES users (id)
                    ON UPDATE CASCADE
                    ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    } catch (Throwable $exception) {
        // If the storage cannot be created, the app still works without remember-me.
    }
}

function fetchAuthenticatedUserByEmail(PDO $conn, string $email): array|false
{
    $statement = $conn->prepare(
        'SELECT u.id, u.username, u.email, u.password_hash, u.avatar_url, u.is_active, u.terms_accepted_at, om.role AS organization_role, o.id AS organization_id, o.name AS organization_name
         FROM users u
         LEFT JOIN organization_members om ON om.user_id = u.id AND om.is_active = 1 AND COALESCE(om.moderation_status, "active") = "active"
         LEFT JOIN organizations o ON o.id = om.organization_id
         WHERE u.email = :email
         ORDER BY om.joined_at DESC, o.id DESC
         LIMIT 1'
    );
    $statement->bindValue(':email', $email, PDO::PARAM_STR);
    $statement->execute();

    return $statement->fetch() ?: false;
}

function fetchAuthenticatedUserById(PDO $conn, int $userId): array|false
{
    $statement = $conn->prepare(
        'SELECT u.id, u.username, u.email, u.password_hash, u.avatar_url, u.is_active, u.terms_accepted_at, om.role AS organization_role, o.id AS organization_id, o.name AS organization_name
         FROM users u
         LEFT JOIN organization_members om ON om.user_id = u.id AND om.is_active = 1 AND COALESCE(om.moderation_status, "active") = "active"
         LEFT JOIN organizations o ON o.id = om.organization_id
         WHERE u.id = :user_id
         ORDER BY om.joined_at DESC, o.id DESC
         LIMIT 1'
    );
    $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $statement->execute();

    return $statement->fetch() ?: false;
}

function populateAuthenticatedSession(array $user): void
{
    $_SESSION['user'] = [
        'id' => (int) $user['id'],
        'email' => $user['email'],
        'name' => $user['username'],
        'avatar_url' => $user['avatar_url'],
        'role' => $user['organization_role'] ?? 'Member',
        'organization' => $user['organization_name'] ?? 'Sin organización',
        'organization_id' => isset($user['organization_id']) ? (int) $user['organization_id'] : null,
        'terms_accepted_at' => $user['terms_accepted_at'] ?? null,
    ];
}

function deleteRememberMeToken(PDO $conn, ?string $cookieValue = null): void
{
    try {
        $cookieValue = $cookieValue ?? (string) ($_COOKIE[ETEAM_REMEMBER_ME_COOKIE] ?? '');

        if ($cookieValue === '' || strpos($cookieValue, '.') === false) {
            return;
        }

        [$selector] = explode('.', $cookieValue, 2);
        $selector = trim((string) $selector);

        if ($selector === '') {
            return;
        }

        $statement = $conn->prepare('DELETE FROM remember_tokens WHERE selector = :selector');
        $statement->bindValue(':selector', $selector, PDO::PARAM_STR);
        $statement->execute();
    } catch (Throwable $exception) {
        // Ignore remember-me cleanup failures.
    }
}

function clearRememberMeCookie(): void
{
    setcookie(ETEAM_REMEMBER_ME_COOKIE, '', rememberMeCookieOptions(time() - 3600));
    unset($_COOKIE[ETEAM_REMEMBER_ME_COOKIE]);
}

function issueRememberMeToken(PDO $conn, int $userId): void
{
    try {
        $selector = base64UrlEncode(random_bytes(12));
        $validator = random_bytes(32);
        $cookieValue = $selector . '.' . base64UrlEncode($validator);
        $expiresAt = (new DateTimeImmutable('now'))->modify('+' . ETEAM_REMEMBER_ME_LIFETIME . ' seconds')->format('Y-m-d H:i:s');

        $statement = $conn->prepare(
            'INSERT INTO remember_tokens (user_id, selector, token_hash, expires_at, created_at, last_used_at)
             VALUES (:user_id, :selector, :token_hash, :expires_at, NOW(), NOW())'
        );
        $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $statement->bindValue(':selector', $selector, PDO::PARAM_STR);
        $statement->bindValue(':token_hash', hash('sha256', $validator), PDO::PARAM_STR);
        $statement->bindValue(':expires_at', $expiresAt, PDO::PARAM_STR);
        $statement->execute();

        setcookie(ETEAM_REMEMBER_ME_COOKIE, $cookieValue, rememberMeCookieOptions(time() + ETEAM_REMEMBER_ME_LIFETIME));
        $_COOKIE[ETEAM_REMEMBER_ME_COOKIE] = $cookieValue;
    } catch (Throwable $exception) {
        clearRememberMeCookie();
    }
}

function restoreRememberedSession(): bool
{
    global $conn;

    try {
        if (!empty($_SESSION['user'])) {
            return true;
        }

        $cookieValue = (string) ($_COOKIE[ETEAM_REMEMBER_ME_COOKIE] ?? '');
        if ($cookieValue === '' || strpos($cookieValue, '.') === false) {
            return false;
        }

        [$selector, $validator] = explode('.', $cookieValue, 2);
        $selector = trim((string) $selector);
        $validator = trim((string) $validator);

        if ($selector === '' || $validator === '') {
            clearRememberMeCookie();
            return false;
        }

        $statement = $conn->prepare('SELECT id, user_id, token_hash, expires_at FROM remember_tokens WHERE selector = :selector LIMIT 1');
        $statement->bindValue(':selector', $selector, PDO::PARAM_STR);
        $statement->execute();
        $tokenRow = $statement->fetch();

        if (!$tokenRow) {
            clearRememberMeCookie();
            return false;
        }

        $expiresAt = strtotime((string) $tokenRow['expires_at']) ?: 0;
        if ($expiresAt <= time()) {
            deleteRememberMeToken($conn, $cookieValue);
            clearRememberMeCookie();
            return false;
        }

        $decodedValidator = base64UrlDecode($validator);
        if ($decodedValidator === '' || !hash_equals((string) $tokenRow['token_hash'], hash('sha256', $decodedValidator))) {
            deleteRememberMeToken($conn, $cookieValue);
            clearRememberMeCookie();
            return false;
        }

        $user = fetchAuthenticatedUserById($conn, (int) $tokenRow['user_id']);
        if (!$user || !(int) $user['is_active']) {
            deleteRememberMeToken($conn, $cookieValue);
            clearRememberMeCookie();
            return false;
        }

        session_regenerate_id(true);
        populateAuthenticatedSession($user);

        $refreshExpiresAt = (new DateTimeImmutable('now'))->modify('+' . ETEAM_REMEMBER_ME_LIFETIME . ' seconds')->format('Y-m-d H:i:s');
        $refreshStatement = $conn->prepare('UPDATE remember_tokens SET last_used_at = NOW(), expires_at = :expires_at WHERE id = :id');
        $refreshStatement->bindValue(':expires_at', $refreshExpiresAt, PDO::PARAM_STR);
        $refreshStatement->bindValue(':id', (int) $tokenRow['id'], PDO::PARAM_INT);
        $refreshStatement->execute();

        setcookie(ETEAM_REMEMBER_ME_COOKIE, $cookieValue, rememberMeCookieOptions(time() + ETEAM_REMEMBER_ME_LIFETIME));
        $_COOKIE[ETEAM_REMEMBER_ME_COOKIE] = $cookieValue;

        return true;
    } catch (Throwable $exception) {
        clearRememberMeCookie();
        return false;
    }
}

ensureRememberMeStorage($conn);

function login($email, $password, bool $rememberMe = false)
{
    global $conn;

    try {
        $user = fetchAuthenticatedUserByEmail($conn, $email);

        if (!$user) {
            return [
                'success' => false,
                'error' => 'Correu electrònic no registrat',
                'errors' => ['email' => 'No existeix cap compte amb aquest correu electrònic'],
            ];
        }

        if (!(int) $user['is_active']) {
            return [
                'success' => false,
                'error' => 'Compte pendent de validació',
                'errors' => ['general' => 'La teva compte està pendent de validació.'],
            ];
        }

        if (empty($user['terms_accepted_at'])) {
            return [
                'success' => false,
                'error' => 'Normativa no aceptada',
                'errors' => ['general' => 'Debes aceptar la normativa de acceso antes de entrar.'],
            ];
        }

        if (hash('sha256', $password) !== $user['password_hash']) {
            return [
                'success' => false,
                'error' => 'Contrasenya incorrecta',
                'errors' => ['password' => 'La contrasenya és incorrecta'],
            ];
        }

        $updateStatement = $conn->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id LIMIT 1');
        $updateStatement->bindValue(':id', (int) $user['id'], PDO::PARAM_INT);
        $updateStatement->execute();

        session_regenerate_id(true);
        populateAuthenticatedSession($user);

        if ($rememberMe) {
            issueRememberMeToken($conn, (int) $user['id']);
        } else {
            deleteRememberMeToken($conn);
            clearRememberMeCookie();
        }

        return ['success' => true];
    } catch (PDOException $exception) {
        return [
            'success' => false,
            'error' => 'Error de base de dades',
            'errors' => ['general' => 'Hi ha hagut un error en accedir a la base de dades'],
        ];
    }
}

function isLogged(): bool
{
    if (!empty($_SESSION['user'])) {
        return true;
    }

    return restoreRememberedSession();
}

function logout(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    global $conn;

    if (isset($conn)) {
        deleteRememberMeToken($conn);
    }

    clearRememberMeCookie();

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}

function requireAuth(): void
{
    if (!isLogged()) {
        header('Location: app.php?view=login&cb=1');
        exit;
    }
}

function redirectIfAuthenticated(): void
{
    if (isLogged()) {
        header('Location: app.php?view=dashboard');
        exit;
    }
}

function safeReturnToTarget(?string $returnTo, string $fallback = 'app.php?view=dashboard'): string
{
    $returnTo = trim((string) $returnTo);

    if ($returnTo === '') {
        return $fallback;
    }

    if (
        str_starts_with($returnTo, 'app.php?view=') ||
        str_starts_with($returnTo, 'invite.php?token=') ||
        $returnTo === 'login.php' ||
        $returnTo === 'register.php' ||
        $returnTo === 'index.php' ||
        $returnTo === 'admin.php'
    ) {
        return $returnTo;
    }

    return $fallback;
}
