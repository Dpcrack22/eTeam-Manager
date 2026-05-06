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
        <div class="sidebar-brand-row">
            <div class="sidebar-brand-title">eTeam Manager</div>
            <button class="sidebar-collapse-button" type="button" data-sidebar-toggle aria-label="Mostrar o ocultar el menú lateral">
                <span class="sidebar-collapse-icon" aria-hidden="true"></span>
            </button>
        </div>
        <p class="small"><?php echo $appAuthState === 'authenticated' ? 'Espacio de trabajo interno' : 'Vista publica del layout'; ?></p>
    </div>

    <?php if ($appAuthState === 'authenticated'): ?>
        <div class="card sidebar-panel" id="sidebar-search-panel">
            <div class="small">Buscar</div>
            <div style="margin-top:8px;">
                <div style="display:flex; gap:8px; align-items:center;">
                    <input id="sidebar-search-input" name="q" type="search" placeholder="Buscar usuarios o equipos" aria-label="Buscar" style="flex:1; padding:8px; border-radius:6px; border:1px solid rgba(255,255,255,0.04); background:transparent; color:var(--text-main);" autocomplete="off" />
                    <select id="sidebar-search-type" name="type" aria-label="Tipo" style="padding:8px; border-radius:6px; background:transparent; border:1px solid rgba(255,255,255,0.04); color:var(--text-main);">
                        <option value="users">Usuarios</option>
                        <option value="teams">Equipos</option>
                    </select>
                </div>
                <div id="sidebar-search-suggestions" class="sidebar-search-suggestions" aria-hidden="true"></div>
            </div>
        </div>
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

        <div class="card sidebar-panel sidebar-context-card">
            <div class="small">Contexto activo</div>
            <div class="sidebar-context-title"><?php echo htmlspecialchars($appCurrentUser['team'], ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="small">El dashboard, los scrims y el calendario leen este equipo como referencia.</div>
            <div class="stack-sm" style="margin-top: 12px;">
                <a class="btn btn-secondary" href="app.php?view=team-detail">Abrir detalle</a>
                <button class="btn btn-primary" type="button" data-open-team-switcher>Cambiar equipo</button>
            </div>
        </div>
    <?php else: ?>
        <div class="card sidebar-panel">
            <div class="small">Acceso pendiente</div>
            <div class="landing-list" style="margin-top: 12px;">
                <div class="landing-list-item">La sesión no está iniciada.</div>
                <div class="landing-list-item">La navegación completa se desbloquea tras autenticarte.</div>
            </div>
        </div>
    <?php endif; ?>
</aside>