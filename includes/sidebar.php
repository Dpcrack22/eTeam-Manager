<?php
$activeSection = $activeSection ?? 'dashboard';

if (!isset($appNavItems)) {
    $appNavItems = [
        'dashboard' => ['label' => 'Dashboard', 'href' => 'app.php?view=dashboard'],
        'teams' => ['label' => 'Equipos', 'href' => 'app.php?view=teams'],
        'scrims' => ['label' => 'Scrims', 'href' => 'app.php?view=scrims'],
        'calendar' => ['label' => 'Calendario', 'href' => 'app.php?view=calendar'],
        'boards' => ['label' => 'Tableros', 'href' => 'app.php?view=boards'],
        'notes' => ['label' => 'Notas', 'href' => 'app.php?view=notes'],
        'settings' => ['label' => 'Configuracion', 'href' => 'app.php?view=settings'],
    ];
}
?>
<aside class="sidebar">
    <div class="sidebar-brand-block">
        <div class="sidebar-brand-title">eTeam Manager</div>
        <p class="small"><?php echo $appAuthState === 'authenticated' ? 'Espacio de trabajo interno' : 'Vista publica del layout'; ?></p>
    </div>

    <?php if ($appAuthState === 'authenticated'): ?>
        <div class="card sidebar-panel">
            <div class="small">Navegacion principal</div>
            <nav class="sidebar-nav" aria-label="Navegacion interna">
                <?php foreach ($appNavItems as $sectionKey => $item): ?>
                    <a class="sidebar-link<?php echo $activeSection === $sectionKey ? ' is-active' : ''; ?>" href="<?php echo htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8'); ?>
                    </a>
                <?php endforeach; ?>
            </nav>
        </div>

        <div class="card sidebar-panel sidebar-user-card">
            <div class="small">Sesion actual</div>
            <div class="sidebar-user-name"><?php echo htmlspecialchars($appCurrentUser['name'], ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="small"><?php echo htmlspecialchars($appCurrentUser['role'], ENT_QUOTES, 'UTF-8'); ?> · <?php echo htmlspecialchars($appCurrentUser['team'], ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="stack-sm" style="margin-top: 12px;">
                <a class="btn btn-secondary" href="app.php?view=settings">Mi cuenta</a>
                <a class="btn btn-primary" href="includes/logout.php">Salir</a>
            </div>
        </div>
    <?php else: ?>
        <div class="card sidebar-panel">
            <div class="small">Acceso pendiente</div>
            <div class="landing-list" style="margin-top: 12px;">
                <div class="landing-list-item">La sesión no está iniciada.</div>
                <div class="landing-list-item">La navegación completa se desbloquea tras autenticarte.</div>
                <div class="landing-list-item">Este panel solo muestra la estructura pública.</div>
            </div>
            <div class="stack-sm" style="margin-top: 12px;">
                <a class="btn btn-primary" href="app.php?view=login">Ir al acceso</a>
            </div>
        </div>
    <?php endif; ?>

    <div class="card sidebar-panel">
        <div class="small">Estructura base</div>
        <div class="landing-list" style="margin-top: 12px;">
            <div class="landing-list-item">Includes compartidos para layout comun.</div>
            <div class="landing-list-item">Pages separadas por modulo.</div>
            <div class="landing-list-item">Base lista para nuevos módulos y vistas internas.</div>
        </div>
    </div>
</aside>