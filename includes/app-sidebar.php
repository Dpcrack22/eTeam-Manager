<?php
$activeSection = $activeSection ?? 'dashboard';
$appNavItems = [
    'dashboard' => ['label' => 'Dashboard', 'href' => 'app.php?view=dashboard'],
    'organizations' => ['label' => 'Organizaciones', 'href' => 'app.php?view=organizations'],
    'teams' => ['label' => 'Equipos', 'href' => 'app.php?view=teams'],
    'scrims' => ['label' => 'Scrims', 'href' => 'app.php?view=scrims'],
    'calendar' => ['label' => 'Calendario', 'href' => 'app.php?view=calendar'],
    'boards' => ['label' => 'Tableros', 'href' => 'app.php?view=boards'],
    'notes' => ['label' => 'Notas', 'href' => 'app.php?view=notes'],
    'settings' => ['label' => 'Configuracion', 'href' => 'app.php?view=settings'],
];
?>
<aside class="sidebar">
    <div class="sidebar-brand-block">
        <div class="sidebar-brand-title">eTeam Manager</div>
        <p class="small">Base interna de la aplicacion</p>
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

    <div class="card sidebar-panel">
        <div class="small">Estado actual</div>
        <div class="stack-sm" style="margin-top: 12px;">
            <span class="badge">Sprint 1</span>
            <span class="badge badge-info">Frontend base</span>
            <span class="badge badge-warning">Mocks proximamente</span>
        </div>
    </div>
</aside>