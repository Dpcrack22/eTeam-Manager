<?php
$allowedSections = [
    'dashboard',
    'organizations',
    'teams',
    'scrims',
    'calendar',
    'boards',
    'notes',
    'settings',
];

$activeSection = isset($_GET['section']) ? strtolower((string) $_GET['section']) : 'dashboard';
if (!in_array($activeSection, $allowedSections, true)) {
    $activeSection = 'dashboard';
}

$pageTitle = isset($_GET['title']) && $_GET['title'] !== '' ? (string) $_GET['title'] : 'Prueba de includes';
$pageEyebrow = isset($_GET['eyebrow']) && $_GET['eyebrow'] !== '' ? (string) $_GET['eyebrow'] : 'Sandbox visual';
$pageDescription = isset($_GET['description']) && $_GET['description'] !== '' ? (string) $_GET['description'] : 'Esta pagina sirve para probar el comportamiento del layout interno y de los includes reutilizables.';
$appAuthState = isset($_GET['state']) && $_GET['state'] === 'guest' ? 'guest' : 'authenticated';
$appCurrentUser = [
    'name' => 'Sandbox User',
    'role' => 'Tester',
    'organization' => 'Entorno de pruebas',
];

$appNavItems = [
    'dashboard' => ['label' => 'Dashboard', 'href' => 'test-includes.php?section=dashboard'],
    'organizations' => ['label' => 'Organizaciones', 'href' => 'test-includes.php?section=organizations'],
    'teams' => ['label' => 'Equipos', 'href' => 'test-includes.php?section=teams'],
    'scrims' => ['label' => 'Scrims', 'href' => 'test-includes.php?section=scrims'],
    'calendar' => ['label' => 'Calendario', 'href' => 'test-includes.php?section=calendar'],
    'boards' => ['label' => 'Tableros', 'href' => 'test-includes.php?section=boards'],
    'notes' => ['label' => 'Notas', 'href' => 'test-includes.php?section=notes'],
    'settings' => ['label' => 'Configuracion', 'href' => 'test-includes.php?section=settings'],
];

require __DIR__ . '/includes/layout-start.php';
?>

<div class="grid-2">
    <div class="card">
        <h2 class="h3">Panel de pruebas</h2>
        <p>Desde aqui puedes comprobar como responden los includes de la app interna sin tocar los archivos reales.</p>

        <form class="form" action="test-includes.php" method="get">
            <div class="field">
                <label for="section">Seccion activa del sidebar</label>
                <select id="section" name="section">
                    <?php foreach ($allowedSections as $sectionOption): ?>
                        <option value="<?php echo htmlspecialchars($sectionOption, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $activeSection === $sectionOption ? ' selected' : ''; ?>><?php echo htmlspecialchars($sectionOption, ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label for="title">Titulo de la pagina</label>
                <input id="title" name="title" type="text" value="<?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?>">
            </div>

            <div class="field">
                <label for="eyebrow">Etiqueta superior</label>
                <input id="eyebrow" name="eyebrow" type="text" value="<?php echo htmlspecialchars($pageEyebrow, ENT_QUOTES, 'UTF-8'); ?>">
            </div>

            <div class="field">
                <label for="description">Descripcion</label>
                <textarea id="description" name="description"><?php echo htmlspecialchars($pageDescription, ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>

            <div class="field">
                <label for="state">Estado visual</label>
                <select id="state" name="state">
                    <option value="authenticated"<?php echo $appAuthState === 'authenticated' ? ' selected' : ''; ?>>authenticated</option>
                    <option value="guest"<?php echo $appAuthState === 'guest' ? ' selected' : ''; ?>>guest</option>
                </select>
            </div>

            <div class="stack-sm">
                <button class="btn btn-primary" type="submit">Aplicar cambios</button>
                <a class="btn btn-secondary" href="test-includes.php">Restablecer</a>
            </div>
        </form>
    </div>

    <div class="card">
        <h2 class="h3">Includes que estas probando</h2>
        <div class="landing-list">
            <div class="landing-list-item">includes/head.php</div>
            <div class="landing-list-item">includes/sidebar.php</div>
            <div class="landing-list-item">includes/header.php</div>
            <div class="landing-list-item">includes/footer.php</div>
            <div class="landing-list-item">includes/layout-start.php y includes/layout-end.php</div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/layout-end.php'; ?>