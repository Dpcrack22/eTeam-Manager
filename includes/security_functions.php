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