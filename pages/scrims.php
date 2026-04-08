<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/organization_functions.php';
require_once __DIR__ . '/../includes/scrim_functions.php';
require_once __DIR__ . '/../includes/team_functions.php';

requireAuth();

global $conn;

$currentUser = $_SESSION['user'] ?? [];
$userId = (int) ($currentUser['id'] ?? 0);
$activeOrganizationId = getActiveOrganizationId($conn, $userId);
$activeOrganization = $activeOrganizationId ? getOrganizationById($conn, $activeOrganizationId, $userId) : false;

if (!$activeOrganization) {
    $activeOrganization = [
        'name' => 'Sin organización',
        'slug' => 'sin-organizacion',
    ];
}

$activeTeamId = $activeOrganizationId ? getActiveTeamId($conn, (int) $activeOrganizationId) : null;
$activeTeam = null;

if ($activeOrganizationId && $activeTeamId !== null) {
    $activeTeam = getTeamById($conn, $activeTeamId, (int) $activeOrganizationId);
}

$statusFilter = strtolower(trim((string) ($_GET['status'] ?? 'all')));
$allowedStatusFilters = array_column(getScrimStatusOptions(), 'key');
if (!in_array($statusFilter, $allowedStatusFilters, true)) {
    $statusFilter = 'all';
}

$searchQuery = trim((string) ($_GET['q'] ?? ''));
$scrims = [];
$summary = [
    'total' => 0,
    'pending' => 0,
    'win' => 0,
    'loss' => 0,
    'draw' => 0,
];

if ($activeTeamId !== null) {
    $scrims = getTeamScrims($conn, (int) $activeTeamId, $statusFilter, $searchQuery);

    $allScrims = getTeamScrims($conn, (int) $activeTeamId);
    $summary['total'] = count($allScrims);
    foreach ($allScrims as $scrim) {
        if (isset($summary[$scrim['result']])) {
            $summary[$scrim['result']]++;
        }
    }
}

$pageScripts[] = 'js/modules/scrims.js';
$pageTitle = 'Scrims';
$pageEyebrow = 'Modulo';
$pageDescription = 'Listado competitivo del roster activo con filtros por estado, acceso al detalle y entrada al formulario de alta o edición.';
$activeSection = 'scrims';
$statusOptions = getScrimStatusOptions();
$successMessage = '';

if (!empty($_SESSION['flash_success'])) {
    $successMessage = (string) $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}
?>

<section class="scrims-page" data-scrims-root>
    <div class="dashboard-hero card">
        <div>
            <div class="small">Historial competitivo</div>
            <h2 class="h2">Scrims y detalle competitivo</h2>
            <p>Este módulo reúne historial, próximos scrims y acceso al detalle por mapas para seguir el rendimiento del equipo activo.</p>
            <div class="stack-sm">
                <span class="badge badge-info">Listado</span>
                <span class="badge badge-success">Alta y edición</span>
                <span class="badge badge-warning">Detalle por mapas</span>
            </div>
        </div>
        <div class="dashboard-hero-meta">
            <div class="dashboard-hero-chip">
                <div class="small">Equipo activo</div>
                <div class="dashboard-hero-value"><?php echo htmlspecialchars((string) ($activeTeam['name'] ?? 'Sin equipo'), ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
            <div class="dashboard-hero-chip">
                <div class="small">Scrims registrados</div>
                <div class="dashboard-hero-value"><?php echo (int) $summary['total']; ?></div>
            </div>
        </div>
    </div>

    <?php if (!empty($successMessage)): ?>
        <div class="error-box app-feedback app-feedback-success" data-flash-message role="status" aria-live="polite">
            <?php echo htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <?php if ($activeTeamId === null): ?>
        <div class="card dashboard-empty-state">
            Todavía no hay un equipo activo. Entra en Equipos para marcar uno y poder listar y crear scrims.
        </div>
    <?php else: ?>
        <div class="scrim-summary-strip">
            <article class="card scrim-summary-card">
                <div class="small">Total</div>
                <div class="scrim-summary-value"><?php echo (int) $summary['total']; ?></div>
                <div class="small">Scrims ligados al equipo activo.</div>
            </article>
            <article class="card scrim-summary-card">
                <div class="small">Victorias</div>
                <div class="scrim-summary-value"><?php echo (int) $summary['win']; ?></div>
                <div class="small">Resultados positivos ya registrados.</div>
            </article>
            <article class="card scrim-summary-card">
                <div class="small">Derrotas</div>
                <div class="scrim-summary-value"><?php echo (int) $summary['loss']; ?></div>
                <div class="small">Para revisar errores y patrones.</div>
            </article>
            <article class="card scrim-summary-card">
                <div class="small">Pendientes</div>
                <div class="scrim-summary-value"><?php echo (int) $summary['pending']; ?></div>
                <div class="small">Scrims aún sin cerrar.</div>
            </article>
        </div>

        <div class="card scrim-toolbar">
            <form class="scrim-toolbar-form" method="get" novalidate>
                <input type="hidden" name="view" value="scrims" />

                <div class="field">
                    <label for="scrim_search">Buscar</label>
                    <input id="scrim_search" name="q" type="search" value="<?php echo htmlspecialchars($searchQuery, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Rival, modo o tag" data-scrim-search />
                </div>

                <div class="field">
                    <label for="scrim_status">Estado</label>
                    <select id="scrim_status" name="status" data-scrim-status>
                        <?php foreach ($statusOptions as $option): ?>
                            <option value="<?php echo htmlspecialchars($option['key'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo $statusFilter === $option['key'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($option['label'], ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="scrim-toolbar-actions">
                    <button class="btn btn-primary" type="submit">Aplicar filtros</button>
                    <a class="btn btn-secondary" href="app.php?view=scrims">Limpiar</a>
                    <a class="btn btn-secondary" href="app.php?view=scrim-form">Nuevo scrim</a>
                </div>
            </form>
        </div>

        <div class="card scrim-list-shell">
            <div class="dashboard-section-head">
                <div>
                    <div class="small">Historial</div>
                    <h3 class="h3">Listado de scrims</h3>
                </div>
                <div class="small">Visibles: <span data-scrims-visible-count><?php echo count($scrims); ?></span></div>
            </div>

            <?php if (empty($scrims)): ?>
                <div class="dashboard-empty-state">
                    No hay scrims para el filtro actual. Prueba con otro estado o crea el primero desde el formulario.
                </div>
            <?php else: ?>
                <div class="scrim-list-grid">
                    <?php foreach ($scrims as $scrim): ?>
                        <article class="scrim-list-item" data-scrim-row data-status="<?php echo htmlspecialchars($scrim['result'], ENT_QUOTES, 'UTF-8'); ?>" data-search="<?php echo htmlspecialchars(strtolower($scrim['opponent_name'] . ' ' . ($scrim['opponent_tag'] ?? '') . ' ' . $scrim['game_mode_name']), ENT_QUOTES, 'UTF-8'); ?>">
                            <div class="scrim-list-main">
                                <div class="scrim-list-title-row">
                                    <div>
                                        <div class="scrim-table-opponent"><?php echo htmlspecialchars($scrim['opponent_name'], ENT_QUOTES, 'UTF-8'); ?></div>
                                        <div class="small"><?php echo !empty($scrim['opponent_tag']) ? htmlspecialchars($scrim['opponent_tag'], ENT_QUOTES, 'UTF-8') : 'Sin tag'; ?></div>
                                    </div>
                                    <span class="badge <?php echo htmlspecialchars($scrim['result_badge'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($scrim['result_label'], ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>

                                <div class="scrim-list-meta-grid">
                                    <div class="scrim-list-meta-item">
                                        <div class="small">Fecha</div>
                                        <div><?php echo htmlspecialchars($scrim['match_date_label'], ENT_QUOTES, 'UTF-8'); ?></div>
                                    </div>
                                    <div class="scrim-list-meta-item">
                                        <div class="small">Modo</div>
                                        <div><?php echo htmlspecialchars($scrim['game_mode_name'], ENT_QUOTES, 'UTF-8'); ?></div>
                                    </div>
                                    <div class="scrim-list-meta-item">
                                        <div class="small">Mapas</div>
                                        <div><?php echo (int) $scrim['maps_count']; ?></div>
                                    </div>
                                    <div class="scrim-list-meta-item">
                                        <div class="small">Score</div>
                                        <div class="scrim-list-score"><?php echo htmlspecialchars($scrim['score_label'], ENT_QUOTES, 'UTF-8'); ?></div>
                                    </div>
                                </div>
                            </div>

                            <div class="scrim-list-actions">
                                <a class="btn btn-secondary" href="app.php?view=scrim-detail&amp;scrim_id=<?php echo (int) $scrim['id']; ?>">Ver</a>
                                <a class="btn btn-primary" href="app.php?view=scrim-form&amp;scrim_id=<?php echo (int) $scrim['id']; ?>">Editar</a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="scrim-list-footer">
                <div class="scrim-note-box">
                    Este listado está pensado para leer rápido la relación rival, resultado, score y fecha sin obligarte a desplazar la pantalla en horizontal.
                </div>

                <div class="stack-sm">
                    <a class="btn btn-secondary" href="app.php?view=calendar">Abrir calendario</a>
                    <a class="btn btn-secondary" href="app.php?view=scrim-form">Nuevo scrim</a>
                </div>
            </div>
        </div>
    <?php endif; ?>
</section>