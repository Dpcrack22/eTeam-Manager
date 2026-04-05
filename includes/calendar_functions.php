<?php
require_once __DIR__ . '/db.php';

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
            'badge_label' => ucfirst((string) $event['event_type']),
            'badge_class' => 'badge-info',
            'href' => null,
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