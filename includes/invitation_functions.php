<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/team_functions.php';
require_once __DIR__ . '/notification_functions.php';

function getPendingTeamInvitationsForUser(PDO $conn, int $userId): array
{
    $statement = $conn->prepare(
        'SELECT ti.id, ti.team_id, ti.organization_id, ti.invited_by, ti.invited_user_id, ti.invited_email, ti.role, ti.status, ti.created_at, ti.responded_at,
                t.name AS team_name, t.tag AS team_tag, o.name AS organization_name, u.username AS inviter_name
         FROM team_invitations ti
         INNER JOIN teams t ON t.id = ti.team_id
         INNER JOIN organizations o ON o.id = ti.organization_id
         INNER JOIN users u ON u.id = ti.invited_by
         WHERE ti.invited_user_id = :user_id AND ti.status = "pending"
         ORDER BY ti.created_at DESC, ti.id DESC'
    );
    $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $statement->execute();

    return $statement->fetchAll();
}

function getTeamInvitationById(PDO $conn, int $invitationId, int $userId = 0): array|false
{
    $sql = 'SELECT ti.id, ti.team_id, ti.organization_id, ti.invited_by, ti.invited_user_id, ti.invited_email, ti.role, ti.status, ti.created_at, ti.responded_at,
                   t.name AS team_name, t.tag AS team_tag, o.name AS organization_name, u.username AS inviter_name
            FROM team_invitations ti
            INNER JOIN teams t ON t.id = ti.team_id
            INNER JOIN organizations o ON o.id = ti.organization_id
            INNER JOIN users u ON u.id = ti.invited_by
            WHERE ti.id = :invitation_id';

    if ($userId > 0) {
        $sql .= ' AND ti.invited_user_id = :user_id';
    }

    $sql .= ' LIMIT 1';

    $statement = $conn->prepare($sql);
    $statement->bindValue(':invitation_id', $invitationId, PDO::PARAM_INT);
    if ($userId > 0) {
        $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
    }
    $statement->execute();

    return $statement->fetch() ?: false;
}

function createTeamInvitation(PDO $conn, int $teamId, int $invitedByUserId, string $email, string $role = 'player'): array
{
    $team = getTeamById($conn, $teamId);

    if (!$team) {
        return ['success' => false, 'error' => 'El equipo no existe'];
    }

    $userStatement = $conn->prepare('SELECT id, username, email FROM users WHERE email = :email LIMIT 1');
    $userStatement->bindValue(':email', $email, PDO::PARAM_STR);
    $userStatement->execute();
    $invitedUser = $userStatement->fetch();

    if (!$invitedUser) {
        return ['success' => false, 'error' => 'No existe ningún usuario con ese email'];
    }

    if (isUserActiveMember($conn, $teamId, (int) $invitedUser['id'])) {
        return ['success' => false, 'error' => 'Ese usuario ya es miembro activo del equipo'];
    }

    $existingStatement = $conn->prepare(
        'SELECT id FROM team_invitations WHERE team_id = :team_id AND invited_user_id = :invited_user_id AND status = "pending" LIMIT 1'
    );
    $existingStatement->bindValue(':team_id', $teamId, PDO::PARAM_INT);
    $existingStatement->bindValue(':invited_user_id', (int) $invitedUser['id'], PDO::PARAM_INT);
    $existingStatement->execute();

    if ($existingStatement->fetch()) {
        return ['success' => false, 'error' => 'Ya existe una invitación pendiente para ese usuario'];
    }

    $insertStatement = $conn->prepare(
        'INSERT INTO team_invitations (team_id, organization_id, invited_by, invited_user_id, invited_email, role, status, created_at)
         VALUES (:team_id, :organization_id, :invited_by, :invited_user_id, :invited_email, :role, "pending", NOW())'
    );
    $insertStatement->bindValue(':team_id', $teamId, PDO::PARAM_INT);
    $insertStatement->bindValue(':organization_id', (int) $team['organization_id'], PDO::PARAM_INT);
    $insertStatement->bindValue(':invited_by', $invitedByUserId, PDO::PARAM_INT);
    $insertStatement->bindValue(':invited_user_id', (int) $invitedUser['id'], PDO::PARAM_INT);
    $insertStatement->bindValue(':invited_email', $invitedUser['email'], PDO::PARAM_STR);
    $insertStatement->bindValue(':role', $role, PDO::PARAM_STR);
    $insertStatement->execute();

    $invitationId = (int) $conn->lastInsertId();

    createNotification(
        $conn,
        (int) $invitedUser['id'],
        'team_invite',
        $invitationId,
        'Tienes una invitación para unirte al equipo ' . $team['name'] . ' como ' . $role . '.'
    );

    return [
        'success' => true,
        'invitation_id' => $invitationId,
        'team' => $team,
        'invited_user' => $invitedUser,
    ];
}

function acceptTeamInvitation(PDO $conn, int $invitationId, int $userId): array
{
    $invitation = getTeamInvitationById($conn, $invitationId, $userId);

    if (!$invitation) {
        return ['success' => false, 'error' => 'La invitación no existe'];
    }

    if ((string) $invitation['status'] !== 'pending') {
        return ['success' => false, 'error' => 'La invitación ya fue respondida'];
    }

    $joinResult = joinTeam($conn, (int) $invitation['team_id'], $userId, (string) $invitation['role']);
    if (empty($joinResult['success'])) {
        return $joinResult;
    }

    $updateStatement = $conn->prepare(
        'UPDATE team_invitations SET status = "accepted", responded_at = NOW() WHERE id = :id AND invited_user_id = :user_id'
    );
    $updateStatement->bindValue(':id', $invitationId, PDO::PARAM_INT);
    $updateStatement->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $updateStatement->execute();

    createNotification(
        $conn,
        (int) $invitation['invited_by'],
        'team_invite_accepted',
        $invitationId,
        $invitation['inviter_name'] . ' ha aceptado la invitación al equipo ' . $invitation['team_name'] . '.'
    );

    return [
        'success' => true,
        'team' => $joinResult['team'],
        'invitation' => $invitation,
    ];
}

function declineTeamInvitation(PDO $conn, int $invitationId, int $userId): array
{
    $invitation = getTeamInvitationById($conn, $invitationId, $userId);

    if (!$invitation) {
        return ['success' => false, 'error' => 'La invitación no existe'];
    }

    if ((string) $invitation['status'] !== 'pending') {
        return ['success' => false, 'error' => 'La invitación ya fue respondida'];
    }

    $updateStatement = $conn->prepare(
        'UPDATE team_invitations SET status = "declined", responded_at = NOW() WHERE id = :id AND invited_user_id = :user_id'
    );
    $updateStatement->bindValue(':id', $invitationId, PDO::PARAM_INT);
    $updateStatement->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $updateStatement->execute();

    createNotification(
        $conn,
        (int) $invitation['invited_by'],
        'team_invite_declined',
        $invitationId,
        $invitation['inviter_name'] . ' ha rechazado la invitación al equipo ' . $invitation['team_name'] . '.'
    );

    return ['success' => true, 'invitation' => $invitation];
}
