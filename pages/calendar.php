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

$requestedMonth = trim((string) ($_GET['month'] ?? ''));
if ($requestedMonth !== '' && preg_match('/^\d{4}-\d{2}$/', $requestedMonth)) {
    $monthStart = DateTimeImmutable::createFromFormat('Y-m-d', $requestedMonth . '-01');
} else {
    $monthStart = new DateTimeImmutable('first day of this month');
}

if (!$monthStart) {
    $monthStart = new DateTimeImmutable('first day of this month');
}

$monthStart = $monthStart->setTime(0, 0, 0);
$monthEnd = $monthStart->modify('last day of this month')->setTime(23, 59, 59);
$gridStart = $monthStart->modify('monday this week');
$gridEnd = $monthEnd->modify('sunday this week');
$calendarEntries = [];
$calendarEntriesByDay = [];
$calendarEvents = [];
$scrimEvents = [];

if ($activeOrganizationId !== null) {
    $calendarEvents = getTeamCalendarEvents($conn, (int) $activeOrganizationId, $activeTeamId, 5);
    $scrimEvents = getTeamScrimEvents($conn, (int) $activeOrganizationId, $activeTeamId, 5);
    $calendarEntries = getCalendarMonthEntries($conn, (int) $activeOrganizationId, $activeTeamId, $monthStart, $monthEnd);

    foreach ($calendarEntries as $entry) {
        $calendarEntriesByDay[$entry['date_key']][] = $entry;
    }
}

$monthLabel = $monthStart->format('F Y');
$previousMonth = $monthStart->modify('-1 month')->format('Y-m');
$nextMonth = $monthStart->modify('+1 month')->format('Y-m');
$weekdayLabels = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
$calendarDays = [];

if (!empty($_SESSION['flash_success'])) {
    $successMessage = (string) $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
} else {
    $successMessage = '';
}

for ($cursor = $gridStart; $cursor <= $gridEnd; $cursor = $cursor->modify('+1 day')) {
    $calendarDays[] = [
        'date' => $cursor,
        'key' => $cursor->format('Y-m-d'),
        'day' => $cursor->format('j'),
        'is_current_month' => $cursor->format('m') === $monthStart->format('m') && $cursor->format('Y') === $monthStart->format('Y'),
        'is_today' => $cursor->format('Y-m-d') === (new DateTimeImmutable('today'))->format('Y-m-d'),
        'entries' => $calendarEntriesByDay[$cursor->format('Y-m-d')] ?? [],
    ];
}

$calendarTimeline = array_slice($calendarEntries, 0, 10);

$pageTitle = 'Calendario';
$pageEyebrow = 'Modulo';
$pageDescription = 'Agenda del equipo activo con un calendario mensual real, scrims recientes y eventos para mantener conectada la planificación competitiva.';
$activeSection = 'calendar';
$pageScripts[] = 'js/modules/calendar.js';
?>

<section class="calendar-page">
    <div class="dashboard-hero card">
        <div>
            <div class="small">Agenda conectada</div>
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

    <?php if (!empty($successMessage)): ?>
        <div class="error-box" style="border-color: rgba(46, 204, 113, 0.4); background: rgba(46, 204, 113, 0.1); color: var(--text-main);">
            <?php echo htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <?php if ($activeTeamId === null): ?>
        <div class="card dashboard-empty-state">
            No hay un equipo activo. En Equipos puedes marcar uno y aquí aparecerán sus eventos y scrims.
        </div>
    <?php else: ?>
        <div class="card calendar-toolbar">
            <div>
                <div class="small">Mes activo</div>
                <h3 class="h3"><?php echo htmlspecialchars($monthLabel, ENT_QUOTES, 'UTF-8'); ?></h3>
            </div>
            <div class="calendar-nav">
                <a class="btn btn-secondary" href="app.php?view=calendar&amp;month=<?php echo htmlspecialchars($previousMonth, ENT_QUOTES, 'UTF-8'); ?>">Mes anterior</a>
                <a class="btn btn-secondary" href="app.php?view=calendar">Hoy</a>
                <a class="btn btn-secondary" href="app.php?view=calendar&amp;month=<?php echo htmlspecialchars($nextMonth, ENT_QUOTES, 'UTF-8'); ?>">Mes siguiente</a>
                <a class="btn btn-primary" href="app.php?view=event-form">Nuevo evento</a>
            </div>
        </div>

        <div class="calendar-layout">
            <div class="card calendar-panel">
                <div class="calendar-weekdays" aria-hidden="true">
                    <?php foreach ($weekdayLabels as $weekdayLabel): ?>
                        <div class="calendar-weekday"><?php echo htmlspecialchars($weekdayLabel, ENT_QUOTES, 'UTF-8'); ?></div>
                    <?php endforeach; ?>
                </div>

                <div class="calendar-grid">
                    <?php foreach ($calendarDays as $day): ?>
                        <article class="calendar-day<?php echo $day['is_current_month'] ? '' : ' is-muted'; ?><?php echo $day['is_today'] ? ' is-today' : ''; ?>">
                            <div class="calendar-day-top">
                                <span class="calendar-day-number"><?php echo htmlspecialchars($day['day'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php if (!empty($day['entries'])): ?>
                                    <span class="calendar-day-count"><?php echo count($day['entries']); ?></span>
                                <?php else: ?>
                                    <span class="small"><?php echo $day['is_current_month'] ? ' ' : 'Otro mes'; ?></span>
                                <?php endif; ?>
                            </div>

                            <div class="calendar-day-events">
                                <?php if (empty($day['entries'])): ?>
                                    <div class="calendar-empty-day">Sin eventos</div>
                                <?php else: ?>
                                    <?php foreach (array_slice($day['entries'], 0, 3) as $entry): ?>
                                        <?php $entryClass = $entry['kind'] === 'scrim' ? 'is-scrim' : 'is-event'; ?>
                                        <button class="calendar-event-pill <?php echo $entryClass; ?>" type="button"
                                            data-calendar-entry="true"
                                            data-entry-kind="<?php echo htmlspecialchars($entry['kind'], ENT_QUOTES, 'UTF-8'); ?>"
                                            data-entry-title="<?php echo htmlspecialchars($entry['title'], ENT_QUOTES, 'UTF-8'); ?>"
                                            data-entry-time="<?php echo htmlspecialchars($entry['time_label'], ENT_QUOTES, 'UTF-8'); ?>"
                                            data-entry-badge="<?php echo htmlspecialchars($entry['badge_label'], ENT_QUOTES, 'UTF-8'); ?>"
                                            data-entry-meta="<?php echo htmlspecialchars($entry['meta'], ENT_QUOTES, 'UTF-8'); ?>"
                                            data-entry-description="<?php echo htmlspecialchars((string) ($entry['description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                            data-entry-href="<?php echo htmlspecialchars((string) ($entry['href'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                            <span class="calendar-event-mark"></span>
                                            <span class="calendar-event-copy">
                                                <strong><?php echo htmlspecialchars($entry['title'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                                <span><?php echo htmlspecialchars($entry['badge_label'], ENT_QUOTES, 'UTF-8'); ?> · <?php echo htmlspecialchars($entry['time_label'], ENT_QUOTES, 'UTF-8'); ?></span>
                                            </span>
                                        </button>
                                    <?php endforeach; ?>
                                    <?php if (count($day['entries']) > 3): ?>
                                        <div class="calendar-more">+<?php echo count($day['entries']) - 3; ?> más</div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="calendar-aside">
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
                                        <span class="badge badge-info"><?php echo htmlspecialchars($event['event_type_label'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    </div>
                                    <div class="dashboard-list-meta"><?php echo htmlspecialchars($event['start_label'], ENT_QUOTES, 'UTF-8'); ?> · <?php echo htmlspecialchars($event['location'], ENT_QUOTES, 'UTF-8'); ?></div>
                                    <div class="stack-sm" style="margin-top: 10px;">
                                        <a class="btn btn-secondary" href="<?php echo htmlspecialchars($event['href'], ENT_QUOTES, 'UTF-8'); ?>">Editar</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="card calendar-detail-card" data-calendar-detail-card>
                    <div class="dashboard-section-head">
                        <div>
                            <div class="small">Detalle mock</div>
                            <h3 class="h3">Evento seleccionado</h3>
                        </div>
                    </div>

                    <div class="calendar-detail-empty" data-calendar-detail-empty>
                        Haz clic en cualquier evento del calendario para ver un resumen rápido aquí.
                    </div>

                    <div class="calendar-detail-content" data-calendar-detail-content hidden>
                        <div class="calendar-detail-title" data-calendar-detail-title></div>
                        <div class="calendar-detail-meta" data-calendar-detail-meta></div>
                        <div class="calendar-detail-badges">
                            <span class="badge badge-info" data-calendar-detail-kind></span>
                            <span class="badge" data-calendar-detail-time></span>
                        </div>
                        <div class="scrim-note-box calendar-detail-note" data-calendar-detail-description></div>
                        <a class="btn btn-primary" data-calendar-detail-link href="#" hidden>Ver relacionado</a>
                    </div>
                </div>

                <div class="card app-module-card">
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
                </div>

                <div class="card calendar-panel">
                    <div class="dashboard-section-head">
                        <div>
                            <div class="small">Lectura rápida</div>
                            <h3 class="h3">Siguientes pasos</h3>
                        </div>
                    </div>

                    <div class="landing-list">
                        <div class="landing-list-item">Los scrims aparecen en el mes y también en la agenda lateral.</div>
                        <div class="landing-list-item">Puedes abrir un scrim desde el calendario sin salir del contexto del equipo.</div>
                        <div class="landing-list-item">Si después quieres, aquí ya cabe una edición visual del evento.</div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</section>