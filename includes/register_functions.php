<?php
require_once __DIR__ . '/db.php';

function createUser(PDO $conn, string $name, string $email, string $password, ?array $avatarFile = null): int
{
    $passwordHash = hash('sha256', $password);
    $avatarUrl = null;

    if ($avatarFile && !empty($avatarFile['tmp_name'])) {
        $targetDir = __DIR__ . '/../uploads/avatars/';
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $filename = uniqid('avatar_', true) . '-' . basename($avatarFile['name']);
        $targetPath = $targetDir . $filename;

        if (move_uploaded_file($avatarFile['tmp_name'], $targetPath)) {
            $avatarUrl = '/uploads/avatars/' . $filename;
        }
    }

    $statement = $conn->prepare(
        'INSERT INTO users (username, email, password_hash, avatar_url, is_active, terms_accepted_at, created_at, updated_at, last_login_at) VALUES (?, ?, ?, ?, 1, NOW(), NOW(), NOW(), NOW())'
    );
    $statement->execute([$name, $email, $passwordHash, $avatarUrl]);

    return (int) $conn->lastInsertId();
}

function getAllOrganizations(PDO $conn): array
{
    $statement = $conn->query('SELECT id, name FROM organizations ORDER BY name ASC');
    return $statement->fetchAll();
}

function validateOrganization(PDO $conn, int $organizationId): bool
{
    $statement = $conn->prepare('SELECT id FROM organizations WHERE id = ? LIMIT 1');
    $statement->execute([$organizationId]);

    return (bool) $statement->fetch();
}

function addUserToOrganization(PDO $conn, int $userId, int $organizationId, string $role): void
{
    $statement = $conn->prepare(
        'INSERT INTO organization_members (organization_id, user_id, role, moderation_status, joined_at, is_active) VALUES (?, ?, ?, "active", NOW(), 1)'
    );
    $statement->execute([$organizationId, $userId, $role]);
}
