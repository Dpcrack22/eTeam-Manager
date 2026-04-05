<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/organization_functions.php';
require_once __DIR__ . '/../includes/team_functions.php';

requireAuth();

global $conn;

$currentUser = $_SESSION['user'] ?? [];
$userId = (int) ($currentUser['id'] ?? 0);
$activeOrganizationId = getActiveOrganizationId($conn, $userId);
$activeOrganization = $activeOrganizationId ? getOrganizationById($conn, $activeOrganizationId, $userId) : false;
if (!$activeOrganization) {
    $activeOrganization = [
        'name' => 'Sin equipo',
        'slug' => 'sin-equipo',
    ];
}
$games = getGames($conn);
$teams = [];
$activeTeam = null;
$errors = [];
$successMessage = '';

if ($activeOrganizationId) {
    $teams = getOrganizationTeams($conn, $activeOrganizationId);

    if (empty($teams)) {
        $activeTeamId = null;
    } else {
        $activeTeamId = getActiveTeamId($conn, $activeOrganizationId);
        if ($activeTeamId === null) {
            $activeTeamId = (int) $teams[0]['id'];
            setActiveTeamContext($conn, $activeOrganizationId, $activeTeamId);
        }
    }

    foreach ($teams as $team) {
        if (isset($activeTeamId) && (int) $team['id'] === (int) $activeTeamId) {
            $activeTeam = $team;
            break;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if (!$activeOrganizationId) {
        $errors[] = 'Primero necesitas un contexto activo';
    } elseif (!in_array((string) ($currentUser['role'] ?? ''), ['owner', 'admin', 'manager'], true)) {
        $errors[] = 'No tienes permisos para gestionar equipos';
    } elseif ($action === 'create_team') {
        $teamName = trim((string) ($_POST['name'] ?? ''));
        $teamTag = trim((string) ($_POST['tag'] ?? ''));
        $teamDescription = trim((string) ($_POST['description'] ?? ''));
        $gameId = (int) ($_POST['game_id'] ?? 0);

        if ($teamName === '') {
            $errors[] = 'El nombre del equipo es obligatorio';
        }

        if ($gameId <= 0) {
            $errors[] = 'Selecciona un juego';
        }

        $gameExists = false;
        foreach ($games as $game) {
            if ((int) $game['id'] === $gameId) {
                $gameExists = true;
                break;
            }
        }

        if (!$gameExists) {
            $errors[] = 'El juego seleccionado no es válido';
        }

        if (empty($errors) && teamExistsByNameAndGame($conn, (int) $activeOrganizationId, $teamName, $gameId)) {
            $errors[] = 'Ya existe un equipo con ese nombre para ese juego';
        }

        if (empty($errors)) {
            $newTeamId = createTeam(
                $conn,
                $activeOrganizationId,
                $gameId,
                $teamName,
                $teamTag !== '' ? $teamTag : null,
                $teamDescription !== '' ? $teamDescription : null
            );

            setActiveTeamContext($conn, $activeOrganizationId, $newTeamId);
            $successMessage = 'Equipo creado y marcado como activo';
        }
    }

    if ($action === 'activate_team') {
        $teamId = (int) ($_POST['team_id'] ?? 0);
        $result = setActiveTeamContext($conn, (int) $activeOrganizationId, $teamId);

        if (!empty($result['success'])) {
            $activeTeam = $result['team'];
            $successMessage = 'Equipo activo actualizado';
        } else {
            $errors[] = $result['error'] ?? 'No se ha podido cambiar el equipo activo';
        }
    }

    $teams = getOrganizationTeams($conn, (int) $activeOrganizationId);
}
?>

<div class="grid-2">
    <div class="card">
        <?php if (!$activeOrganizationId): ?>
            <div class="dashboard-empty-state" style="margin-bottom: 16px;">
                        Todavía no tienes un contexto activo. Primero necesitas acceder a un equipo para poder gestionarlo.
            </div>
        <?php endif; ?>

        <div class="dashboard-section-head">
            <div>
                <div class="small">Equipo actual</div>
                        <h2 class="h3"><?php echo htmlspecialchars($activeTeam['name'] ?? 'Sin equipo', ENT_QUOTES, 'UTF-8'); ?></h2>
            </div>
            <?php if ($activeTeam): ?>
                <span class="badge badge-success">Activo: <?php echo htmlspecialchars($activeTeam['name'], ENT_QUOTES, 'UTF-8'); ?></span>
            <?php endif; ?>
        </div>

        <?php if (!empty($successMessage)): ?>
            <div class="error-box" style="border-color: rgba(46, 204, 113, 0.4); background: rgba(46, 204, 113, 0.1); margin-bottom: 16px; color: var(--text-main);">
                <?php echo htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="error-container">
                <?php foreach ($errors as $error): ?>
                    <div class="error-box"><?php echo htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($teams)): ?>
            <div class="landing-list">
                <?php foreach ($teams as $team): ?>
                    <div class="dashboard-list-item">
                        <div class="dashboard-list-top">
                            <div>
                                <div class="dashboard-list-title"><?php echo htmlspecialchars($team['name'], ENT_QUOTES, 'UTF-8'); ?></div>
                                <div class="dashboard-list-meta"><?php echo htmlspecialchars($team['game_name'], ENT_QUOTES, 'UTF-8'); ?><?php echo !empty($team['tag']) ? ' · ' . htmlspecialchars($team['tag'], ENT_QUOTES, 'UTF-8') : ''; ?></div>
                            </div>
                            <span class="badge badge-info"><?php echo (int) $team['members_count']; ?> miembros</span>
                        </div>

                        <p class="small"><?php echo htmlspecialchars($team['description'] ?: 'Sin descripción', ENT_QUOTES, 'UTF-8'); ?></p>

                        <div class="stack-sm">
                            <a class="btn btn-secondary" href="app.php?view=team-detail&amp;team_id=<?php echo (int) $team['id']; ?>">Ver detalle</a>

                            <form method="post">
                                <input type="hidden" name="action" value="activate_team" />
                                <input type="hidden" name="team_id" value="<?php echo (int) $team['id']; ?>" />
                                <button class="btn <?php echo $activeTeam && (int) $activeTeam['id'] === (int) $team['id'] ? 'btn-secondary' : 'btn-primary'; ?>" type="submit">
                                    <?php echo $activeTeam && (int) $activeTeam['id'] === (int) $team['id'] ? 'Equipo actual' : 'Usar este equipo'; ?>
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="dashboard-empty-state">Todavía no hay equipos en este contexto.</div>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="dashboard-section-head">
            <div>
                <div class="small">Nuevo equipo</div>
                <h2 class="h3">Crear roster</h2>
            </div>
        </div>

        <form class="form" method="post" novalidate>
            <input type="hidden" name="action" value="create_team" />

            <div class="field">
                <label for="team_name">Nombre</label>
                <input id="team_name" name="name" type="text" placeholder="Parallax V" />
            </div>

            <div class="field">
                <label for="team_tag">Tag</label>
                <input id="team_tag" name="tag" type="text" placeholder="PV" />
            </div>

            <div class="field">
                <label for="team_game">Juego</label>
                <select id="team_game" name="game_id">
                    <option value="">Selecciona un juego</option>
                    <?php foreach ($games as $game): ?>
                        <option value="<?php echo (int) $game['id']; ?>"><?php echo htmlspecialchars($game['name'], ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label for="team_description">Descripción</label>
                <textarea id="team_description" name="description" placeholder="Objetivo del roster..."></textarea>
            </div>

            <button class="btn btn-primary" type="submit">Crear equipo</button>
        </form>

        <div class="landing-list">
            <div class="landing-list-item">El equipo activo se usa como contexto operativo del dashboard.</div>
            <div class="landing-list-item">La gestión de miembros y roles vive en el detalle de cada equipo.</div>
            <div class="landing-list-item">Solo roles de gestión pueden crear equipos.</div>
        </div>
    </div>
</div>