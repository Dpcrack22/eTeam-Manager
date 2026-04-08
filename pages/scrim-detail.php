<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/organization_functions.php';
require_once __DIR__ . '/../includes/scrim_functions.php';
require_once __DIR__ . '/../includes/team_functions.php';

requireAuth();

global $conn;

$currentUser = $_SESSION['user'] ?? [];
$userId = (int) ($currentUser['id'] ?? 0);
$activeOrganizationId = getActiveOrganizationId($conn, $userId);
$activeOrganization = $activeOrganizationId ? getOrganizationById($conn, $activeOrganizationId, $userId) : false;

if (!$activeOrganization) {
    $activeOrganization = [
        'name' => 'Sin organización',
        'slug' => 'sin-organizacion',
    ];
}

$activeTeamId = $activeOrganizationId ? getActiveTeamId($conn, (int) $activeOrganizationId) : null;
$activeTeam = $activeTeamId ? getTeamById($conn, (int) $activeTeamId, (int) $activeOrganizationId) : false;

$scrimId = (int) ($_GET['scrim_id'] ?? 0);
$scrim = false;
$scrimMaps = [];

if ($activeTeamId !== null) {
    if ($scrimId <= 0) {
        $latestScrims = getTeamScrims($conn, (int) $activeTeamId);
        $scrimId = (int) ($latestScrims[0]['id'] ?? 0);
    }

    if ($scrimId > 0) {
        $scrim = getScrimById($conn, $scrimId, (int) $activeTeamId);
        if ($scrim) {
            $scrimMaps = getScrimMaps($conn, (int) $scrim['id']);
        }
    }
}

$pageScripts[] = 'js/modules/scrim-detail.js';
$pageTitle = 'Detalle de scrim';
$pageEyebrow = 'Modulo';
$pageDescription = 'Resumen competitivo del enfrentamiento con mapas, score y espacio para análisis posterior.';
$activeSection = 'scrims';
?>

<section class="scrim-detail-page" data-scrim-detail-root>
    <div class="dashboard-hero card">
        <div>
            <div class="small">Detalle competitivo</div>
            <h2 class="h2">Detalle competitivo</h2>
            <p>Esta vista aterriza el contexto del enfrentamiento para revisar score, modo, mapas y lectura táctica del scrim.</p>
        </div>
        <div class="dashboard-hero-meta">
            <div class="dashboard-hero-chip">
                <div class="small">Equipo activo</div>
                <div class="dashboard-hero-value"><?php echo htmlspecialchars((string) ($activeTeam['name'] ?? 'Sin equipo'), ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
            <div class="dashboard-hero-chip">
                <div class="small">Mapas jugados</div>
                <div class="dashboard-hero-value"><?php echo count($scrimMaps); ?></div>
            </div>
        </div>
    </div>

    <?php if (!$scrim): ?>
        <div class="card dashboard-empty-state">
            No hay ningún scrim seleccionado. Vuelve al listado y abre uno para ver su detalle.
        </div>
    <?php else: ?>
        <div class="grid-2 scrim-detail-grid">
            <div class="card">
                <div class="dashboard-section-head">
                    <div>
                        <div class="small">Enfrentamiento</div>
                        <h3 class="h3"><?php echo htmlspecialchars($scrim['opponent_name'], ENT_QUOTES, 'UTF-8'); ?></h3>
                    </div>
                    <span class="badge <?php echo htmlspecialchars($scrim['result_badge'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($scrim['result_label'], ENT_QUOTES, 'UTF-8'); ?></span>
                </div>

                <div class="scrim-detail-summary">
                    <div class="scrim-detail-score">
                        <div class="small">Score global</div>
                        <div class="scrim-summary-value"><?php echo htmlspecialchars(($scrim['score_for'] ?? 0) . ' - ' . ($scrim['score_against'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>

                    <div class="scrim-detail-meta-grid">
                        <div class="scrim-detail-meta-item">
                            <div class="small">Fecha</div>
                            <div><?php echo htmlspecialchars($scrim['match_date_label'], ENT_QUOTES, 'UTF-8'); ?></div>
                        </div>
                        <div class="scrim-detail-meta-item">
                            <div class="small">Modo</div>
                            <div><?php echo htmlspecialchars($scrim['game_mode_name'], ENT_QUOTES, 'UTF-8'); ?></div>
                        </div>
                        <div class="scrim-detail-meta-item">
                            <div class="small">Rival</div>
                            <div><?php echo htmlspecialchars($scrim['opponent_name'], ENT_QUOTES, 'UTF-8'); ?><?php echo !empty($scrim['opponent_tag']) ? ' · ' . htmlspecialchars($scrim['opponent_tag'], ENT_QUOTES, 'UTF-8') : ''; ?></div>
                        </div>
                    </div>
                </div>

                <div class="scrim-note-box">
                    Aquí puedes concentrar la lectura táctica del encuentro. La estructura ya está lista para añadir notas, VOD review y estadísticas más adelante sin rehacer la página.
                </div>

                <div class="stack-sm">
                    <a class="btn btn-primary" href="app.php?view=scrim-form&amp;scrim_id=<?php echo (int) $scrim['id']; ?>">Editar scrim</a>
                    <a class="btn btn-secondary" href="app.php?view=scrims">Volver al listado</a>
                </div>
            </div>

            <div class="card">
                <div class="dashboard-section-head">
                    <div>
                        <div class="small">Notas</div>
                        <h3 class="h3">Contexto del análisis</h3>
                    </div>
                </div>

                <div class="landing-list">
                    <div class="landing-list-item">Repasar los mapas en orden y detectar patrones de rotación.</div>
                    <div class="landing-list-item">Cruzar el resultado con el plan previo del scrim.</div>
                    <div class="landing-list-item">Dejar una nota post-scrim para el roster o staff.</div>
                    <div class="landing-list-item">Preparar futuras estadísticas por mapa y resultado.</div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="dashboard-section-head">
                <div>
                    <div class="small">Mapas</div>
                    <h3 class="h3">Orden del enfrentamiento</h3>
                </div>
                <div class="small"><?php echo count($scrimMaps); ?> mapas registrados</div>
            </div>

            <?php if (empty($scrimMaps)): ?>
                <div class="dashboard-empty-state">Todavía no hay mapas ligados a este scrim.</div>
            <?php else: ?>
                <div class="scrim-map-list">
                    <?php foreach ($scrimMaps as $index => $scrimMap): ?>
                        <article class="scrim-map-card<?php echo $index === 0 ? ' is-active' : ''; ?>" data-scrim-map-card tabindex="0">
                            <div class="scrim-map-index"><?php echo (int) $scrimMap['order_index']; ?></div>
                            <div>
                                <div class="scrim-map-title"><?php echo htmlspecialchars($scrimMap['map_name'], ENT_QUOTES, 'UTF-8'); ?></div>
                                <div class="small">Mapa <?php echo (int) $scrimMap['order_index']; ?> del scrim</div>
                            </div>
                            <div class="scrim-map-score">
                                <?php echo htmlspecialchars(($scrimMap['score_for'] ?? 0) . ' - ' . ($scrimMap['score_against'] ?? 0), ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>