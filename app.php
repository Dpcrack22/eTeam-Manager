<?php
$view = isset($_GET['view']) ? strtolower((string) $_GET['view']) : 'dashboard';

require __DIR__ . '/includes/app-config.php';
require __DIR__ . '/includes/layout-start.php';
require $currentModule['page'];
require __DIR__ . '/includes/layout-end.php';
$moduleConfig = [
	'dashboard' => [
		'title' => 'Dashboard base',
		'eyebrow' => 'App interna',
		'description' => 'Resumen inicial del sistema con acceso al contexto principal de trabajo.',
		'headline' => 'Base de la parte interna',
		'summary' => 'Esta vista sirve como punto de entrada al entorno interno. Aqui se concentraran organizacion activa, equipo activo, eventos proximos, scrims recientes y tareas pendientes.',
		'next' => [
			'Organizacion activa y equipo activo.',
			'Resumen operativo del dia.',
			'Accesos rapidos a modulos principales.',
		],
	],
	'organizations' => [
		'title' => 'Organizaciones',
		'eyebrow' => 'Modulo',
		'description' => 'Gestion visual de organizaciones, miembros y roles internos.',
		'headline' => 'Gestion de organizaciones',
		'summary' => 'Desde aqui se podran crear organizaciones, editar sus datos, elegir la organizacion activa y gestionar miembros con sus roles y permisos.',
		'next' => [
			'Listado y seleccion de organizaciones.',
			'Formulario de alta y edicion.',
			'Vista de miembros y cambio de roles.',
		],
	],
	'teams' => [
		'title' => 'Equipos',
		'eyebrow' => 'Modulo',
		'description' => 'Gestion de roster, detalle de equipos y relacion con la organizacion activa.',
		'headline' => 'Gestion de equipos',
		'summary' => 'Este modulo servira para ver todos los teams de una organizacion, crear rosters y trabajar el detalle competitivo de cada equipo.',
		'next' => [
			'Listado de equipos por organizacion.',
			'Detalle del roster y roles internos.',
			'Base para conectar scrims, boards y notas.',
		],
	],
	'scrims' => [
		'title' => 'Scrims',
		'eyebrow' => 'Modulo',
		'description' => 'Registro de enfrentamientos, resultados, mapas y detalle competitivo.',
		'headline' => 'Gestion de scrims',
		'summary' => 'Aqui se podra llevar historial de scrims, registrar rivales, resultados, score y mapas jugados para revisar trabajo competitivo.',
		'next' => [
			'Listado de scrims por equipo.',
			'Formulario de alta y edicion.',
			'Detalle de partido con mapas y notas.',
		],
	],
	'calendar' => [
		'title' => 'Calendario',
		'eyebrow' => 'Modulo',
		'description' => 'Planificacion de eventos de equipo y organizacion con estados de asistencia.',
		'headline' => 'Gestion de calendario',
		'summary' => 'Este modulo organizara scrims, entrenamientos, torneos, practicas y reuniones para que el equipo tenga una planificacion clara.',
		'next' => [
			'Vista de calendario y listado.',
			'Alta y edicion de eventos.',
			'Control de asistencia de miembros.',
		],
	],
	'boards' => [
		'title' => 'Tableros',
		'eyebrow' => 'Modulo',
		'description' => 'Kanban interno para tareas, prioridades y seguimiento del trabajo.',
		'headline' => 'Gestion de tareas',
		'summary' => 'Aqui se organizara el trabajo interno del staff y del equipo mediante tableros con columnas, prioridades, responsables y fechas limite.',
		'next' => [
			'Board por equipo.',
			'Tareas con prioridad y asignado.',
			'Movimiento entre columnas.',
		],
	],
	'notes' => [
		'title' => 'Notas',
		'eyebrow' => 'Modulo',
		'description' => 'Repositorio interno para estrategia, analisis y documentacion del equipo.',
		'headline' => 'Notas estrategicas',
		'summary' => 'Este espacio servira para guardar analisis de rivales, ideas tacticas, repaso de mapas y documentacion relevante del roster.',
		'next' => [
			'Listado de notas.',
			'CRUD visual de contenido.',
			'Tags y filtros por tipo de nota.',
		],
	],
	'settings' => [
		'title' => 'Perfil y ajustes',
		'eyebrow' => 'Modulo',
		'description' => 'Configuracion del usuario, preferencias y datos personales.',
		'headline' => 'Cuenta y configuracion',
		'summary' => 'Aqui se agruparan los datos del usuario como avatar, correo, contrasena y otras preferencias relacionadas con la cuenta.',
		'next' => [
			'Vista de perfil.',
			'Ajustes de cuenta.',
			'Preferencias basicas del usuario.',
		],
	],
	'login' => [
		'title' => 'Login',
		'eyebrow' => 'Acceso',
		'description' => 'Inicia sesión en eTeam Manager',
	],
	'register' => [
		'title' => 'Registro',
		'eyebrow' => 'Crear cuenta',
		'description' => 'Registra una cuenta de demo en eTeam Manager',
	],
];

if (!isset($moduleConfig[$view])) {
	$view = 'dashboard';
}

$currentModule = $moduleConfig[$view];
$pageTitle = $currentModule['title'];
$pageEyebrow = $currentModule['eyebrow'];
$pageDescription = $currentModule['description'];
$activeSection = $view;

// Decide per-view UI tweaks
$hideSidebar = in_array($view, ['login', 'register'], true);

require __DIR__ . '/includes/app-layout-start.php';
$layoutIncluded = true;

// Load the matching page from /pages. We already ensured $view exists in $moduleConfig.
$pageFile = __DIR__ . '/pages/' . $view . '.php';
if (is_file($pageFile)) {
	require $pageFile;
} else {
	// Fallback to dashboard if the specific page file is missing.
	require __DIR__ . '/pages/dashboard.php';
}

require __DIR__ . '/includes/app-layout-end.php';
