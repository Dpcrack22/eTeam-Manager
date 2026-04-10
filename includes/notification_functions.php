<?php
require_once __DIR__ . '/db.php';

function createNotification(PDO $conn, int $userId, string $type, ?int $referenceId, string $message): int
{
    $statement = $conn->prepare(
        'INSERT INTO notifications (user_id, type, reference_id, message, is_read, created_at)
         VALUES (:user_id, :type, :reference_id, :message, 0, NOW())'
    );
    $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $statement->bindValue(':type', $type, PDO::PARAM_STR);
    if ($referenceId !== null) {
        $statement->bindValue(':reference_id', $referenceId, PDO::PARAM_INT);
    } else {
        $statement->bindValue(':reference_id', null, PDO::PARAM_NULL);
    }
    $statement->bindValue(':message', $message, PDO::PARAM_STR);
    $statement->execute();

    return (int) $conn->lastInsertId();
}

function notificationTypeLabel(string $type): string
{
    return match ($type) {
        'team_join', 'team_leave', 'team_invite', 'team_invite_accepted', 'team_invite_declined', 'organization_invite' => 'Equipo',
        'moderation_suspended', 'moderation_banned', 'moderation_restored' => 'Moderación',
        'event' => 'Evento',
        'task' => 'Tarea',
        'note' => 'Nota',
        default => ucfirst(str_replace(['_', '-'], ' ', $type)),
    };
}

function getUnreadNotificationsCount(PDO $conn, int $userId): int
{
    $statement = $conn->prepare('SELECT COUNT(*) AS total FROM notifications WHERE user_id = :user_id AND is_read = 0');
    $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $statement->execute();

    return (int) ($statement->fetch()['total'] ?? 0);
}

function getRecentNotifications(PDO $conn, int $userId, int $limit = 6): array
{
    $statement = $conn->prepare(
        'SELECT id, type, reference_id, message, is_read, created_at
         FROM notifications
         WHERE user_id = :user_id
         ORDER BY is_read ASC, created_at DESC, id DESC
         LIMIT :limit'
    );
    $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
    $statement->execute();

    return $statement->fetchAll();
}

function markNotificationAsRead(PDO $conn, int $notificationId, int $userId): bool
{
    $statement = $conn->prepare(
        'UPDATE notifications SET is_read = 1 WHERE id = :id AND user_id = :user_id'
    );
    $statement->bindValue(':id', $notificationId, PDO::PARAM_INT);
    $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);

    return $statement->execute();
}

function markAllNotificationsAsRead(PDO $conn, int $userId): bool
{
    $statement = $conn->prepare(
        'UPDATE notifications SET is_read = 1 WHERE user_id = :user_id AND is_read = 0'
    );
    $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);

    return $statement->execute();
}
