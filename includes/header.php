<?php
$pageEyebrow = $pageEyebrow ?? 'App interna';
$pageTitle = $pageTitle ?? 'Dashboard';
$pageDescription = $pageDescription ?? 'Resumen operativo del modulo actual.';
?>
<header class="topbar">
    <div class="container app-topbar-inner">
        <div class="app-topbar-left">
            <button class="app-sidebar-toggle" type="button" data-sidebar-toggle aria-label="Mostrar o ocultar el menú lateral">
                <span class="app-sidebar-toggle-icon" aria-hidden="true"></span>
            </button>

            <details class="app-brand-dropdown">
                <summary class="app-brand-button">
                    <img class="app-brand-logo" src="assets/mini-logo.svg?v=<?php echo (int) (file_exists(__DIR__ . '/../assets/mini-logo.svg') ? filemtime(__DIR__ . '/../assets/mini-logo.svg') : time()); ?>" alt="eTeam Manager" />
                    <span class="app-brand-copy">
                        <span class="app-brand-name">eTeam Manager</span>
                        <span class="app-brand-subtitle">Acceso rápido</span>
                    </span>
                    <span class="app-brand-caret" aria-hidden="true">▾</span>
                </summary>
                <div class="app-brand-menu">
                    <a class="app-brand-menu-item" href="index.php">Inicio</a>
                    <a class="app-brand-menu-item" href="app.php?view=dashboard">Dashboard</a>
                    <a class="app-brand-menu-item" href="app.php?view=teams">Equipos</a>
                    <a class="app-brand-menu-item" href="app.php?view=calendar">Calendario</a>
                    <a class="app-brand-menu-item" href="app.php?view=settings">Perfil</a>
                    <a class="app-brand-menu-item" href="includes/logout.php">Salir</a>
                </div>
            </details>

            <div>
                <div class="app-breadcrumbs" aria-label="Ruta actual">
                    <?php foreach ($appBreadcrumbs as $breadcrumb): ?>
                        <a class="app-breadcrumb-link" href="<?php echo htmlspecialchars($breadcrumb['href'], ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo htmlspecialchars($breadcrumb['label'], ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
                <div class="small"><?php echo htmlspecialchars($pageEyebrow, ENT_QUOTES, 'UTF-8'); ?></div>
                <h1 class="app-topbar-title"><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></h1>
                <p class="small app-topbar-copy"><?php echo htmlspecialchars($pageDescription, ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
        </div>
        <div class="app-topbar-actions">
            <?php if ($appAuthState === 'authenticated'): ?>
                <a class="app-user-chip" href="app.php?view=settings" aria-label="Abrir perfil de usuario">
                    <span class="app-user-avatar">
                        <?php if (!empty($appCurrentUser['avatar_url'])): ?>
                            <img src="<?php echo htmlspecialchars($appCurrentUser['avatar_url'], ENT_QUOTES, 'UTF-8'); ?>" alt="Avatar de <?php echo htmlspecialchars($appCurrentUser['name'], ENT_QUOTES, 'UTF-8'); ?>" />
                        <?php else: ?>
                            <span><?php echo htmlspecialchars($appCurrentUser['initials'] ?? 'EM', ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php endif; ?>
                    </span>
                    <span class="app-user-meta">
                        <span class="app-user-name"><?php echo htmlspecialchars($appCurrentUser['name'], ENT_QUOTES, 'UTF-8'); ?></span>
                        <span class="app-user-role"><?php echo htmlspecialchars($appCurrentUser['role'], ENT_QUOTES, 'UTF-8'); ?> · <?php echo htmlspecialchars($appCurrentUser['team'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </span>
                </a>
            <?php else: ?>
                <div class="app-state-chip is-guest">Vista publica</div>
                <a class="btn btn-secondary" href="index.php">Volver a la landing</a>
            <?php endif; ?>
        </div>
    </div>
</header>