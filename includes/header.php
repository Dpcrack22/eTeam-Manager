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
                <details class="app-notification-dropdown">
                    <summary class="app-notification-chip" aria-label="Abrir notificaciones">
                        <span class="app-notification-icon" aria-hidden="true">🔔</span>
                        <span class="app-notification-label">Avisos</span>
                        <?php if (!empty($appUnreadNotificationCount)): ?>
                            <span class="app-notification-badge"><?php echo (int) $appUnreadNotificationCount; ?></span>
                        <?php endif; ?>
                    </summary>
                    <div class="app-notification-menu">
                        <div class="app-notification-menu-head">
                            <strong>Notificaciones</strong>
                            <span><?php echo !empty($appUnreadNotificationCount) ? (int) $appUnreadNotificationCount . ' sin leer' : 'Todo al día'; ?></span>
                        </div>

                        <?php if (empty($appNotifications)): ?>
                            <div class="app-notification-empty">No hay avisos recientes.</div>
                        <?php else: ?>
                            <?php foreach ($appNotifications as $notification): ?>
                                <a class="app-notification-item<?php echo empty($notification['is_read']) ? ' is-unread' : ''; ?>" href="app.php?view=notifications">
                                    <span class="app-notification-item-message"><?php echo htmlspecialchars((string) $notification['message'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    <span class="app-notification-item-meta"><?php echo htmlspecialchars(notificationTypeLabel((string) $notification['type']), ENT_QUOTES, 'UTF-8'); ?> · <?php echo htmlspecialchars(date('d/m/Y H:i', strtotime((string) $notification['created_at'])), ENT_QUOTES, 'UTF-8'); ?></span>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>

                        <div class="app-notification-actions">
                            <a class="app-user-menu-item" href="app.php?view=notifications">Ver todas</a>
                        </div>
                    </div>
                </details>

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