<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/calendar_functions.php';
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
$calendarEvents = [];
$scrimEvents = [];
$recentScrims = [];

if ($activeOrganizationId !== null) {
    $calendarEvents = getTeamCalendarEvents($conn, (int) $activeOrganizationId, $activeTeamId, 5);
    $scrimEvents = getTeamScrimEvents($conn, (int) $activeOrganizationId, $activeTeamId, 5);

    if ($activeTeamId !== null) {
        $recentScrims = getTeamScrims($conn, (int) $activeTeamId);
    }
}

$pageTitle = 'Calendario';
$pageEyebrow = 'Modulo';
$pageDescription = 'Agenda del equipo activo con eventos generales y scrims recientes para mantener conectada la planificación competitiva.';
$activeSection = 'calendar';
?>

<section class="calendar-page">
    <div class="dashboard-hero card">
        <div>
            <div class="small">Integración Sprint 5</div>
            <h2 class="h2">Calendario y scrims</h2>
            <p>Esta vista cruza los eventos del equipo con los scrims registrados para que la agenda y el seguimiento competitivo hablen el mismo idioma.</p>
            <div class="stack-sm">
                <span class="badge badge-info">Eventos</span>
                <span class="badge badge-success">Scrims</span>
                <span class="badge badge-warning">Agenda conectada</span>
            </div>
        </div>
        <div class="dashboard-hero-meta">
            <div class="dashboard-hero-chip">
                <div class="small">Equipo activo</div>
                <div class="dashboard-hero-value"><?php echo htmlspecialchars((string) ($activeTeam['name'] ?? 'Sin equipo'), ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
            <div class="dashboard-hero-chip">
                <div class="small">Eventos visibles</div>
                <div class="dashboard-hero-value"><?php echo count($calendarEvents); ?></div>
            </div>
        </div>
    </div>

    <?php if ($activeTeamId === null): ?>
        <div class="card dashboard-empty-state">
            No hay un equipo activo. En Equipos puedes marcar uno y aquí aparecerán sus eventos y scrims.
        </div>
    <?php else: ?>
        <div class="grid-2">
            <div class="card app-module-card">
                <div class="dashboard-section-head">
                    <div>
                        <div class="small">Agenda</div>
                        <h3 class="h3">Próximos eventos</h3>
                    </div>
                    <a class="btn btn-secondary" href="app.php?view=scrims">Abrir scrims</a>
                </div>

                <?php if (empty($calendarEvents)): ?>
                    <div class="dashboard-empty-state">Todavía no hay eventos cargados para este equipo.</div>
                <?php else: ?>
                    <div class="dashboard-list">
                        <?php foreach ($calendarEvents as $event): ?>
                            <div class="dashboard-list-item">
                                <div class="dashboard-list-top">
                                    <span class="dashboard-list-title"><?php echo htmlspecialchars($event['title'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    <span class="badge badge-info"><?php echo htmlspecialchars($event['event_type'], ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                                <div class="dashboard-list-meta"><?php echo htmlspecialchars($event['start_label'], ENT_QUOTES, 'UTF-8'); ?> · <?php echo htmlspecialchars($event['location'], ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="scrim-note-box" style="margin-top: 16px;">
                    Los eventos de tipo scrim viven en la misma agenda del equipo, así que no hace falta duplicar la información para consultarlos.
                </div>
            </div>

            <div class="card">
                <div class="dashboard-section-head">
                    <div>
                        <div class="small">Scrims</div>
                        <h3 class="h3">Historial reciente</h3>
                    </div>
                </div>

                <?php if (empty($scrimEvents)): ?>
                    <div class="dashboard-empty-state">No hay scrims recientes para este equipo.</div>
                <?php else: ?>
                    <div class="dashboard-list">
                        <?php foreach ($scrimEvents as $scrim): ?>
                            <div class="dashboard-list-item">
                                <div class="dashboard-list-top">
                                    <span class="dashboard-list-title">vs <?php echo htmlspecialchars($scrim['opponent_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    <span class="badge <?php echo htmlspecialchars(scrimResultBadgeClass((string) $scrim['result']), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(scrimResultLabel((string) $scrim['result']), ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                                <div class="dashboard-list-meta"><?php echo htmlspecialchars($scrim['match_date_label'], ENT_QUOTES, 'UTF-8'); ?> · <?php echo htmlspecialchars($scrim['game_mode_name'], ENT_QUOTES, 'UTF-8'); ?> · <?php echo htmlspecialchars($scrim['score_label'], ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="stack-sm" style="margin-top: 16px;">
                    <a class="btn btn-primary" href="app.php?view=scrim-form">Crear scrim</a>
                    <a class="btn btn-secondary" href="app.php?view=scrims">Ver listado</a>
                </div>
            </div>
        </div>

        <div class="card" style="margin-top: 16px;">
            <div class="dashboard-section-head">
                <div>
                    <div class="small">Conexión directa</div>
                    <h3 class="h3">Cómo se enlaza con scrims</h3>
                </div>
            </div>

            <div class="landing-list">
                <div class="landing-list-item">Un scrim programado en agenda se consulta desde aquí.</div>
                <div class="landing-list-item">El historial de scrims se abre sin salir del mismo contexto de equipo.</div>
                <div class="landing-list-item">Calendario y scrims usan el equipo activo como referencia común.</div>
            </div>
        </div>
    <?php endif; ?>
</section>