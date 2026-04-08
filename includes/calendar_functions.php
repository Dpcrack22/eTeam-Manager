<?php
require_once __DIR__ . '/db.php';

function getCalendarEventTypeOptions(): array
{
    return [
        ['key' => 'scrim', 'label' => 'Scrim'],
        ['key' => 'practice', 'label' => 'Práctica'],
        ['key' => 'tournament', 'label' => 'Torneo'],
        ['key' => 'meeting', 'label' => 'Reunión'],
        ['key' => 'review', 'label' => 'Review'],
    ];
}

function getCalendarEventTypeLabel(string $eventType): string
{
    foreach (getCalendarEventTypeOptions() as $option) {
        if ($option['key'] === $eventType) {
            return $option['label'];
        }
    }

    return ucfirst($eventType);
}

function getCalendarParticipantStatusOptions(): array
{
    return [
        ['key' => 'none', 'label' => 'Sin asignar'],
        ['key' => 'invited', 'label' => 'Invitado'],
        ['key' => 'accepted', 'label' => 'Aceptado'],
        ['key' => 'declined', 'label' => 'Rechazado'],
    ];
}

function getCalendarEventById(PDO $conn, int $eventId, int $organizationId): array|false
{
    $statement = $conn->prepare(
        'SELECT e.id, e.organization_id, e.team_id, e.title, e.description, e.event_type, e.start_datetime, e.end_datetime,
                e.location, e.created_by, e.created_at, t.name AS team_name, t.tag AS team_tag
         FROM events e
         LEFT JOIN teams t ON t.id = e.team_id
         WHERE e.id = :event_id AND e.organization_id = :organization_id
         LIMIT 1'
    );
    $statement->bindValue(':event_id', $eventId, PDO::PARAM_INT);
    $statement->bindValue(':organization_id', $organizationId, PDO::PARAM_INT);
    $statement->execute();

    $event = $statement->fetch();

    if (!$event) {
        return false;
    }

    $startDate = new DateTimeImmutable((string) $event['start_datetime']);
    $endDate = new DateTimeImmutable((string) $event['end_datetime']);

    return [
        'id' => (int) $event['id'],
        'organization_id' => (int) $event['organization_id'],
        'team_id' => $event['team_id'] !== null ? (int) $event['team_id'] : null,
        'team_name' => $event['team_name'],
        'team_tag' => $event['team_tag'],
        'title' => $event['title'],
        'description' => $event['description'],
        'event_type' => $event['event_type'],
        'event_type_label' => getCalendarEventTypeLabel((string) $event['event_type']),
        'start_datetime' => $startDate->format('Y-m-d\TH:i'),
        'end_datetime' => $endDate->format('Y-m-d\TH:i'),
        'start_label' => $startDate->format('d M Y · H:i'),
        'end_label' => $endDate->format('d M Y · H:i'),
        'location' => $event['location'],
        'created_by' => (int) $event['created_by'],
        'created_at' => $event['created_at'],
    ];
}

function getCalendarEventParticipants(PDO $conn, int $eventId): array
{
    $statement = $conn->prepare(
        'SELECT ep.user_id, ep.status, u.username, u.email, u.avatar_url
         FROM event_participants ep
         INNER JOIN users u ON u.id = ep.user_id
         WHERE ep.event_id = :event_id
         ORDER BY FIELD(ep.status, "accepted", "invited", "declined"), u.username ASC'
    );
    $statement->bindValue(':event_id', $eventId, PDO::PARAM_INT);
    $statement->execute();

    return $statement->fetchAll();
}

function normalizeCalendarParticipantRows(array $participantRows): array
{
    $allowedStatuses = ['invited', 'accepted', 'declined'];
    $normalizedRows = [];

    foreach ($participantRows as $userId => $status) {
        $userId = (int) $userId;
        $status = is_array($status) ? (string) ($status['status'] ?? '') : (string) $status;
        $status = strtolower(trim($status));

        if ($userId <= 0 || !in_array($status, $allowedStatuses, true)) {
            continue;
        }

        $normalizedRows[] = [
            'user_id' => $userId,
            'status' => $status,
        ];
    }

    return $normalizedRows;
}

function syncCalendarEventParticipants(PDO $conn, int $eventId, array $participantRows): void
{
    $deleteStatement = $conn->prepare('DELETE FROM event_participants WHERE event_id = :event_id');
    $deleteStatement->bindValue(':event_id', $eventId, PDO::PARAM_INT);
    $deleteStatement->execute();

    $insertStatement = $conn->prepare(
        'INSERT INTO event_participants (event_id, user_id, status)
         VALUES (:event_id, :user_id, :status)'
    );

    foreach (normalizeCalendarParticipantRows($participantRows) as $participantRow) {
        $insertStatement->bindValue(':event_id', $eventId, PDO::PARAM_INT);
        $insertStatement->bindValue(':user_id', $participantRow['user_id'], PDO::PARAM_INT);
        $insertStatement->bindValue(':status', $participantRow['status'], PDO::PARAM_STR);
        $insertStatement->execute();
    }
}

function createCalendarEvent(PDO $conn, int $organizationId, ?int $teamId, string $title, ?string $description, string $eventType, string $startDatetime, string $endDatetime, ?string $location, int $createdBy, array $participantRows = []): int
{
    $conn->beginTransaction();

    try {
        $statement = $conn->prepare(
            'INSERT INTO events (organization_id, team_id, title, description, event_type, start_datetime, end_datetime, location, created_by, created_at)
             VALUES (:organization_id, :team_id, :title, :description, :event_type, :start_datetime, :end_datetime, :location, :created_by, NOW())'
        );
        $statement->bindValue(':organization_id', $organizationId, PDO::PARAM_INT);
        $statement->bindValue(':team_id', $teamId, $teamId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $statement->bindValue(':title', $title, PDO::PARAM_STR);
        $statement->bindValue(':description', $description, $description === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $statement->bindValue(':event_type', $eventType, PDO::PARAM_STR);
        $statement->bindValue(':start_datetime', $startDatetime, PDO::PARAM_STR);
        $statement->bindValue(':end_datetime', $endDatetime, PDO::PARAM_STR);
        $statement->bindValue(':location', $location, $location === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $statement->bindValue(':created_by', $createdBy, PDO::PARAM_INT);
        $statement->execute();

        $eventId = (int) $conn->lastInsertId();
        syncCalendarEventParticipants($conn, $eventId, $participantRows);

        $conn->commit();

        return $eventId;
    } catch (Throwable $exception) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }

        throw $exception;
    }
}

function updateCalendarEvent(PDO $conn, int $eventId, int $organizationId, ?int $teamId, string $title, ?string $description, string $eventType, string $startDatetime, string $endDatetime, ?string $location, array $participantRows = []): bool
{
    $conn->beginTransaction();

    try {
        $statement = $conn->prepare(
            'UPDATE events
             SET team_id = :team_id, title = :title, description = :description, event_type = :event_type,
                 start_datetime = :start_datetime, end_datetime = :end_datetime, location = :location
             WHERE id = :event_id AND organization_id = :organization_id'
        );
        $statement->bindValue(':team_id', $teamId, $teamId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $statement->bindValue(':title', $title, PDO::PARAM_STR);
        $statement->bindValue(':description', $description, $description === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $statement->bindValue(':event_type', $eventType, PDO::PARAM_STR);
        $statement->bindValue(':start_datetime', $startDatetime, PDO::PARAM_STR);
        $statement->bindValue(':end_datetime', $endDatetime, PDO::PARAM_STR);
        $statement->bindValue(':location', $location, $location === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $statement->bindValue(':event_id', $eventId, PDO::PARAM_INT);
        $statement->bindValue(':organization_id', $organizationId, PDO::PARAM_INT);
        $statement->execute();

        syncCalendarEventParticipants($conn, $eventId, $participantRows);

        $conn->commit();

        return true;
    } catch (Throwable $exception) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }

        throw $exception;
    }
}

function deleteCalendarEvent(PDO $conn, int $eventId, int $organizationId): bool
{
    $conn->beginTransaction();

    try {
        $deleteParticipants = $conn->prepare('DELETE FROM event_participants WHERE event_id = :event_id');
        $deleteParticipants->bindValue(':event_id', $eventId, PDO::PARAM_INT);
        $deleteParticipants->execute();

        $deleteEvent = $conn->prepare('DELETE FROM events WHERE id = :event_id AND organization_id = :organization_id');
        $deleteEvent->bindValue(':event_id', $eventId, PDO::PARAM_INT);
        $deleteEvent->bindValue(':organization_id', $organizationId, PDO::PARAM_INT);
        $deleteEvent->execute();

        $deleted = $deleteEvent->rowCount() > 0;

        $conn->commit();

        return $deleted;
    } catch (Throwable $exception) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }

        throw $exception;
    }
}

function getTeamCalendarEvents(PDO $conn, int $organizationId, ?int $teamId = null, int $limit = 5): array
{
    $sql = 'SELECT id, title, description, event_type, start_datetime, end_datetime, location, team_id
            FROM events
            WHERE organization_id = :organization_id';
    $bindings = [':organization_id' => [$organizationId, PDO::PARAM_INT]];

    if ($teamId !== null) {
        $sql .= ' AND (team_id = :team_id OR team_id IS NULL)';
        $bindings[':team_id'] = [$teamId, PDO::PARAM_INT];
    }

    $sql .= ' ORDER BY start_datetime ASC, id DESC LIMIT ' . max(1, $limit);

    $statement = $conn->prepare($sql);

    foreach ($bindings as $parameter => [$value, $type]) {
        $statement->bindValue($parameter, $value, $type);
    }

    $statement->execute();

    return array_map(static function (array $event): array {
        $startDate = new DateTimeImmutable((string) $event['start_datetime']);
        $endDate = new DateTimeImmutable((string) $event['end_datetime']);

        return [
            'id' => (int) $event['id'],
            'title' => $event['title'],
            'description' => $event['description'],
            'event_type' => $event['event_type'],
            'start_label' => $startDate->format('d M · H:i'),
            'end_label' => $endDate->format('H:i'),
            'location' => $event['location'] ?: 'Sin ubicación',
            'team_id' => $event['team_id'] !== null ? (int) $event['team_id'] : null,
            'event_type_label' => getCalendarEventTypeLabel((string) $event['event_type']),
            'href' => 'app.php?view=event-form&event_id=' . (int) $event['id'],
        ];
    }, $statement->fetchAll());
}

function getTeamScrimEvents(PDO $conn, int $organizationId, ?int $teamId = null, int $limit = 5): array
{
    $sql = 'SELECT m.id, m.opponent_name, m.opponent_tag, m.match_date, m.result, m.score_for, m.score_against, gm.name AS game_mode_name
            FROM matches m
            INNER JOIN game_modes gm ON gm.id = m.game_mode_id
            INNER JOIN teams t ON t.id = m.team_id
            WHERE t.organization_id = :organization_id AND m.match_type = "scrim"';
    $bindings = [':organization_id' => [$organizationId, PDO::PARAM_INT]];

    if ($teamId !== null) {
        $sql .= ' AND m.team_id = :team_id';
        $bindings[':team_id'] = [$teamId, PDO::PARAM_INT];
    }

    $sql .= ' ORDER BY m.match_date DESC, m.id DESC LIMIT ' . max(1, $limit);

    $statement = $conn->prepare($sql);

    foreach ($bindings as $parameter => [$value, $type]) {
        $statement->bindValue($parameter, $value, $type);
    }

    $statement->execute();

    return array_map(static function (array $scrim): array {
        $matchDate = new DateTimeImmutable((string) $scrim['match_date']);

        return [
            'id' => (int) $scrim['id'],
            'opponent_name' => $scrim['opponent_name'],
            'opponent_tag' => $scrim['opponent_tag'],
            'match_date_label' => $matchDate->format('d M · H:i'),
            'result' => (string) $scrim['result'],
            'score_label' => ((int) ($scrim['score_for'] ?? 0)) . ' - ' . ((int) ($scrim['score_against'] ?? 0)),
            'game_mode_name' => $scrim['game_mode_name'],
        ];
    }, $statement->fetchAll());
}

function getCalendarMonthEntries(PDO $conn, int $organizationId, ?int $teamId, DateTimeImmutable $monthStart, DateTimeImmutable $monthEnd): array
{
    $monthStartSql = $monthStart->format('Y-m-d 00:00:00');
    $monthEndSql = $monthEnd->format('Y-m-d 23:59:59');
    $entries = [];

    $eventsSql = 'SELECT id, title, description, event_type, start_datetime, end_datetime, location, team_id
                  FROM events
                  WHERE organization_id = :organization_id
                    AND start_datetime >= :month_start
                    AND start_datetime <= :month_end';
    $eventBindings = [
        ':organization_id' => [$organizationId, PDO::PARAM_INT],
        ':month_start' => [$monthStartSql, PDO::PARAM_STR],
        ':month_end' => [$monthEndSql, PDO::PARAM_STR],
    ];

    if ($teamId !== null) {
        $eventsSql .= ' AND (team_id = :team_id OR team_id IS NULL)';
        $eventBindings[':team_id'] = [$teamId, PDO::PARAM_INT];
    }

    $eventsSql .= ' ORDER BY start_datetime ASC, id ASC';

    $eventStatement = $conn->prepare($eventsSql);
    foreach ($eventBindings as $parameter => [$value, $type]) {
        $eventStatement->bindValue($parameter, $value, $type);
    }
    $eventStatement->execute();

    foreach ($eventStatement->fetchAll() as $event) {
        $startDate = new DateTimeImmutable((string) $event['start_datetime']);

        $entries[] = [
            'kind' => 'event',
            'id' => (int) $event['id'],
            'date_key' => $startDate->format('Y-m-d'),
            'time_label' => $startDate->format('H:i'),
            'title' => $event['title'],
            'meta' => $event['location'] ?: 'Sin ubicación',
            'badge_label' => getCalendarEventTypeLabel((string) $event['event_type']),
            'badge_class' => 'badge-info',
            'href' => 'app.php?view=event-form&event_id=' . (int) $event['id'],
            'description' => $event['description'],
        ];
    }

    $scrimSql = 'SELECT m.id, m.opponent_name, m.opponent_tag, m.match_date, m.result, m.score_for, m.score_against, gm.name AS game_mode_name
                 FROM matches m
                 INNER JOIN game_modes gm ON gm.id = m.game_mode_id
                 INNER JOIN teams t ON t.id = m.team_id
                 WHERE t.organization_id = :organization_id
                   AND m.match_type = "scrim"
                   AND m.match_date >= :month_start
                   AND m.match_date <= :month_end';
    $scrimBindings = [
        ':organization_id' => [$organizationId, PDO::PARAM_INT],
        ':month_start' => [$monthStartSql, PDO::PARAM_STR],
        ':month_end' => [$monthEndSql, PDO::PARAM_STR],
    ];

    if ($teamId !== null) {
        $scrimSql .= ' AND m.team_id = :team_id';
        $scrimBindings[':team_id'] = [$teamId, PDO::PARAM_INT];
    }

    $scrimSql .= ' ORDER BY m.match_date ASC, m.id ASC';

    $scrimStatement = $conn->prepare($scrimSql);
    foreach ($scrimBindings as $parameter => [$value, $type]) {
        $scrimStatement->bindValue($parameter, $value, $type);
    }
    $scrimStatement->execute();

    foreach ($scrimStatement->fetchAll() as $scrim) {
        $matchDate = new DateTimeImmutable((string) $scrim['match_date']);
        $result = (string) $scrim['result'];

        $entries[] = [
            'kind' => 'scrim',
            'id' => (int) $scrim['id'],
            'date_key' => $matchDate->format('Y-m-d'),
            'time_label' => $matchDate->format('H:i'),
            'title' => 'vs ' . $scrim['opponent_name'],
            'meta' => $scrim['game_mode_name'] . ' · ' . ((int) ($scrim['score_for'] ?? 0)) . ' - ' . ((int) ($scrim['score_against'] ?? 0)),
            'badge_label' => scrimResultLabel($result),
            'badge_class' => scrimResultBadgeClass($result),
            'href' => 'app.php?view=scrim-detail&scrim_id=' . (int) $scrim['id'],
            'description' => null,
        ];
    }

    usort($entries, static function (array $left, array $right): int {
        if ($left['date_key'] === $right['date_key']) {
            return strcmp($left['time_label'], $right['time_label']);
        }

        return strcmp($left['date_key'], $right['date_key']);
    });

    return $entries;
}