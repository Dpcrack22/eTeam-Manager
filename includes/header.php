<?php
$pageEyebrow = $pageEyebrow ?? 'App interna';
$pageTitle = $pageTitle ?? 'Dashboard';
$pageDescription = $pageDescription ?? 'Resumen operativo del modulo actual.';
?>
<header class="topbar">
    <div class="container app-topbar-inner">
        <div class="app-topbar-left">
            <div class="app-topbar-context">
                <div class="small"><?php echo htmlspecialchars($pageEyebrow, ENT_QUOTES, 'UTF-8'); ?></div>
                <h1 class="app-topbar-title"><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></h1>
            </div>
        </div>
        <div class="app-topbar-actions">
            <?php if ($appAuthState === 'authenticated'): ?>
                <details class="app-user-dropdown">
                    <summary class="app-user-chip" aria-label="Abrir menú de perfil">
                        <span class="app-user-avatar">
                            <?php if (!empty($appCurrentUser['avatar_url'])): ?>
                                <img src="<?php echo htmlspecialchars($appCurrentUser['avatar_url'], ENT_QUOTES, 'UTF-8'); ?>" alt="Avatar de <?php echo htmlspecialchars($appCurrentUser['name'], ENT_QUOTES, 'UTF-8'); ?>" />
                            <?php else: ?>
                                <span><?php echo htmlspecialchars($appCurrentUser['initials'] ?? 'EM', ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php endif; ?>
                        </span>
                        <span class="app-user-meta">
                            <span class="app-user-name"><?php echo htmlspecialchars($appCurrentUser['name'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <span class="app-user-role"><?php echo htmlspecialchars($appCurrentUser['role'], ENT_QUOTES, 'UTF-8'); ?></span>
                        </span>
                        <span class="app-user-caret" aria-hidden="true">▾</span>
                    </summary>
                    <div class="app-user-menu">
                        <div class="app-user-menu-head">
                            <strong><?php echo htmlspecialchars($appCurrentUser['name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                            <span><?php echo htmlspecialchars($appCurrentUser['team'], ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                        <a class="app-user-menu-item" href="index.php">Inicio</a>
                        <a class="app-user-menu-item" href="app.php?view=dashboard">Dashboard</a>
                        <a class="app-user-menu-item" href="app.php?view=teams">Equipos</a>
                        <a class="app-user-menu-item" href="app.php?view=calendar">Calendario</a>
                        <a class="app-user-menu-item" href="app.php?view=settings">Mi perfil</a>
                        <a class="app-user-menu-item" href="includes/logout.php">Cerrar sesión</a>
                    </div>
                </details>
            <?php else: ?>
                <div class="app-state-chip is-guest">Vista publica</div>
                <a class="btn btn-secondary" href="index.php">Volver a la landing</a>
            <?php endif; ?>
        </div>
    </div>
</header>