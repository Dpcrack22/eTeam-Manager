<?php
require_once __DIR__ . "/db.php";
require_once __DIR__ . "/auth.php";
require_once __DIR__ . "/profile_functions.php";
$activeSection = $activeSection ?? 'dashboard';

if (!isset($appNavItems)) {
    $appNavItems = [
        'dashboard' => ['label' => 'Dashboard', 'href' => 'app.php?view=dashboard', "showInSidebar" => true],
        'organizations' => ['label' => 'Organizaciones', 'href' => 'app.php?view=organizations', "showInSidebar" => true],
        'teams' => ['label' => 'Equipos', 'href' => 'app.php?view=teams', "showInSidebar" => true],
        'scrims' => ['label' => 'Scrims', 'href' => 'app.php?view=scrims', "showInSidebar" => true],
        'calendar' => ['label' => 'Calendario', 'href' => 'app.php?view=calendar', "showInSidebar" => true],
        'boards' => ['label' => 'Tableros', 'href' => 'app.php?view=boards', "showInSidebar" => true],
        'notes' => ['label' => 'Notas', 'href' => 'app.php?view=notes', "showInSidebar" => true],
        'settings' => ['label' => 'Configuracion', 'href' => 'app.php?view=settings', "showInSidebar" => true],
    ];
}

// Inicializar variables por defecto
$appCurrentUser = [
    'name' => 'Invitado',
    'role' => 'Sin rol',
    'organization' => 'Sin organización',
    'avatar_url' => '/uploads/avatars/default.jpg'
];

if (isset($_SESSION['user']['email'])) {
    $userData = getUserProfile($conn, $_SESSION['user']['email']);
    if ($userData) {
        $appCurrentUser = [
            'name' => $userData['username'] ?? 'Usuario',
            'role' => $userData['role'] ?? 'Sin rol',
            'organization' => $userData['organization_name'] ?? 'Sin organización',
            'avatar_url' => $userData['avatar_url'] ?? '/uploads/avatars/default.jpg'
        ];
    }
}
?>
<aside class="sidebar">
    <div class="sidebar-brand-block">
        <div class="sidebar-brand-title">eTeam Manager</div>
        <p class="small"><?php echo $appAuthState === 'authenticated' ? 'Base interna de la aplicacion' : 'Modo sandbox para revisar layout'; ?></p>
    </div>

    <?php if ($appAuthState === 'authenticated'): ?>
        <div class="card sidebar-panel">
            <div class="small">Navegacion principal</div>
            <nav class="sidebar-nav" aria-label="Navegacion interna">
               <?php foreach ($appNavItems as $sectionKey => $item): ?>
                    <?php if (isset($item['showInSidebar']) && $item['showInSidebar'] === true): ?>
                        <a class="sidebar-link<?php echo $activeSection === $sectionKey ? ' is-active' : ''; ?>" 
                        href="<?php echo htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </nav>
        </div>

        <div class="card sidebar-panel sidebar-user-card">
            <!-- Avatar del usuario -->
            <div class="sidebar-user-avatar">
                <img 
                    src="<?php echo htmlspecialchars($appCurrentUser['avatar_url']); ?>" 
                    alt="Avatar <?php echo htmlspecialchars($appCurrentUser['name']); ?>" 
                />
            </div>

            <!-- Información del usuario -->
            <div class="sidebar-user-info">
                <div class="sidebar-user-name">
                    <?php echo htmlspecialchars($appCurrentUser['name']); ?>
                </div>
                <div class="small" style="font-size:0.8rem; color:#666;">
                    <?php echo htmlspecialchars($appCurrentUser['role']); ?>
                </div>
            </div>

            <!-- Botones de acción -->
            <div class="stack-sm action-buttons">
                <a class="btn btn-secondary btn-sm" href="app.php?view=profile">Cuenta</a>
                <a class="btn btn-primary btn-sm" href="/includes/logout.php">Logout</a>
            </div>
        </div>
    <?php else: ?>
        <div class="card sidebar-panel">
            <div class="small">Estado visitante</div>
            <div class="landing-list" style="margin-top: 12px;">
                <div class="landing-list-item">No hay sesion simulada activa.</div>
                <div class="landing-list-item">Se muestra el layout para pruebas visuales.</div>
                <div class="landing-list-item">La navegacion principal completa aparece dentro de la app.</div>
            </div>
            <div class="stack-sm" style="margin-top: 12px;">
                <a class="btn btn-primary" href="index.php">Ir a login demo</a>
            </div>
        </div>
    <?php endif; ?>

    <div class="card sidebar-panel">
        <div class="small">Estructura base</div>
        <div class="landing-list" style="margin-top: 12px;">
            <div class="landing-list-item">Includes compartidos para layout comun.</div>
            <div class="landing-list-item">Pages separadas por modulo.</div>
            <div class="landing-list-item">Base lista para meter mocks y modulos JS.</div>
        </div>
    </div>
</aside>