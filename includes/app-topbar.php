<?php
$pageEyebrow = $pageEyebrow ?? 'App interna';
$pageTitle = $pageTitle ?? 'Dashboard';
$pageDescription = $pageDescription ?? 'Base inicial de la aplicacion.';
?>
<header class="topbar">
    <div class="container app-topbar-inner">
        <div>
            <div class="small"><?php echo htmlspecialchars($pageEyebrow, ENT_QUOTES, 'UTF-8'); ?></div>
            <h1 class="h2" style="margin: 0;"><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></h1>
            <p class="small app-topbar-copy"><?php echo htmlspecialchars($pageDescription, ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
        <div class="app-topbar-actions">
            <a class="btn btn-secondary" href="index.php">Volver a la landing</a>
            <a class="btn btn-primary" href="ui-kit.html">UI Kit</a>
        </div>
    </div>
</header>