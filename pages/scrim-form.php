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
$activeTeam = $activeTeamId ? getTeamById($conn, (int) $activeTeamId, (int) $activeOrganizationId) : false;
$errors = [];

if (!$activeTeam) {
    $activeTeam = false;
}

$scrimId = (int) ($_GET['scrim_id'] ?? $_POST['scrim_id'] ?? 0);
$scrim = false;
$scrimMaps = [];

if ($activeTeam && $scrimId > 0) {
    $scrim = getScrimById($conn, $scrimId, (int) $activeTeam['id']);
    if ($scrim) {
        $scrimMaps = getScrimMaps($conn, (int) $scrim['id']);
    }
}

$teamGameId = $activeTeam ? (int) $activeTeam['game_id'] : 0;
$gameModes = $teamGameId > 0 ? getGameModesForGame($conn, $teamGameId) : [];
$gameMaps = $teamGameId > 0 ? getGameMapsForGame($conn, $teamGameId) : [];

$formState = [
    'opponent_name' => $scrim['opponent_name'] ?? '',
    'opponent_tag' => $scrim['opponent_tag'] ?? '',
    'match_date' => $scrim['match_date'] ?? '',
    'game_mode_id' => $scrim['game_mode_id'] ?? ($gameModes[0]['id'] ?? ''),
    'result' => $scrim['result'] ?? 'pending',
    'score_for' => $scrim['score_for'] ?? '',
    'score_against' => $scrim['score_against'] ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['scrim_action'] ?? 'save_scrim');

    if (!$activeOrganizationId || !$activeTeam || $teamGameId <= 0) {
        $errors[] = 'Necesitas un equipo activo con juego asignado para gestionar scrims';
    } elseif ($action === 'delete_scrim') {
        if ($scrimId > 0) {
            deleteScrim($conn, $scrimId, (int) $activeTeam['id']);
            header('Location: app.php?view=scrims');
            exit;
        }

        $errors[] = 'No se ha encontrado el scrim para eliminar';
    } else {
        $opponentName = trim((string) ($_POST['opponent_name'] ?? ''));
        $opponentTag = trim((string) ($_POST['opponent_tag'] ?? ''));
        $matchDateInput = trim((string) ($_POST['match_date'] ?? ''));
        $result = strtolower(trim((string) ($_POST['result'] ?? 'pending')));
        $gameModeId = (int) ($_POST['game_mode_id'] ?? 0);
        $scoreForInput = trim((string) ($_POST['score_for'] ?? ''));
        $scoreAgainstInput = trim((string) ($_POST['score_against'] ?? ''));
        $mapRows = $_POST['maps'] ?? [];

        $allowedResults = ['pending', 'win', 'loss', 'draw'];

        if ($opponentName === '') {
            $errors[] = 'El rival es obligatorio';
        }

        $dateObject = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $matchDateInput);
        if (!$dateObject) {
            $errors[] = 'La fecha del scrim no es válida';
        }

        $gameModeExists = false;
        foreach ($gameModes as $gameMode) {
            if ((int) $gameMode['id'] === $gameModeId) {
                $gameModeExists = true;
                break;
            }
        }

        if (!$gameModeExists) {
            $errors[] = 'Selecciona un modo de juego válido';
        }

        if (!in_array($result, $allowedResults, true)) {
            $errors[] = 'El resultado seleccionado no es válido';
        }

        $scoreFor = $scoreForInput === '' ? null : (int) $scoreForInput;
        $scoreAgainst = $scoreAgainstInput === '' ? null : (int) $scoreAgainstInput;
        $normalizedMatchDate = $dateObject ? $dateObject->format('Y-m-d H:i:s') : '';
        $normalizedOpponentTag = $opponentTag !== '' ? $opponentTag : null;

        if (empty($errors)) {
            $normalizedMaps = [];
            foreach ($mapRows as $mapRow) {
                if (!is_array($mapRow)) {
                    continue;
                }

                $normalizedMaps[] = [
                    'map_id' => (int) ($mapRow['map_id'] ?? 0),
                    'order_index' => (int) ($mapRow['order_index'] ?? 0),
                    'score_for' => trim((string) ($mapRow['score_for'] ?? '')),
                    'score_against' => trim((string) ($mapRow['score_against'] ?? '')),
                ];
            }

            if ($scrim && $scrimId > 0) {
                updateScrim(
                    $conn,
                    $scrimId,
                    (int) $activeTeam['id'],
                    $gameModeId,
                    $opponentName,
                    $normalizedOpponentTag,
                    $normalizedMatchDate,
                    $result,
                    $scoreFor,
                    $scoreAgainst,
                    $normalizedMaps
                );
                header('Location: app.php?view=scrim-detail&scrim_id=' . $scrimId);
                exit;
            }

            $createdScrimId = createScrim(
                $conn,
                (int) $activeTeam['id'],
                $gameModeId,
                $opponentName,
                $normalizedOpponentTag,
                $normalizedMatchDate,
                $result,
                $scoreFor,
                $scoreAgainst,
                $userId,
                $normalizedMaps
            );

            header('Location: app.php?view=scrim-detail&scrim_id=' . $createdScrimId);
            exit;
        }

        $formState = [
            'opponent_name' => $opponentName,
            'opponent_tag' => $opponentTag,
            'match_date' => $matchDateInput,
            'game_mode_id' => $gameModeId,
            'result' => $result,
            'score_for' => $scoreForInput,
            'score_against' => $scoreAgainstInput,
        ];
    }
}

if (!$scrim && $scrimId > 0 && $activeTeam) {
    $scrim = getScrimById($conn, $scrimId, (int) $activeTeam['id']);
    if ($scrim) {
        $scrimMaps = getScrimMaps($conn, (int) $scrim['id']);
        $formState = [
            'opponent_name' => $scrim['opponent_name'],
            'opponent_tag' => $scrim['opponent_tag'] ?? '',
            'match_date' => $scrim['match_date'],
            'game_mode_id' => $scrim['game_mode_id'],
            'result' => $scrim['result'],
            'score_for' => $scrim['score_for'] ?? '',
            'score_against' => $scrim['score_against'] ?? '',
        ];
    }
}

if (empty($scrimMaps)) {
    $scrimMaps = [
        ['map_id' => $gameMaps[0]['id'] ?? 0, 'score_for' => '', 'score_against' => '', 'order_index' => 1],
        ['map_id' => $gameMaps[1]['id'] ?? ($gameMaps[0]['id'] ?? 0), 'score_for' => '', 'score_against' => '', 'order_index' => 2],
        ['map_id' => $gameMaps[2]['id'] ?? ($gameMaps[0]['id'] ?? 0), 'score_for' => '', 'score_against' => '', 'order_index' => 3],
    ];
}

$pageScripts[] = 'js/modules/scrim-form.js';
$pageTitle = $scrim ? 'Editar scrim' : 'Nuevo scrim';
$pageEyebrow = 'Modulo';
$pageDescription = $scrim ? 'Edición del enfrentamiento competitivo y sus mapas.' : 'Alta de un nuevo enfrentamiento con score, modo y mapas.';
$activeSection = 'scrims';
?>

<section class="scrim-form-page" data-scrim-form-root>
    <div class="dashboard-hero card">
        <div>
            <div class="small">Sprint 5</div>
            <h2 class="h2"><?php echo $scrim ? 'Editar scrim' : 'Nuevo scrim'; ?></h2>
            <p>El formulario prepara el dato base para el historial competitivo. Desde aquí se guarda rival, fecha, resultado, score y mapas.</p>
        </div>
        <div class="dashboard-hero-meta">
            <div class="dashboard-hero-chip">
                <div class="small">Equipo activo</div>
                <div class="dashboard-hero-value"><?php echo htmlspecialchars((string) ($activeTeam['name'] ?? 'Sin equipo'), ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
            <div class="dashboard-hero-chip">
                <div class="small">Resultado previsto</div>
                <div class="dashboard-hero-value" data-scrim-result-preview><?php echo htmlspecialchars(scrimResultLabel((string) ($formState['result'] ?? 'pending')), ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
        </div>
    </div>

    <?php if (!$activeTeam): ?>
        <div class="card dashboard-empty-state">
            No hay un equipo activo. Antes de crear scrims necesitas activar un roster desde Equipos.
        </div>
    <?php else: ?>
        <div class="grid-2">
            <div class="card">
                <?php if (!empty($errors)): ?>
                    <div class="error-container">
                        <?php foreach ($errors as $error): ?>
                            <div class="error-box"><?php echo htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8'); ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <form class="form scrim-form" method="post" novalidate>
                    <input type="hidden" name="scrim_id" value="<?php echo (int) $scrimId; ?>" />
                    <input type="hidden" name="scrim_action" value="save_scrim" />

                    <div class="scrim-form-grid">
                        <div class="field">
                            <label for="scrim_opponent">Rival</label>
                            <input id="scrim_opponent" name="opponent_name" type="text" placeholder="Vertex Collective" value="<?php echo htmlspecialchars((string) ($formState['opponent_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                        </div>

                        <div class="field">
                            <label for="scrim_tag">Tag del rival</label>
                            <input id="scrim_tag" name="opponent_tag" type="text" placeholder="VX" value="<?php echo htmlspecialchars((string) ($formState['opponent_tag'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                        </div>

                        <div class="field">
                            <label for="scrim_date">Fecha y hora</label>
                            <input id="scrim_date" name="match_date" type="datetime-local" value="<?php echo htmlspecialchars((string) ($formState['match_date'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                        </div>

                        <div class="field">
                            <label for="scrim_mode">Modo</label>
                            <select id="scrim_mode" name="game_mode_id">
                                <?php foreach ($gameModes as $gameMode): ?>
                                    <option value="<?php echo (int) $gameMode['id']; ?>" <?php echo (int) ($formState['game_mode_id'] ?? 0) === (int) $gameMode['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($gameMode['name'], ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="field">
                            <label for="scrim_result">Resultado</label>
                            <select id="scrim_result" name="result" data-scrim-result-select>
                                <option value="pending" <?php echo ($formState['result'] ?? 'pending') === 'pending' ? 'selected' : ''; ?>>Pendiente</option>
                                <option value="win" <?php echo ($formState['result'] ?? '') === 'win' ? 'selected' : ''; ?>>Victoria</option>
                                <option value="loss" <?php echo ($formState['result'] ?? '') === 'loss' ? 'selected' : ''; ?>>Derrota</option>
                                <option value="draw" <?php echo ($formState['result'] ?? '') === 'draw' ? 'selected' : ''; ?>>Empate</option>
                            </select>
                        </div>

                        <div class="field">
                            <label for="scrim_score_for">Score a favor</label>
                            <input id="scrim_score_for" name="score_for" type="number" min="0" placeholder="26" value="<?php echo htmlspecialchars((string) ($formState['score_for'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" data-scrim-score-for />
                        </div>

                        <div class="field">
                            <label for="scrim_score_against">Score en contra</label>
                            <input id="scrim_score_against" name="score_against" type="number" min="0" placeholder="22" value="<?php echo htmlspecialchars((string) ($formState['score_against'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" data-scrim-score-against />
                        </div>
                    </div>

                    <div class="dashboard-section-head" style="margin-top: 4px;">
                        <div>
                            <div class="small">Mapas</div>
                            <h3 class="h3">Orden del enfrentamiento</h3>
                        </div>
                        <button class="btn btn-secondary" type="button" data-scrim-add-map>Agregar mapa</button>
                    </div>

                    <div class="scrim-form-map-list" data-scrim-map-list>
                        <?php foreach ($scrimMaps as $index => $scrimMap): ?>
                            <div class="scrim-form-map-row" data-scrim-map-row>
                                <div class="field">
                                    <label>Orden</label>
                                    <input name="maps[<?php echo (int) $index; ?>][order_index]" type="number" min="1" value="<?php echo (int) ($scrimMap['order_index'] ?? ($index + 1)); ?>" data-map-field="order_index" />
                                </div>

                                <div class="field">
                                    <label>Mapa</label>
                                    <select name="maps[<?php echo (int) $index; ?>][map_id]" data-map-field="map_id">
                                        <option value="">Selecciona un mapa</option>
                                        <?php foreach ($gameMaps as $gameMap): ?>
                                            <option value="<?php echo (int) $gameMap['id']; ?>" <?php echo (int) ($scrimMap['map_id'] ?? 0) === (int) $gameMap['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($gameMap['name'], ENT_QUOTES, 'UTF-8'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="field">
                                    <label>Score a favor</label>
                                    <input name="maps[<?php echo (int) $index; ?>][score_for]" type="number" min="0" value="<?php echo htmlspecialchars((string) ($scrimMap['score_for'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" data-map-field="score_for" />
                                </div>

                                <div class="field">
                                    <label>Score en contra</label>
                                    <input name="maps[<?php echo (int) $index; ?>][score_against]" type="number" min="0" value="<?php echo htmlspecialchars((string) ($scrimMap['score_against'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" data-map-field="score_against" />
                                </div>

                                <div class="scrim-form-map-actions">
                                    <button class="btn btn-secondary" type="button" data-scrim-remove-map>Quitar</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="scrim-form-actions">
                        <button class="btn btn-primary" type="submit"><?php echo $scrim ? 'Guardar cambios' : 'Crear scrim'; ?></button>
                        <a class="btn btn-secondary" href="app.php?view=scrims">Volver al listado</a>
                        <?php if ($scrim): ?>
                            <button class="btn btn-secondary" type="submit" name="scrim_action" value="delete_scrim" onclick="return confirm('¿Eliminar este scrim?');">Eliminar</button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <div class="card">
                <div class="dashboard-section-head">
                    <div>
                        <div class="small">Contexto del módulo</div>
                        <h3 class="h3">Qué queda ya preparado</h3>
                    </div>
                </div>

                <div class="landing-list">
                    <div class="landing-list-item">Alta y edición de scrims por equipo activo.</div>
                    <div class="landing-list-item">Estructura de mapas con orden y score.</div>
                    <div class="landing-list-item">Resultado, rival y modo enlazados al modelo `matches`.</div>
                    <div class="landing-list-item">Base lista para conectar analítica más adelante.</div>
                </div>

                <div class="scrim-note-box">
                    Este formulario ya deja el sprint listo para seguir con calendario y estadísticas sin rehacer la estructura de datos.
                </div>
            </div>
        </div>
    <?php endif; ?>
</section>