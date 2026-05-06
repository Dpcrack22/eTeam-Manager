<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/scrim_functions.php';

function getTeamAnalyticsSummary(PDO $conn, int $teamId): array
{
    $statement = $conn->prepare(
        'SELECT
            COUNT(*) AS total_scrims,
            COALESCE(SUM(CASE WHEN result = "win" THEN 1 ELSE 0 END), 0) AS wins,
            COALESCE(SUM(CASE WHEN result = "loss" THEN 1 ELSE 0 END), 0) AS losses,
            COALESCE(SUM(CASE WHEN result = "draw" THEN 1 ELSE 0 END), 0) AS draws,
            COALESCE(SUM(CASE WHEN result = "pending" THEN 1 ELSE 0 END), 0) AS pending,
            COALESCE(AVG(CASE WHEN score_for IS NOT NULL AND score_against IS NOT NULL THEN score_for - score_against END), 0) AS avg_score_diff,
            COALESCE(AVG(score_for), 0) AS avg_score_for,
            COALESCE(AVG(score_against), 0) AS avg_score_against,
            MAX(match_date) AS last_match_at
         FROM matches
         WHERE team_id = :team_id AND match_type = "scrim"'
    );
    $statement->bindValue(':team_id', $teamId, PDO::PARAM_INT);
    $statement->execute();
    $row = $statement->fetch() ?: [];

    $mapsStatement = $conn->prepare(
        'SELECT COUNT(*) AS total_maps
         FROM match_maps mm
         INNER JOIN matches m ON m.id = mm.match_id
         WHERE m.team_id = :team_id AND m.match_type = "scrim"'
    );
    $mapsStatement->bindValue(':team_id', $teamId, PDO::PARAM_INT);
    $mapsStatement->execute();
    $mapsRow = $mapsStatement->fetch() ?: [];

    $totalScrims = (int) ($row['total_scrims'] ?? 0);
    $wins = (int) ($row['wins'] ?? 0);
    $losses = (int) ($row['losses'] ?? 0);
    $draws = (int) ($row['draws'] ?? 0);
    $pending = (int) ($row['pending'] ?? 0);

    return [
        'total_scrims' => $totalScrims,
        'wins' => $wins,
        'losses' => $losses,
        'draws' => $draws,
        'pending' => $pending,
        'win_rate' => $totalScrims > 0 ? round(($wins / $totalScrims) * 100, 1) : 0,
        'avg_score_diff' => round((float) ($row['avg_score_diff'] ?? 0), 1),
        'avg_score_for' => round((float) ($row['avg_score_for'] ?? 0), 1),
        'avg_score_against' => round((float) ($row['avg_score_against'] ?? 0), 1),
        'total_maps' => (int) ($mapsRow['total_maps'] ?? 0),
        'last_match_at' => $row['last_match_at'] ?? null,
    ];
}

function getTeamAnalyticsMapStats(PDO $conn, int $teamId): array
{
    $statement = $conn->prepare(
        'SELECT
            gm.name AS map_name,
            COUNT(*) AS times_played,
            COALESCE(SUM(CASE WHEN m.result = "win" THEN 1 ELSE 0 END), 0) AS wins,
            COALESCE(SUM(CASE WHEN m.result = "loss" THEN 1 ELSE 0 END), 0) AS losses,
            COALESCE(SUM(CASE WHEN m.result = "draw" THEN 1 ELSE 0 END), 0) AS draws,
            COALESCE(AVG(CASE WHEN mm.score_for IS NOT NULL AND mm.score_against IS NOT NULL THEN mm.score_for - mm.score_against END), 0) AS avg_score_diff
         FROM match_maps mm
         INNER JOIN matches m ON m.id = mm.match_id
         INNER JOIN game_maps gm ON gm.id = mm.map_id
         WHERE m.team_id = :team_id AND m.match_type = "scrim"
         GROUP BY gm.id, gm.name
         ORDER BY times_played DESC, wins DESC, gm.name ASC
         LIMIT 5'
    );
    $statement->bindValue(':team_id', $teamId, PDO::PARAM_INT);
    $statement->execute();

    return array_map(static function (array $row): array {
        $timesPlayed = (int) $row['times_played'];
        $wins = (int) $row['wins'];

        return [
            'map_name' => $row['map_name'],
            'times_played' => $timesPlayed,
            'wins' => $wins,
            'losses' => (int) $row['losses'],
            'draws' => (int) $row['draws'],
            'win_rate' => $timesPlayed > 0 ? round(($wins / $timesPlayed) * 100, 1) : 0,
            'avg_score_diff' => round((float) $row['avg_score_diff'], 1),
        ];
    }, $statement->fetchAll());
}

function getTeamAnalyticsOpponentStats(PDO $conn, int $teamId): array
{
    $statement = $conn->prepare(
        'SELECT
            opponent_name,
            COALESCE(opponent_tag, "") AS opponent_tag,
            COUNT(*) AS total_matches,
            COALESCE(SUM(CASE WHEN result = "win" THEN 1 ELSE 0 END), 0) AS wins,
            COALESCE(SUM(CASE WHEN result = "loss" THEN 1 ELSE 0 END), 0) AS losses,
            COALESCE(SUM(CASE WHEN result = "draw" THEN 1 ELSE 0 END), 0) AS draws,
            COALESCE(AVG(CASE WHEN score_for IS NOT NULL AND score_against IS NOT NULL THEN score_for - score_against END), 0) AS avg_score_diff
         FROM matches
         WHERE team_id = :team_id AND match_type = "scrim"
         GROUP BY opponent_name, opponent_tag
         ORDER BY total_matches DESC, wins DESC, opponent_name ASC
         LIMIT 5'
    );
    $statement->bindValue(':team_id', $teamId, PDO::PARAM_INT);
    $statement->execute();

    return array_map(static function (array $row): array {
        $totalMatches = (int) $row['total_matches'];
        $wins = (int) $row['wins'];

        return [
            'opponent_name' => $row['opponent_name'],
            'opponent_tag' => $row['opponent_tag'] !== '' ? $row['opponent_tag'] : null,
            'total_matches' => $totalMatches,
            'wins' => $wins,
            'losses' => (int) $row['losses'],
            'draws' => (int) $row['draws'],
            'win_rate' => $totalMatches > 0 ? round(($wins / $totalMatches) * 100, 1) : 0,
            'avg_score_diff' => round((float) $row['avg_score_diff'], 1),
        ];
    }, $statement->fetchAll());
}

function getTeamAnalyticsRecentMatches(PDO $conn, int $teamId, int $limit = 5): array
{
    $matches = getTeamScrims($conn, $teamId, 'all');
    return array_slice($matches, 0, max(1, $limit));
}
