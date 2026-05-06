<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/analytics_functions.php';
require_once __DIR__ . '/../includes/organization_functions.php';
require_once __DIR__ . '/../includes/team_functions.php';

requireAuth();

global $conn;

$currentUser = $_SESSION['user'] ?? [];
$userId = (int) ($currentUser['id'] ?? 0);
$activeOrganizationId = getActiveOrganizationId($conn, $userId);
$activeTeamId = $activeOrganizationId !== null ? getActiveTeamId($conn, (int) $activeOrganizationId) : null;
$activeTeam = $activeTeamId ? getTeamById($conn, (int) $activeTeamId, (int) ($activeOrganizationId ?? 0)) : false;

if (!$activeTeam) {
    $activeTeam = false;
}

$summary = $activeTeam ? getTeamAnalyticsSummary($conn, (int) $activeTeamId) : [
    'total_scrims' => 0,
    'wins' => 0,
    'losses' => 0,
    'draws' => 0,
    'pending' => 0,
    'win_rate' => 0,
    'avg_score_diff' => 0,
    'avg_score_for' => 0,
    'avg_score_against' => 0,
    'total_maps' => 0,
    'last_match_at' => null,
];
$mapStats = $activeTeam ? getTeamAnalyticsMapStats($conn, (int) $activeTeamId) : [];
$opponentStats = $activeTeam ? getTeamAnalyticsOpponentStats($conn, (int) $activeTeamId) : [];
$recentMatches = $activeTeam ? getTeamAnalyticsRecentMatches($conn, (int) $activeTeamId, 5) : [];

$pageTitle = 'Analitica del equipo';
$pageEyebrow = 'Modulo';
$pageDescription = 'Resumen competitivo del roster activo con victorias, mapas, rivales y scrims recientes.';
$activeSection = 'analytics';
?>

<section class="analytics-page">
    <div class="dashboard-hero card">
        <div>
            <div class="small">Seguimiento competitivo</div>
            <h2 class="h2">Analitica del equipo</h2>
            <p>Este panel resume el rendimiento del roster activo con victorias, mapas jugados, rivales y la evolución reciente de los scrims.</p>
            <div class="stack-sm">
                <span class="badge badge-info"><?php echo (int) $summary['total_scrims']; ?> scrims</span>
                <span class="badge badge-success"><?php echo (float) $summary['win_rate']; ?>% win rate</span>
                <span class="badge"><?php echo (int) $summary['total_maps']; ?> mapas</span>
            </div>
        </div>
        <div class="dashboard-hero-meta">
            <div class="dashboard-hero-chip">
                <div class="small">Equipo activo</div>
                <div class="dashboard-hero-value"><?php echo htmlspecialchars($activeTeam['name'] ?? 'Sin equipo', ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
            <div class="dashboard-hero-chip">
                <div class="small">Ultimo scrim</div>
                <div class="dashboard-hero-value"><?php echo !empty($summary['last_match_at']) ? htmlspecialchars(date('d/m/Y', strtotime((string) $summary['last_match_at'])), ENT_QUOTES, 'UTF-8') : 'Sin datos'; ?></div>
            </div>
        </div>
    </div>

    <?php if (!$activeTeam): ?>
        <div class="card dashboard-empty-state">
            Todavia no hay un equipo activo para calcular analitica. Activa un equipo desde Equipos para ver sus datos competitivos.
        </div>
    <?php else: ?>
        <div class="analytics-summary-grid">
            <article class="card analytics-stat-card">
                <div class="small">Victorias</div>
                <div class="analytics-stat-value"><?php echo (int) $summary['wins']; ?></div>
                <div class="small">Derrotas: <?php echo (int) $summary['losses']; ?> · Empates: <?php echo (int) $summary['draws']; ?></div>
            </article>
            <article class="card analytics-stat-card">
                <div class="small">Win rate</div>
                <div class="analytics-stat-value"><?php echo (float) $summary['win_rate']; ?>%</div>
                <div class="small">Pendientes: <?php echo (int) $summary['pending']; ?></div>
            </article>
            <article class="card analytics-stat-card">
                <div class="small">Diferencia media</div>
                <div class="analytics-stat-value"><?php echo (float) $summary['avg_score_diff']; ?></div>
                <div class="small">A favor <?php echo (float) $summary['avg_score_for']; ?> · En contra <?php echo (float) $summary['avg_score_against']; ?></div>
            </article>
            <article class="card analytics-stat-card">
                <div class="small">Mapas jugados</div>
                <div class="analytics-stat-value"><?php echo (int) $summary['total_maps']; ?></div>
                <div class="small">En scrims del equipo activo</div>
            </article>
        </div>

        <div class="grid-2">
            <div class="card analytics-panel">
                <div class="dashboard-section-head">
                    <div>
                        <div class="small">Mapas</div>
                        <h3 class="h3">Rendimiento por mapa</h3>
                    </div>
                </div>

                <?php if (empty($mapStats)): ?>
                    <div class="dashboard-empty-state">Aun no hay mapas registrados para este equipo.</div>
                <?php else: ?>
                    <div class="analytics-table-wrap">
                        <table class="analytics-table">
                            <thead>
                                <tr>
                                    <th>Mapa</th>
                                    <th>Partidas</th>
                                    <th>Win rate</th>
                                    <th>Diferencia</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($mapStats as $mapRow): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars((string) $mapRow['map_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo (int) $mapRow['times_played']; ?></td>
                                        <td><?php echo (float) $mapRow['win_rate']; ?>%</td>
                                        <td><?php echo (float) $mapRow['avg_score_diff']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <div class="card analytics-panel">
                <div class="dashboard-section-head">
                    <div>
                        <div class="small">Rivales</div>
                        <h3 class="h3">Rendimiento por oponente</h3>
                    </div>
                </div>

                <?php if (empty($opponentStats)): ?>
                    <div class="dashboard-empty-state">Aun no hay rivales registrados para este equipo.</div>
                <?php else: ?>
                    <div class="analytics-list">
                        <?php foreach ($opponentStats as $opponentRow): ?>
                            <article class="analytics-list-item">
                                <div class="analytics-list-top">
                                    <div>
                                        <div class="dashboard-list-title"><?php echo htmlspecialchars((string) $opponentRow['opponent_name'], ENT_QUOTES, 'UTF-8'); ?></div>
                                        <div class="dashboard-list-meta"><?php echo !empty($opponentRow['opponent_tag']) ? htmlspecialchars((string) $opponentRow['opponent_tag'], ENT_QUOTES, 'UTF-8') . ' · ' : ''; ?><?php echo (int) $opponentRow['total_matches']; ?> partidas</div>
                                    </div>
                                    <span class="badge badge-info"><?php echo (float) $opponentRow['win_rate']; ?>%</span>
                                </div>
                                <div class="small">Victorias: <?php echo (int) $opponentRow['wins']; ?> · Derrotas: <?php echo (int) $opponentRow['losses']; ?> · Empates: <?php echo (int) $opponentRow['draws']; ?> · Diff: <?php echo (float) $opponentRow['avg_score_diff']; ?></div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card analytics-panel">
            <div class="dashboard-section-head">
                <div>
                    <div class="small">Periodo reciente</div>
                    <h3 class="h3">Ultimos scrims</h3>
                </div>
            </div>

            <?php if (empty($recentMatches)): ?>
                <div class="dashboard-empty-state">Todavia no hay scrims recientes para mostrar.</div>
            <?php else: ?>
                <div class="analytics-recent-list">
                    <?php foreach ($recentMatches as $match): ?>
                        <article class="analytics-recent-item">
                            <div class="analytics-recent-top">
                                <div>
                                    <div class="dashboard-list-title"><?php echo htmlspecialchars((string) $match['opponent_name'], ENT_QUOTES, 'UTF-8'); ?></div>
                                    <div class="dashboard-list-meta"><?php echo htmlspecialchars((string) $match['match_date_label'], ENT_QUOTES, 'UTF-8'); ?> · <?php echo htmlspecialchars((string) $match['game_mode_name'], ENT_QUOTES, 'UTF-8'); ?></div>
                                </div>
                                <span class="badge <?php echo htmlspecialchars((string) $match['result_badge'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) $match['result_label'], ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                            <div class="small">Marcador <?php echo htmlspecialchars((string) $match['score_label'], ENT_QUOTES, 'UTF-8'); ?> · Mapas: <?php echo (int) $match['maps_count']; ?></div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>
