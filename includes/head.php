<?php
$resolvedTitle = isset($pageTitle) ? $pageTitle . ' | eTeam Manager' : 'eTeam Manager';
$mainCssPath = __DIR__ . '/../css/main.css';
$mainCssVersion = is_file($mainCssPath) ? filemtime($mainCssPath) : time();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($resolvedTitle, ENT_QUOTES, 'UTF-8'); ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css?v=<?php echo (int) $mainCssVersion; ?>">
</head>
<body>