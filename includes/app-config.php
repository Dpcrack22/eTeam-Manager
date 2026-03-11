<?php
$view = isset($view) ? strtolower((string) $view) : (isset($_GET['view']) ? strtolower((string) $_GET['view']) : 'dashboard');

$appModules = [
    'dashboard' => [
        'label' => 'Dashboard',
        'title' => 'Dashboard base',
        'eyebrow' => 'App interna',
        'description' => 'Resumen inicial del sistema con acceso al contexto principal de trabajo.',
        'page' => __DIR__ . '/../pages/dashboard.php',
    ],
    'organizations' => [
        'label' => 'Organizaciones',
        'title' => 'Organizaciones',
        'eyebrow' => 'Modulo',
        'description' => 'Gestion visual de organizaciones, miembros y roles internos.',
        'page' => __DIR__ . '/../pages/organizations.php',
    ],
    'teams' => [
        'label' => 'Equipos',
        'title' => 'Equipos',
        'eyebrow' => 'Modulo',
        'description' => 'Gestion de roster, detalle de equipos y relacion con la organizacion activa.',
        'page' => __DIR__ . '/../pages/teams.php',
    ],
    'scrims' => [
        'label' => 'Scrims',
        'title' => 'Scrims',
        'eyebrow' => 'Modulo',
        'description' => 'Registro de enfrentamientos, resultados, mapas y detalle competitivo.',
        'page' => __DIR__ . '/../pages/scrims.php',
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
];

if (!isset($appModules[$view])) {
    $view = 'dashboard';
}

$currentModule = $appModules[$view];
$activeSection = $activeSection ?? $view;
$pageTitle = $pageTitle ?? $currentModule['title'];
$pageEyebrow = $pageEyebrow ?? $currentModule['eyebrow'];
$pageDescription = $pageDescription ?? $currentModule['description'];
$appAuthState = $appAuthState ?? 'authenticated';
$appCurrentUser = $appCurrentUser ?? [
    'name' => 'Demo User',
    'role' => 'Manager',
    'organization' => 'Parallax Esports',
];

if (!isset($appNavItems)) {
    $appNavItems = [];

    foreach ($appModules as $moduleKey => $module) {
        $appNavItems[$moduleKey] = [
            'label' => $module['label'],
            'href' => 'app.php?view=' . $moduleKey,
        ];
    }
}

$appBreadcrumbs = [
    ['label' => 'App', 'href' => 'app.php?view=dashboard'],
    ['label' => $currentModule['label'], 'href' => 'app.php?view=' . $view],
];