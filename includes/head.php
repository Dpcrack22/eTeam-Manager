<?php
$resolvedTitle = isset($pageTitle) ? $pageTitle . ' | eTeam Manager' : 'eTeam Manager';
$resolvedDescription = isset($pageDescription) ? trim((string) $pageDescription) : 'eTeam Manager, plataforma interna para equipos, scrims, calendario y organización de eSports.';
$siteLogoPath = 'assets/mini-logo.svg';
$mainCssPath = __DIR__ . '/../css/main.css';
$siteLogoFile = __DIR__ . '/../' . $siteLogoPath;
$mainCssVersion = is_file($mainCssPath) ? filemtime($mainCssPath) : time();
$siteLogoVersion = is_file($siteLogoFile) ? filemtime($siteLogoFile) : time();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($resolvedTitle, ENT_QUOTES, 'UTF-8'); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($resolvedDescription, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="keywords" content="eSports, gestión de equipos, scrims, calendario, kanban, notas, eTeam Manager">
    <meta name="application-name" content="eTeam Manager">
    <meta name="apple-mobile-web-app-title" content="eTeam Manager">
    <meta name="theme-color" content="#FF4655">
    <meta name="robots" content="index,follow">
    <meta property="og:site_name" content="eTeam Manager">
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?php echo htmlspecialchars($resolvedTitle, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($resolvedDescription, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:image" content="<?php echo htmlspecialchars($siteLogoPath, ENT_QUOTES, 'UTF-8'); ?>?v=<?php echo (int) $siteLogoVersion; ?>">
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="<?php echo htmlspecialchars($resolvedTitle, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="twitter:description" content="<?php echo htmlspecialchars($resolvedDescription, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="twitter:image" content="<?php echo htmlspecialchars($siteLogoPath, ENT_QUOTES, 'UTF-8'); ?>?v=<?php echo (int) $siteLogoVersion; ?>">
    <link rel="icon" type="image/svg+xml" href="<?php echo htmlspecialchars($siteLogoPath, ENT_QUOTES, 'UTF-8'); ?>?v=<?php echo (int) $siteLogoVersion; ?>">
    <link rel="apple-touch-icon" href="<?php echo htmlspecialchars($siteLogoPath, ENT_QUOTES, 'UTF-8'); ?>?v=<?php echo (int) $siteLogoVersion; ?>">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css?v=<?php echo (int) $mainCssVersion; ?>">
</head>
<body>