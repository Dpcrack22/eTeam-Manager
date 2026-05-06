<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/notification_functions.php';

function getUserOrganizations(PDO $conn, int $userId): array
{
    $statement = $conn->prepare(
        'SELECT o.id, o.name, o.slug, o.description, o.logo_url, o.owner_id, om.role AS member_role, om.joined_at
         FROM organization_members om
         INNER JOIN organizations o ON o.id = om.organization_id
         WHERE om.user_id = :user_id AND om.is_active = 1 AND COALESCE(om.moderation_status, "active") = "active"
         ORDER BY om.joined_at DESC, o.id DESC'
    );
    $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $statement->execute();

    return $statement->fetchAll();
}

function getOrganizationById(PDO $conn, int $organizationId, int $userId = 0): array|false
{
    $sql = 'SELECT o.id, o.name, o.slug, o.description, o.logo_url, o.owner_id, o.created_at, o.updated_at
            FROM organizations o';

    if ($userId > 0) {
    $sql .= ' INNER JOIN organization_members om ON om.organization_id = o.id AND om.user_id = :user_id AND om.is_active = 1 AND COALESCE(om.moderation_status, "active") = "active"';
    }

    $sql .= ' WHERE o.id = :organization_id LIMIT 1';

    $statement = $conn->prepare($sql);
    $statement->bindValue(':organization_id', $organizationId, PDO::PARAM_INT);

    if ($userId > 0) {
        $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
    }

    $statement->execute();

    return $statement->fetch() ?: false;
}

function getOrganizationMembers(PDO $conn, int $organizationId): array
{
    $statement = $conn->prepare(
        'SELECT u.id AS user_id, u.username, u.email, u.avatar_url, om.role, om.joined_at, om.is_active, COALESCE(om.moderation_status, "active") AS moderation_status, om.moderation_reason, om.moderation_until, om.moderated_at, m.username AS moderated_by_name
         FROM organization_members om
         INNER JOIN users u ON u.id = om.user_id
         LEFT JOIN users m ON m.id = om.moderated_by
         WHERE om.organization_id = :organization_id
         ORDER BY FIELD(om.role, "owner", "admin", "manager", "coach", "analyst", "player", "viewer"), u.username ASC'
    );
    $statement->bindValue(':organization_id', $organizationId, PDO::PARAM_INT);
    $statement->execute();

    return $statement->fetchAll();
}

function getOrganizationStats(PDO $conn, int $organizationId): array
{
    $memberCountStatement = $conn->prepare('SELECT COUNT(*) AS total FROM organization_members WHERE organization_id = :organization_id AND is_active = 1 AND COALESCE(moderation_status, "active") = "active"');
    $memberCountStatement->bindValue(':organization_id', $organizationId, PDO::PARAM_INT);
    $memberCountStatement->execute();

    $teamCountStatement = $conn->prepare('SELECT COUNT(*) AS total FROM teams WHERE organization_id = :organization_id AND is_active = 1');
    $teamCountStatement->bindValue(':organization_id', $organizationId, PDO::PARAM_INT);
    $teamCountStatement->execute();

    return [
        'members' => (int) ($memberCountStatement->fetch()['total'] ?? 0),
        'teams' => (int) ($teamCountStatement->fetch()['total'] ?? 0),
    ];
}

function createOrganization(PDO $conn, int $ownerId, string $name, string $slug, string $description, ?string $logoUrl = null): int
{
    $statement = $conn->prepare(
        'INSERT INTO organizations (name, slug, logo_url, description, owner_id, created_at, updated_at) VALUES (:name, :slug, :logo_url, :description, :owner_id, NOW(), NOW())'
    );
    $statement->bindValue(':name', $name, PDO::PARAM_STR);
    $statement->bindValue(':slug', $slug, PDO::PARAM_STR);
    $statement->bindValue(':logo_url', $logoUrl, PDO::PARAM_STR);
    $statement->bindValue(':description', $description, PDO::PARAM_STR);
    $statement->bindValue(':owner_id', $ownerId, PDO::PARAM_INT);
    $statement->execute();

    $organizationId = (int) $conn->lastInsertId();

    $memberStatement = $conn->prepare(
        'INSERT INTO organization_members (organization_id, user_id, role, moderation_status, joined_at, is_active) VALUES (:organization_id, :user_id, "owner", "active", NOW(), 1)'
    );
    $memberStatement->bindValue(':organization_id', $organizationId, PDO::PARAM_INT);
    $memberStatement->bindValue(':user_id', $ownerId, PDO::PARAM_INT);
    $memberStatement->execute();

    return $organizationId;
}

function updateOrganization(PDO $conn, int $organizationId, string $name, string $slug, string $description, ?string $logoUrl = null): bool
{
    if ($logoUrl !== null) {
        $statement = $conn->prepare(
            'UPDATE organizations SET name = :name, slug = :slug, description = :description, logo_url = :logo_url, updated_at = NOW() WHERE id = :organization_id'
        );
        $statement->bindValue(':logo_url', $logoUrl, PDO::PARAM_STR);
    } else {
        $statement = $conn->prepare(
            'UPDATE organizations SET name = :name, slug = :slug, description = :description, updated_at = NOW() WHERE id = :organization_id'
        );
    }

    $statement->bindValue(':name', $name, PDO::PARAM_STR);
    $statement->bindValue(':slug', $slug, PDO::PARAM_STR);
    $statement->bindValue(':description', $description, PDO::PARAM_STR);
    $statement->bindValue(':organization_id', $organizationId, PDO::PARAM_INT);

    return $statement->execute();
}

function updateOrganizationMemberRole(PDO $conn, int $organizationId, int $userId, string $role): bool
{
    $statement = $conn->prepare(
        'UPDATE organization_members SET role = :role WHERE organization_id = :organization_id AND user_id = :user_id'
    );
    $statement->bindValue(':role', $role, PDO::PARAM_STR);
    $statement->bindValue(':organization_id', $organizationId, PDO::PARAM_INT);
    $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);

    return $statement->execute();
}

function addOrUpdateOrganizationMemberByEmail(PDO $conn, int $organizationId, string $email, string $role): array
{
    $userStatement = $conn->prepare('SELECT id, username FROM users WHERE email = :email LIMIT 1');
    $userStatement->bindValue(':email', $email, PDO::PARAM_STR);
    $userStatement->execute();
    $user = $userStatement->fetch();

    if (!$user) {
        return [
            'success' => false,
            'error' => 'No existe ningún usuario con ese email',
        ];
    }

    $memberStatement = $conn->prepare('SELECT id FROM organization_members WHERE organization_id = :organization_id AND user_id = :user_id LIMIT 1');
    $memberStatement->bindValue(':organization_id', $organizationId, PDO::PARAM_INT);
    $memberStatement->bindValue(':user_id', (int) $user['id'], PDO::PARAM_INT);
    $memberStatement->execute();

    if ($memberStatement->fetch()) {
        $updateStatement = $conn->prepare(
            'UPDATE organization_members
             SET role = :role, is_active = 1, moderation_status = "active", moderation_reason = NULL, moderation_until = NULL, moderated_at = NULL, moderated_by = NULL
             WHERE organization_id = :organization_id AND user_id = :user_id'
        );
        $updateStatement->bindValue(':role', $role, PDO::PARAM_STR);
        $updateStatement->bindValue(':organization_id', $organizationId, PDO::PARAM_INT);
        $updateStatement->bindValue(':user_id', (int) $user['id'], PDO::PARAM_INT);
        $updateStatement->execute();
    } else {
        $insertStatement = $conn->prepare(
            'INSERT INTO organization_members (organization_id, user_id, role, moderation_status, joined_at, is_active) VALUES (:organization_id, :user_id, :role, "active", NOW(), 1)'
        );
        $insertStatement->bindValue(':organization_id', $organizationId, PDO::PARAM_INT);
        $insertStatement->bindValue(':user_id', (int) $user['id'], PDO::PARAM_INT);
        $insertStatement->bindValue(':role', $role, PDO::PARAM_STR);
        $insertStatement->execute();
    }

    $orgStatement = $conn->prepare('SELECT name FROM organizations WHERE id = :organization_id LIMIT 1');
    $orgStatement->bindValue(':organization_id', $organizationId, PDO::PARAM_INT);
    $orgStatement->execute();
    $organization = $orgStatement->fetch();

    if ($organization) {
        createNotification(
            $conn,
            (int) $user['id'],
            'organization_invite',
            $organizationId,
            'Te han añadido a la organización ' . $organization['name'] . ' como ' . $role . '.'
        );
    }

    return [
        'success' => true,
        'user_id' => (int) $user['id'],
        'username' => $user['username'],
    ];
}

function canManageOrganizationModeration(string $role): bool
{
    return in_array($role, ['owner', 'admin'], true);
}

function setOrganizationMemberModeration(PDO $conn, int $organizationId, int $targetUserId, int $moderatedByUserId, string $status, string $reason = '', ?string $until = null): array
{
    $allowedStatuses = ['suspended', 'banned', 'active'];
    if (!in_array($status, $allowedStatuses, true)) {
        return ['success' => false, 'error' => 'Estado de moderación no válido'];
    }

    $memberStatement = $conn->prepare(
        'SELECT om.user_id, om.role, u.username, u.email
         FROM organization_members om
         INNER JOIN users u ON u.id = om.user_id
         WHERE om.organization_id = :organization_id AND om.user_id = :user_id LIMIT 1'
    );
    $memberStatement->bindValue(':organization_id', $organizationId, PDO::PARAM_INT);
    $memberStatement->bindValue(':user_id', $targetUserId, PDO::PARAM_INT);
    $memberStatement->execute();
    $member = $memberStatement->fetch();

    if (!$member) {
        return ['success' => false, 'error' => 'El miembro no existe en esta organización'];
    }

    $updateStatement = $conn->prepare(
        'UPDATE organization_members
         SET is_active = :is_active,
             moderation_status = :moderation_status,
             moderation_reason = :moderation_reason,
             moderation_until = :moderation_until,
             moderated_at = NOW(),
             moderated_by = :moderated_by
         WHERE organization_id = :organization_id AND user_id = :user_id'
    );
    $updateStatement->bindValue(':is_active', $status === 'active' ? 1 : 0, PDO::PARAM_INT);
    $updateStatement->bindValue(':moderation_status', $status, PDO::PARAM_STR);
    $updateStatement->bindValue(':moderation_reason', $reason !== '' ? $reason : null, $reason !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
    $updateStatement->bindValue(':moderation_until', $until !== null && $until !== '' ? $until : null, $until !== null && $until !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
    $updateStatement->bindValue(':moderated_by', $moderatedByUserId, PDO::PARAM_INT);
    $updateStatement->bindValue(':organization_id', $organizationId, PDO::PARAM_INT);
    $updateStatement->bindValue(':user_id', $targetUserId, PDO::PARAM_INT);
    $updateStatement->execute();

    $teamUpdate = $conn->prepare(
        'UPDATE team_members tm
         INNER JOIN teams t ON t.id = tm.team_id
         SET tm.is_active = :is_active
         WHERE t.organization_id = :organization_id AND tm.user_id = :user_id'
    );
    $teamUpdate->bindValue(':is_active', $status === 'active' ? 1 : 0, PDO::PARAM_INT);
    $teamUpdate->bindValue(':organization_id', $organizationId, PDO::PARAM_INT);
    $teamUpdate->bindValue(':user_id', $targetUserId, PDO::PARAM_INT);
    $teamUpdate->execute();

    $notificationType = $status === 'active' ? 'moderation_restored' : ($status === 'banned' ? 'moderation_banned' : 'moderation_suspended');
    $message = $status === 'active'
        ? 'Tu acceso a la organización ha sido restaurado.'
        : 'Tu acceso a la organización ha sido ' . $status . '.';
    if ($reason !== '') {
        $message .= ' Motivo: ' . $reason;
    }

    createNotification($conn, $targetUserId, $notificationType, $organizationId, $message);

    return [
        'success' => true,
        'member' => $member,
        'status' => $status,
    ];
}

function setActiveOrganizationContext(PDO $conn, int $userId, int $organizationId): array
{
    $statement = $conn->prepare(
        'SELECT o.id, o.name, o.slug, om.role
         FROM organization_members om
         INNER JOIN organizations o ON o.id = om.organization_id
            WHERE om.user_id = :user_id AND om.organization_id = :organization_id AND om.is_active = 1 AND COALESCE(om.moderation_status, "active") = "active"
         LIMIT 1'
    );
    $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $statement->bindValue(':organization_id', $organizationId, PDO::PARAM_INT);
    $statement->execute();
    $organization = $statement->fetch();

    if (!$organization) {
        return ['success' => false, 'error' => 'No tienes acceso a esa organización'];
    }

    $_SESSION['active_organization_id'] = (int) $organization['id'];
    $_SESSION['user']['organization_id'] = (int) $organization['id'];
    $_SESSION['user']['organization'] = $organization['name'];
    $_SESSION['user']['role'] = $organization['role'];

    return ['success' => true, 'organization' => $organization];
}

function getActiveOrganizationId(PDO $conn, int $userId): ?int
{
    if (!empty($_SESSION['active_organization_id'])) {
        $sessionOrganizationId = (int) $_SESSION['active_organization_id'];
        $validateStatement = $conn->prepare(
            'SELECT organization_id FROM organization_members WHERE user_id = :user_id AND organization_id = :organization_id AND is_active = 1 AND COALESCE(moderation_status, "active") = "active" LIMIT 1'
        );
        $validateStatement->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $validateStatement->bindValue(':organization_id', $sessionOrganizationId, PDO::PARAM_INT);
        $validateStatement->execute();

        if ($validateStatement->fetch()) {
            return $sessionOrganizationId;
        }

        unset($_SESSION['active_organization_id']);
    }

    if (!empty($_SESSION['user']['organization_id'])) {
        $sessionOrganizationId = (int) $_SESSION['user']['organization_id'];
        $validateStatement = $conn->prepare(
            'SELECT organization_id FROM organization_members WHERE user_id = :user_id AND organization_id = :organization_id AND is_active = 1 AND COALESCE(moderation_status, "active") = "active" LIMIT 1'
        );
        $validateStatement->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $validateStatement->bindValue(':organization_id', $sessionOrganizationId, PDO::PARAM_INT);
        $validateStatement->execute();

        if ($validateStatement->fetch()) {
            return $sessionOrganizationId;
        }

        unset($_SESSION['user']['organization_id'], $_SESSION['user']['organization'], $_SESSION['user']['role']);
    }

    $statement = $conn->prepare(
        'SELECT organization_id FROM organization_members WHERE user_id = :user_id AND is_active = 1 AND COALESCE(moderation_status, "active") = "active" ORDER BY joined_at DESC, organization_id DESC LIMIT 1'
    );
    $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $statement->execute();
    $row = $statement->fetch();

    return $row ? (int) $row['organization_id'] : null;
}
