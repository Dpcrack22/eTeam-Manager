<?php
require_once __DIR__ . '/db.php';

function ensureUserSecurityStorage(PDO $conn): void
{
    $columns = [
        'bio' => 'ALTER TABLE users ADD COLUMN bio TEXT NULL AFTER avatar_url',
        'email_verified_at' => 'ALTER TABLE users ADD COLUMN email_verified_at DATETIME NULL DEFAULT NULL AFTER terms_accepted_at',
        'email_verification_token' => 'ALTER TABLE users ADD COLUMN email_verification_token VARCHAR(128) NULL AFTER email_verified_at',
        'email_verification_sent_at' => 'ALTER TABLE users ADD COLUMN email_verification_sent_at DATETIME NULL DEFAULT NULL AFTER email_verification_token',
        'password_reset_token' => 'ALTER TABLE users ADD COLUMN password_reset_token VARCHAR(128) NULL AFTER email_verification_sent_at',
        'password_reset_sent_at' => 'ALTER TABLE users ADD COLUMN password_reset_sent_at DATETIME NULL DEFAULT NULL AFTER password_reset_token',
        'password_reset_expires_at' => 'ALTER TABLE users ADD COLUMN password_reset_expires_at DATETIME NULL DEFAULT NULL AFTER password_reset_sent_at',
        'login_code_token' => 'ALTER TABLE users ADD COLUMN login_code_token VARCHAR(64) NULL AFTER password_reset_expires_at',
        'login_code_sent_at' => 'ALTER TABLE users ADD COLUMN login_code_sent_at DATETIME NULL DEFAULT NULL AFTER login_code_token',
        'login_code_expires_at' => 'ALTER TABLE users ADD COLUMN login_code_expires_at DATETIME NULL DEFAULT NULL AFTER login_code_sent_at',
    ];

    foreach ($columns as $columnName => $alterSql) {
        $statement = $conn->prepare('SELECT COUNT(*) AS total FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = "users" AND column_name = :column_name');
        $statement->bindValue(':column_name', $columnName, PDO::PARAM_STR);
        $statement->execute();
        $exists = (int) ($statement->fetch()['total'] ?? 0) > 0;

        if (!$exists) {
            $conn->exec($alterSql);
        }
    }

    $indexes = [
        'uq_users_email_verification_token' => 'ALTER TABLE users ADD UNIQUE KEY uq_users_email_verification_token (email_verification_token)',
        'uq_users_password_reset_token' => 'ALTER TABLE users ADD UNIQUE KEY uq_users_password_reset_token (password_reset_token)',
    ];

    foreach ($indexes as $indexName => $alterSql) {
        $statement = $conn->prepare('SELECT COUNT(*) AS total FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = "users" AND index_name = :index_name');
        $statement->bindValue(':index_name', $indexName, PDO::PARAM_STR);
        $statement->execute();
        $exists = (int) ($statement->fetch()['total'] ?? 0) > 0;

        if (!$exists) {
            $conn->exec($alterSql);
        }
    }
}

function generateSecurityToken(int $bytes = 24): string
{
    return bin2hex(random_bytes(max(16, $bytes)));
}

function applicationBaseUrl(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $scriptName = (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php');
    $basePath = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');

    return $scheme . '://' . $host . ($basePath !== '' ? $basePath : '');
}

function absoluteAppUrl(string $path): string
{
    return rtrim(applicationBaseUrl(), '/') . '/' . ltrim($path, '/');
}

function sendMailMessage(string $to, string $subject, string $message): bool
{
    if ($to === '') {
        return false;
    }

    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/plain; charset=UTF-8',
        'From: eTeam Manager <no-reply@localhost>',
    ];

    return @mail($to, $subject, $message, implode("\r\n", $headers));
}

function sendVerificationEmail(array $user, string $token): bool
{
    $email = (string) ($user['email'] ?? '');
    if ($email === '') {
        return false;
    }

    $verifyLink = absoluteAppUrl('verify-email.php?token=' . urlencode($token));
    $subject = 'Verifica tu correo en eTeam Manager';
    $message = "Hola {$user['username']},\n\n";
    $message .= 'Ya casi tienes tu cuenta lista. Verifica tu correo con este enlace:' . "\n";
    $message .= $verifyLink . "\n\n";
    $message .= 'Si no has pedido esta cuenta, puedes ignorar este mensaje.' . "\n\n";
    $message .= "-- eTeam Manager\n";

    return sendMailMessage($email, $subject, $message);
}

function sendPasswordResetEmail(array $user, string $token): bool
{
    $email = (string) ($user['email'] ?? '');
    if ($email === '') {
        return false;
    }

    $resetLink = absoluteAppUrl('reset-password.php?token=' . urlencode($token));
    $subject = 'Restablece tu contraseña en eTeam Manager';
    $message = "Hola {$user['username']},\n\n";
    $message .= 'Puedes crear una contraseña nueva desde este enlace:' . "\n";
    $message .= $resetLink . "\n\n";
    $message .= 'El enlace caduca en 60 minutos.' . "\n\n";
    $message .= "-- eTeam Manager\n";

    return sendMailMessage($email, $subject, $message);
}

function sendLoginCodeEmail(array $user, string $code): bool
{
    $email = (string) ($user['email'] ?? '');
    if ($email === '') {
        return false;
    }

    $loginLink = absoluteAppUrl('login-code.php');
    $subject = 'Tu código temporal para eTeam Manager';
    $message = "Hola {$user['username']},\n\n";
    $message .= 'Tu código temporal de acceso es:' . "\n";
    $message .= $code . "\n\n";
    $message .= 'Caduca en 15 minutos y solo puede usarse una vez.' . "\n";
    $message .= 'Puedes completar el acceso desde este enlace:' . "\n";
    $message .= $loginLink . "\n\n";
    $message .= 'Si no has solicitado este código, ignora este mensaje.' . "\n\n";
    $message .= "-- eTeam Manager\n";

    return sendMailMessage($email, $subject, $message);
}

function issueEmailVerificationToken(PDO $conn, int $userId): string
{
    $token = generateSecurityToken(24);
    $statement = $conn->prepare(
        'UPDATE users SET email_verification_token = :token, email_verification_sent_at = NOW() WHERE id = :user_id LIMIT 1'
    );
    $statement->bindValue(':token', $token, PDO::PARAM_STR);
    $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $statement->execute();

    return $token;
}

function issuePasswordResetToken(PDO $conn, int $userId): string
{
    $token = generateSecurityToken(24);
    $statement = $conn->prepare(
        'UPDATE users SET password_reset_token = :token, password_reset_sent_at = NOW(), password_reset_expires_at = DATE_ADD(NOW(), INTERVAL 60 MINUTE) WHERE id = :user_id LIMIT 1'
    );
    $statement->bindValue(':token', $token, PDO::PARAM_STR);
    $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $statement->execute();

    return $token;
}

function issueLoginCodeToken(PDO $conn, int $userId): string
{
    $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $codeHash = hash('sha256', $code);
    $statement = $conn->prepare(
        'UPDATE users SET login_code_token = :token, login_code_sent_at = NOW(), login_code_expires_at = DATE_ADD(NOW(), INTERVAL 15 MINUTE) WHERE id = :user_id LIMIT 1'
    );
    $statement->bindValue(':token', $codeHash, PDO::PARAM_STR);
    $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $statement->execute();

    return $code;
}

function findUserByEmailToken(PDO $conn, string $token): array|false
{
    $statement = $conn->prepare('SELECT id, username, email, email_verification_token, email_verified_at FROM users WHERE email_verification_token = :token LIMIT 1');
    $statement->bindValue(':token', $token, PDO::PARAM_STR);
    $statement->execute();

    return $statement->fetch() ?: false;
}

function findUserByPasswordResetToken(PDO $conn, string $token): array|false
{
    $statement = $conn->prepare('SELECT id, username, email, password_reset_token, password_reset_expires_at FROM users WHERE password_reset_token = :token LIMIT 1');
    $statement->bindValue(':token', $token, PDO::PARAM_STR);
    $statement->execute();

    return $statement->fetch() ?: false;
}

function markUserEmailVerified(PDO $conn, int $userId): void
{
    $statement = $conn->prepare(
        'UPDATE users SET email_verified_at = NOW(), email_verification_token = NULL, email_verification_sent_at = NULL WHERE id = :user_id LIMIT 1'
    );
    $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $statement->execute();
}

function resetUserPassword(PDO $conn, int $userId, string $passwordHash): void
{
    $statement = $conn->prepare(
        'UPDATE users SET password_hash = :password_hash, password_reset_token = NULL, password_reset_sent_at = NULL, password_reset_expires_at = NULL, updated_at = NOW() WHERE id = :user_id LIMIT 1'
    );
    $statement->bindValue(':password_hash', $passwordHash, PDO::PARAM_STR);
    $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $statement->execute();
}

function clearLoginCodeToken(PDO $conn, int $userId): void
{
    $statement = $conn->prepare(
        'UPDATE users SET login_code_token = NULL, login_code_sent_at = NULL, login_code_expires_at = NULL WHERE id = :user_id LIMIT 1'
    );
    $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $statement->execute();
}

function requestVerificationEmail(PDO $conn, string $email): array
{
    $statement = $conn->prepare('SELECT id, username, email, email_verified_at FROM users WHERE email = :email LIMIT 1');
    $statement->bindValue(':email', $email, PDO::PARAM_STR);
    $statement->execute();
    $user = $statement->fetch();

    if (!$user) {
        return ['success' => false, 'error' => 'No existe ninguna cuenta con ese correo'];
    }

    if (!empty($user['email_verified_at'])) {
        return ['success' => true, 'already_verified' => true, 'message' => 'Ese correo ya está verificado'];
    }

    $token = issueEmailVerificationToken($conn, (int) $user['id']);
    sendVerificationEmail($user, $token);

    return ['success' => true, 'message' => 'Hemos enviado un correo de verificación'];
}

function requestPasswordReset(PDO $conn, string $email): array
{
    $statement = $conn->prepare('SELECT id, username, email FROM users WHERE email = :email AND is_active = 1 LIMIT 1');
    $statement->bindValue(':email', $email, PDO::PARAM_STR);
    $statement->execute();
    $user = $statement->fetch();

    if (!$user) {
        return ['success' => true, 'message' => 'Si el correo existe, recibirás un enlace para restablecer la contraseña'];
    }

    $token = issuePasswordResetToken($conn, (int) $user['id']);
    sendPasswordResetEmail($user, $token);

    return ['success' => true, 'message' => 'Si el correo existe, recibirás un enlace para restablecer la contraseña'];
}

function requestLoginCode(PDO $conn, string $email): array
{
    $statement = $conn->prepare(
        'SELECT id, username, email, is_active, terms_accepted_at, email_verified_at FROM users WHERE email = :email LIMIT 1'
    );
    $statement->bindValue(':email', $email, PDO::PARAM_STR);
    $statement->execute();
    $user = $statement->fetch();

    if (!$user || !(int) $user['is_active'] || empty($user['terms_accepted_at']) || empty($user['email_verified_at'])) {
        return ['success' => true, 'message' => 'Si el correo existe y está verificado, recibirás un código temporal'];
    }

    $code = issueLoginCodeToken($conn, (int) $user['id']);
    sendLoginCodeEmail($user, $code);

    return ['success' => true, 'message' => 'Si el correo existe y está verificado, recibirás un código temporal'];
}

function fetchUserForLoginCode(PDO $conn, string $email): array|false
{
    $statement = $conn->prepare(
        'SELECT u.id, u.username, u.email, u.password_hash, u.avatar_url, u.bio, u.is_active, u.terms_accepted_at, u.email_verified_at, u.login_code_token, u.login_code_expires_at, om.role AS organization_role, o.id AS organization_id, o.name AS organization_name
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

function verifyLoginCodeForUser(array $user, string $code): array
{
    $errors = [];

    if (empty($user['is_active'])) {
        $errors['general'] = 'La cuenta está desactivada.';
    } elseif (empty($user['email_verified_at'])) {
        $errors['general'] = 'Debes verificar tu correo antes de entrar.';
    } elseif (empty($user['terms_accepted_at'])) {
        $errors['general'] = 'Debes aceptar la normativa antes de entrar.';
    } elseif (empty($user['login_code_token']) || empty($user['login_code_expires_at'])) {
        $errors['code'] = 'Primero debes solicitar un código temporal.';
    } else {
        $expiresAt = strtotime((string) $user['login_code_expires_at']) ?: 0;
        $submittedHash = hash('sha256', trim($code));

        if ($expiresAt <= time()) {
            $errors['code'] = 'El código temporal ha caducado.';
        } elseif (!hash_equals((string) $user['login_code_token'], $submittedHash)) {
            $errors['code'] = 'El código temporal no es válido.';
        }
    }

    if (!empty($errors)) {
        return ['success' => false, 'errors' => $errors];
    }

    return ['success' => true];
}