<?php
$view = isset($_GET['view']) ? strtolower((string) $_GET['view']) : 'dashboard';

require __DIR__ . '/includes/app-config.php';
require __DIR__ . '/includes/layout-start.php';
require $currentModule['page'];
require __DIR__ . '/includes/layout-end.php';