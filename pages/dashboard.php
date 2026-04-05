<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/organization_functions.php';
require_once __DIR__ . '/../includes/team_functions.php';

requireAuth();

global $conn;

$currentUser = $_SESSION['user'] ?? [];
$currentUserName = $currentUser['name'] ?? 'Usuario';
$currentUserEmail = $currentUser['email'] ?? '';
$currentUserRole = $currentUser['role'] ?? 'Member';

$dashboardInitials = 'EM';
$sanitizedName = trim($currentUserName);
if ($sanitizedName !== '') {
    $nameParts = preg_split('/\s+/', $sanitizedName) ?: [];
    $dashboardInitials = '';

    foreach ($nameParts as $namePart) {
        if ($namePart === '') {
            continue;
        }

        $dashboardInitials .= strtoupper(substr($namePart, 0, 1));
        if (strlen($dashboardInitials) >= 2) {
            break;
        }
    }

    if ($dashboardInitials === '') {
        $dashboardInitials = 'EM';
    }
}

$userId = (int) ($currentUser['id'] ?? 0);
$userOrganizations = getUserOrganizations($conn, $userId);
$activeOrganizationId = getActiveOrganizationId($conn, $userId);

if ($activeOrganizationId === null && !empty($userOrganizations)) {
    $activeOrganizationId = (int) $userOrganizations[0]['id'];
    setActiveOrganizationContext($conn, $userId, $activeOrganizationId);
}

$currentUserRole = $_SESSION['user']['role'] ?? $currentUserRole;

$activeOrganization = [
    'id' => null,
    'name' => $currentUser['team'] ?? 'Sin equipo',
    'slug' => 'sin-equipo',
    'description' => 'Sin contexto de equipo activo.',
];

foreach ($userOrganizations as $userOrganization) {
    if ((int) $userOrganization['id'] === (int) $activeOrganizationId) {
        $activeOrganization = [
            'id' => (int) $userOrganization['id'],
            'name' => $userOrganization['name'],
            'slug' => $userOrganization['slug'],
            'description' => $userOrganization['description'] ?: 'Contexto activo cargado desde la base de datos.',
        ];
        break;
    }
}

$activeTeam = [
    'id' => null,
    'name' => 'Sin equipo',
    'tag' => '--',
];
$upcomingEvents = [];
$pendingTasks = [];
$recentScrims = [];

if ($activeOrganization['id'] !== null) {
    $organizationTeams = getOrganizationTeams($conn, (int) $activeOrganization['id']);
    $activeTeamId = getActiveTeamId($conn, (int) $activeOrganization['id']);

    if ($activeTeamId === null && !empty($organizationTeams)) {
        $activeTeamId = (int) $organizationTeams[0]['id'];
        setActiveTeamContext($conn, (int) $activeOrganization['id'], $activeTeamId);
    }

    foreach ($organizationTeams as $teamRow) {
        if ($activeTeamId !== null && (int) $teamRow['id'] === (int) $activeTeamId) {
            $activeTeam = [
                'id' => (int) $teamRow['id'],
                'name' => $teamRow['name'],
                'tag' => $teamRow['tag'] ?: '--',
            ];
            break;
        }
    }

    $eventsSql = 'SELECT title, event_type, DATE_FORMAT(start_datetime, "%d %b · %H:%i") AS date_label, location FROM events WHERE organization_id = :organization_id';
    if ($activeTeam['id'] !== null) {
        $eventsSql .= ' AND (team_id = :team_id OR team_id IS NULL)';
    }
    $eventsSql .= ' ORDER BY start_datetime ASC LIMIT 3';

    $eventsStatement = $conn->prepare($eventsSql);
    $eventsStatement->bindValue(':organization_id', (int) $activeOrganization['id'], PDO::PARAM_INT);
    if ($activeTeam['id'] !== null) {
        $eventsStatement->bindValue(':team_id', $activeTeam['id'], PDO::PARAM_INT);
    }
    $eventsStatement->execute();
    $upcomingEvents = $eventsStatement->fetchAll();

    if ($activeTeam['id'] !== null) {
        $tasksStatement = $conn->prepare(
            'SELECT t.title, t.priority, DATE_FORMAT(t.due_date, "%d %b") AS due_label, COALESCE(u.username, "Sin asignar") AS assignee_name
             FROM tasks t
             LEFT JOIN users u ON u.id = t.assigned_to
             WHERE t.team_id = :team_id AND t.status <> "Hecho"
             ORDER BY t.due_date ASC, t.id ASC
             LIMIT 3'
        );
        $tasksStatement->bindValue(':team_id', $activeTeam['id'], PDO::PARAM_INT);
        $tasksStatement->execute();
        $pendingTasks = $tasksStatement->fetchAll();

        $scrimsStatement = $conn->prepare(
            'SELECT opponent_name, result, CONCAT(COALESCE(score_for, 0), " - ", COALESCE(score_against, 0)) AS score_label, DATE_FORMAT(match_date, "%d %b") AS meta_label
             FROM matches
             WHERE team_id = :team_id
             ORDER BY match_date DESC, id DESC
             LIMIT 3'
        );
        $scrimsStatement->bindValue(':team_id', $activeTeam['id'], PDO::PARAM_INT);
        $scrimsStatement->execute();
        $recentScrims = $scrimsStatement->fetchAll();
    }
}

$dashboardData = [
    'currentUser' => [
        'name' => $currentUserName,
        'email' => $currentUserEmail,
        'role' => $currentUserRole,
        'avatarInitials' => $dashboardInitials,
    ],
    'activeOrganization' => [
        'name' => $activeOrganization['name'],
        'slug' => $activeOrganization['slug'],
    ],
    'activeTeam' => [
        'name' => $activeTeam['name'],
        'tag' => $activeTeam['tag'],
    ],
    'upcomingEvents' => array_map(static function (array $event): array {
        return [
            'title' => $event['title'],
            'type' => $event['event_type'],
            'dateLabel' => $event['date_label'],
            'location' => $event['location'] ?: 'Sin ubicación',
        ];
    }, $upcomingEvents),
    'pendingTasks' => array_map(static function (array $task): array {
        return [
            'title' => $task['title'],
            'priority' => ucfirst((string) $task['priority']),
            'meta' => 'Asignada a ' . $task['assignee_name'] . ' · vence ' . $task['due_label'],
        ];
    }, $pendingTasks),
    'recentScrims' => array_map(static function (array $scrim): array {
        return [
            'opponent' => $scrim['opponent_name'],
            'result' => ucfirst((string) $scrim['result']),
            'score' => $scrim['score_label'],
            'meta' => 'Jugado el ' . $scrim['meta_label'],
        ];
    }, $recentScrims),
];
?>

<section class="dashboard" data-dashboard-root>
    <div class="dashboard-hero card">
        <div>
            <div class="small">Resumen operativo</div>
            <h2 class="h2">Bienvenido al panel de equipos</h2>
            <p>Este dashboard concentra el contexto principal de trabajo del usuario: equipo activo, próximos eventos, tareas pendientes y scrims recientes.</p>
            <div class="stack-sm">
                <span class="badge">Sprint 2</span>
                <span class="badge badge-info">Dashboard conectado</span>
                <span class="badge badge-success">Datos de BD</span>
            </div>
        </div>
        <div class="dashboard-hero-meta">
            <div class="dashboard-hero-chip">
                <div class="small">Equipo activo</div>
                <div class="dashboard-hero-value" data-dashboard-team-name><?php echo htmlspecialchars($activeTeam['name'], ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
            <div class="dashboard-hero-chip">
                <div class="small">Tag competitivo</div>
                <div class="dashboard-hero-value"><?php echo htmlspecialchars($activeTeam['tag'], ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
        </div>
    </div>

    <div class="dashboard-kpis">
        <article class="card dashboard-kpi">
            <div class="small">Mi rol actual</div>
            <div class="dashboard-kpi-value" data-dashboard-user-role><?php echo htmlspecialchars($currentUserRole, ENT_QUOTES, 'UTF-8'); ?></div>
            <p class="dashboard-kpi-copy">Rol del usuario en el contexto activo de trabajo.</p>
        </article>

        <article class="card dashboard-kpi">
            <div class="small">Proximos eventos</div>
            <div class="dashboard-kpi-value" data-dashboard-events-count><?php echo count($upcomingEvents); ?></div>
            <p class="dashboard-kpi-copy">Eventos programados para los siguientes dias.</p>
        </article>

        <article class="card dashboard-kpi">
            <div class="small">Tareas pendientes</div>
            <div class="dashboard-kpi-value" data-dashboard-tasks-count><?php echo count($pendingTasks); ?></div>
            <p class="dashboard-kpi-copy">Tareas abiertas o en progreso dentro del roster.</p>
        </article>

        <article class="card dashboard-kpi">
            <div class="small">Scrims recientes</div>
            <div class="dashboard-kpi-value" data-dashboard-scrims-count><?php echo count($recentScrims); ?></div>
            <p class="dashboard-kpi-copy">Historial competitivo reciente para revisiones rapidas.</p>
        </article>
    </div>

    <div class="grid-2 dashboard-main-grid">
        <article class="card">
            <div class="dashboard-section-head">
                <div>
                    <div class="small">Contexto actual</div>
                    <h3 class="h3">Estado del usuario</h3>
                </div>
                <a class="btn btn-secondary" href="app.php?view=settings">Ir a mi cuenta</a>
            </div>

            <div class="dashboard-profile-summary">
                <div class="dashboard-avatar" data-dashboard-avatar><?php echo htmlspecialchars($dashboardInitials, ENT_QUOTES, 'UTF-8'); ?></div>
                <div>
                    <div class="h4" data-dashboard-user-name><?php echo htmlspecialchars($currentUserName, ENT_QUOTES, 'UTF-8'); ?></div>
                    <p class="dashboard-inline-copy" data-dashboard-user-email><?php echo htmlspecialchars($currentUserEmail, ENT_QUOTES, 'UTF-8'); ?></p>
                    <div class="stack-sm">
                        <span class="badge">Roster activo</span>
                        <span class="badge badge-info" data-dashboard-team-tag><?php echo htmlspecialchars($activeTeam['tag'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                </div>
            </div>
        </article>

        <article class="card">
            <div class="dashboard-section-head">
                <div>
                    <div class="small">Acceso rapido</div>
                    <h3 class="h3">Modulos clave</h3>
                </div>
            </div>

            <div class="dashboard-quick-actions">
                <a class="dashboard-action-tile" href="app.php?view=team-detail">
                    <span class="small">Roster</span>
                    <strong>Detalle de equipo</strong>
                    <span>Revisar el roster, los miembros y los roles del equipo activo.</span>
                </a>
                <a class="dashboard-action-tile" href="app.php?view=teams">
                    <span class="small">Roster</span>
                    <strong>Equipos</strong>
                    <span>Ver el equipo activo y la relación con el roster competitivo.</span>
                </a>
                <a class="dashboard-action-tile" href="app.php?view=calendar">
                    <span class="small">Agenda</span>
                    <strong>Calendario</strong>
                    <span>Consultar entrenamientos, scrims y reuniones programadas.</span>
                </a>
                <a class="dashboard-action-tile" href="app.php?view=scrims">
                    <span class="small">Competitivo</span>
                    <strong>Scrims</strong>
                    <span>Entrar al historial competitivo y revisar resultados recientes.</span>
                </a>
                <a class="dashboard-action-tile" href="app.php?view=boards">
                    <span class="small">Trabajo interno</span>
                    <strong>Tableros</strong>
                    <span>Revisar tareas operativas y prioridades pendientes.</span>
                </a>
                <a class="dashboard-action-tile" href="app.php?view=settings">
                    <span class="small">Cuenta</span>
                    <strong>Perfil y ajustes</strong>
                    <span>Consultar datos del usuario y preferencias del entorno interno.</span>
                </a>
            </div>
        </article>
    </div>

    <div class="grid-3 dashboard-content-grid">
        <article class="card dashboard-panel">
            <div class="dashboard-section-head">
                <div>
                    <div class="small">Agenda</div>
                    <h3 class="h3">Proximos eventos</h3>
                </div>
                <a class="btn btn-secondary" href="app.php?view=calendar">Ver calendario</a>
            </div>
            <div class="dashboard-list" data-dashboard-events-list></div>
        </article>

        <article class="card dashboard-panel">
            <div class="dashboard-section-head">
                <div>
                    <div class="small">Seguimiento</div>
                    <h3 class="h3">Tareas pendientes</h3>
                </div>
                <a class="btn btn-secondary" href="app.php?view=boards">Ver board</a>
            </div>
            <div class="dashboard-list" data-dashboard-tasks-list></div>
        </article>

        <article class="card dashboard-panel">
            <div class="dashboard-section-head">
                <div>
                    <div class="small">Competitivo</div>
                    <h3 class="h3">Scrims recientes</h3>
                </div>
                <a class="btn btn-secondary" href="app.php?view=scrims">Ver scrims</a>
            </div>
            <div class="dashboard-list" data-dashboard-scrims-list></div>
        </article>
    </div>
</section>

<script>
window.eTeamAppData = <?php echo json_encode($dashboardData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
</script>
