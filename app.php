<?php
ob_start();

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/organization_functions.php';
require_once __DIR__ . '/includes/team_functions.php';

$view = isset($view) ? strtolower((string) $view) : (isset($_GET['view']) ? strtolower((string) $_GET['view']) : 'dashboard');
$isAuthenticated = isLogged();
$appCurrentRequestUri = (string) ($_SERVER['REQUEST_URI'] ?? ('app.php?view=' . $view));
$appCurrentRequestUri = preg_replace('/^.*?(app\.php\?.*)$/', '$1', $appCurrentRequestUri) ?: ('app.php?view=' . $view);

if (!str_starts_with($appCurrentRequestUri, 'app.php?view=')) {
    $appCurrentRequestUri = 'app.php?view=' . $view;
}

if (!$isAuthenticated && !in_array($view, ['login', 'register'], true)) {
    header('Location: app.php?view=login');
    exit;
}

if ($isAuthenticated && in_array($view, ['login', 'register'], true)) {
    header('Location: app.php?view=dashboard');
    exit;
}

$modulesToHide = ['organizations'];
if (in_array($view, $modulesToHide, true)) {
    header('Location: app.php?view=teams');
    exit;
}

$isTeamDetailView = $view === 'team-detail';
$isScrimDetailView = in_array($view, ['scrim-detail', 'scrim-form'], true);
$isCalendarChildView = $view === 'event-form';

$appModules = [
    'dashboard' => [
        'label' => 'Dashboard',
        'title' => 'Dashboard',
        'eyebrow' => 'App interna',
        'description' => 'Resumen operativo del contexto actual, la agenda inmediata, las tareas abiertas y el seguimiento competitivo del roster.',
        'page' => __DIR__ . '/../pages/dashboard.php',
    ],
    'teams' => [
        'label' => 'Equipos',
        'title' => 'Equipos',
        'eyebrow' => 'Modulo',
        'description' => 'Gestion de roster, detalle de equipos y relacion con la organizacion activa.',
        'page' => __DIR__ . '/../pages/teams.php',
    ],
    'team-detail' => [
        'label' => 'Detalle de equipo',
        'title' => 'Detalle de equipo',
        'eyebrow' => 'Modulo',
        'description' => 'Ficha del roster, sus miembros y sus roles internos.',
        'page' => __DIR__ . '/../pages/team-detail.php',
    ],
    'scrims' => [
        'label' => 'Scrims',
        'title' => 'Scrims',
        'eyebrow' => 'Modulo',
        'description' => 'Registro de enfrentamientos, resultados, mapas y detalle competitivo.',
        'page' => __DIR__ . '/../pages/scrims.php',
    ],
    'scrim-form' => [
        'label' => 'Nuevo scrim',
        'title' => 'Nuevo scrim',
        'eyebrow' => 'Modulo',
        'description' => 'Alta y edición visual de scrims con mapa, score y resultado.',
        'page' => __DIR__ . '/../pages/scrim-form.php',
    ],
    'scrim-detail' => [
        'label' => 'Detalle de scrim',
        'title' => 'Detalle de scrim',
        'eyebrow' => 'Modulo',
        'description' => 'Resumen competitivo del enfrentamiento, con mapas, score y contexto.',
        'page' => __DIR__ . '/../pages/scrim-detail.php',
    ],
    'event-form' => [
        'label' => 'Evento',
        'title' => 'Evento',
        'eyebrow' => 'Modulo',
        'description' => 'Alta y edición visual de eventos del calendario con participación.',
        'page' => __DIR__ . '/../pages/event-form.php',
    ],
    'calendar' => [
        'label' => 'Calendario',
        'title' => 'Calendario',
        'eyebrow' => 'Modulo',
        'description' => 'Planificacion de eventos de equipo y organizacion con estados de asistencia.',
        'page' => __DIR__ . '/../pages/calendar.php',
    ],
    'boards' => [
        'label' => 'Tableros',
        'title' => 'Tableros',
        'eyebrow' => 'Modulo',
        'description' => 'Kanban interno para tareas, prioridades y seguimiento del trabajo.',
        'page' => __DIR__ . '/../pages/boards.php',
    ],
    'notes' => [
        'label' => 'Notas',
        'title' => 'Notas',
        'eyebrow' => 'Modulo',
        'description' => 'Repositorio interno para estrategia, analisis y documentacion del equipo.',
        'page' => __DIR__ . '/../pages/notes.php',
    ],
    'settings' => [
        'label' => 'Configuracion',
        'title' => 'Perfil y ajustes',
        'eyebrow' => 'Modulo',
        'description' => 'Configuracion del usuario, preferencias y datos personales.',
        'page' => __DIR__ . '/../pages/settings.php',
    ],
	'login' => [
		'label' => 'Login',
		'title' => 'Login',
		'eyebrow' => 'Acceso',
		'description' => 'Inicia sesión en eTeam Manager',
	],
	'register' => [
		'label' => 'Registro',
		'title' => 'Registro',
		'eyebrow' => 'Crear cuenta',
        'description' => 'Crea una cuenta para acceder a eTeam Manager',
	],
];

if (!isset($appModules[$view])) {
    $view = 'dashboard';
    $isTeamDetailView = false;
    $isCalendarChildView = false;
}

$currentModule = $appModules[$view];
$activeSection = $activeSection ?? ($isTeamDetailView ? 'teams' : ($isScrimDetailView ? 'scrims' : ($isCalendarChildView ? 'calendar' : $view)));
$pageTitle = $pageTitle ?? $currentModule['title'];
$pageEyebrow = $pageEyebrow ?? $currentModule['eyebrow'];
$pageDescription = $pageDescription ?? $currentModule['description'];
$pageScripts = $pageScripts ?? [];
$pageScripts[] = 'js/modules/app-shell.js';
$appAuthState = $appAuthState ?? ($isAuthenticated ? 'authenticated' : 'guest');
$appCurrentUser = $appCurrentUser ?? [
    'name' => $_SESSION['user']['name'] ?? 'Usuario',
    'role' => $_SESSION['user']['role'] ?? 'Manager',
    'team' => $_SESSION['user']['team'] ?? 'Sin equipo',
    'avatar_url' => $_SESSION['user']['avatar_url'] ?? null,
];

if (empty($appCurrentUser['initials'])) {
    $userNameForInitials = trim((string) $appCurrentUser['name']);
    $appCurrentUser['initials'] = 'EM';

    if ($userNameForInitials !== '') {
        $nameParts = preg_split('/\s+/', $userNameForInitials) ?: [];
        $initials = '';

        foreach ($nameParts as $namePart) {
            if ($namePart === '') {
                continue;
            }

            $initials .= strtoupper(substr($namePart, 0, 1));
            if (strlen($initials) >= 2) {
                break;
            }
        }

        if ($initials !== '') {
            $appCurrentUser['initials'] = $initials;
        }
    }
}

if ($appAuthState === 'authenticated' && !empty($_SESSION['user']['id'])) {
    $appShellUserId = (int) $_SESSION['user']['id'];
    $appShellOrganizationId = getActiveOrganizationId($conn, $appShellUserId);

    if ($appShellOrganizationId !== null) {
        $appShellTeamId = getActiveTeamId($conn, (int) $appShellOrganizationId);

        if ($appShellTeamId !== null) {
            $appShellTeam = getTeamById($conn, (int) $appShellTeamId, (int) $appShellOrganizationId);

            if ($appShellTeam) {
                $appCurrentUser['team'] = $appShellTeam['name'];
                $appCurrentUser['team_id'] = (int) $appShellTeam['id'];
                $appCurrentUser['team_tag'] = $appShellTeam['tag'] ?: '--';
            }
        }
    }
}

$appActiveTeamId = $appCurrentUser['team_id'] ?? null;
$appSidebarTeams = [];

if ($appAuthState === 'authenticated' && $appShellOrganizationId !== null) {
    $appSidebarTeams = getOrganizationTeams($conn, (int) $appShellOrganizationId);
}

if ($view === 'dashboard') {
    $pageScripts[] = 'js/modules/dashboard.js';
}

if (!isset($appNavItems)) {
    $appNavItems = [];

    foreach ($appModules as $moduleKey => $module) {
        if (in_array($moduleKey, ['team-detail', 'scrim-form', 'scrim-detail', 'event-form'], true)) {
            continue;
        }

        $appNavItems[$moduleKey] = [
            'label' => $module['label'],
            'href' => 'app.php?view=' . $moduleKey,
        ];
    }
}

if ($isTeamDetailView) {
    $appBreadcrumbs = [
        ['label' => 'App', 'href' => 'app.php?view=dashboard'],
        ['label' => 'Equipos', 'href' => 'app.php?view=teams'],
        ['label' => $currentModule['label'], 'href' => 'app.php?view=team-detail'],
    ];
} elseif ($isCalendarChildView) {
    $appBreadcrumbs = [
        ['label' => 'App', 'href' => 'app.php?view=dashboard'],
        ['label' => 'Calendario', 'href' => 'app.php?view=calendar'],
        ['label' => $currentModule['label'], 'href' => 'app.php?view=event-form'],
    ];
} elseif ($isScrimDetailView) {
    $appBreadcrumbs = [
        ['label' => 'App', 'href' => 'app.php?view=dashboard'],
        ['label' => 'Scrims', 'href' => 'app.php?view=scrims'],
        ['label' => $currentModule['label'], 'href' => 'app.php?view=' . $view],
    ];
} else {
    $appBreadcrumbs = [
        ['label' => 'App', 'href' => 'app.php?view=dashboard'],
        ['label' => $currentModule['label'], 'href' => 'app.php?view=' . $view],
    ];
}

// Decide per-view UI tweaks
$hideSidebar = in_array($view, ['login', 'register'], true);

require __DIR__ . '/includes/layout-start.php';
$layoutIncluded = true;

// Load the matching page from /pages. We already ensured $view exists in $moduleConfig.
$pageFile = __DIR__ . '/pages/' . $view . '.php';
if (is_file($pageFile)) {
	require $pageFile;
} else {
	// Fallback to dashboard if the specific page file is missing.
	require __DIR__ . '/pages/dashboard.php';
}

require __DIR__ . '/includes/layout-end.php';
