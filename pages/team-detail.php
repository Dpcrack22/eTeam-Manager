<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/organization_functions.php';
require_once __DIR__ . '/../includes/team_functions.php';

requireAuth();

global $conn;

$currentUser = $_SESSION['user'] ?? [];
$userId = (int) ($currentUser['id'] ?? 0);
$allowedManageRoles = ['owner', 'admin', 'manager', 'coach'];
$teamRoles = ['coach', 'player', 'analyst', 'substitute'];
$teamRoleLabels = [
    'coach' => 'Coach',
    'player' => 'Player',
    'analyst' => 'Analyst',
    'substitute' => 'Substitute',
];
$errors = [];
$successMessage = '';

$activeOrganizationId = getActiveOrganizationId($conn, $userId);
$activeOrganization = $activeOrganizationId ? getOrganizationById($conn, $activeOrganizationId, $userId) : false;
if (!$activeOrganization) {
    $activeOrganization = [
        'id' => null,
        'name' => 'Sin equipo',
        'slug' => 'sin-equipo',
        'description' => '',
    ];
}

$games = getGames($conn);
$teams = [];
$selectedTeam = false;
$selectedTeamId = (int) ($_GET['team_id'] ?? 0);
$teamMembers = [];
$canManageTeams = in_array((string) ($_SESSION['user']['role'] ?? ''), $allowedManageRoles, true);

if ($activeOrganizationId) {
    $teams = getOrganizationTeams($conn, (int) $activeOrganizationId);
    $activeTeamId = getActiveTeamId($conn, (int) $activeOrganizationId);

    if ($selectedTeamId <= 0) {
        $selectedTeamId = $activeTeamId ?? (int) ($teams[0]['id'] ?? 0);
    }

    if ($selectedTeamId > 0) {
        $selectedTeam = getTeamById($conn, $selectedTeamId, (int) $activeOrganizationId);

        if (!$selectedTeam && !empty($teams)) {
            $firstTeam = $teams[0];
            $selectedTeamId = (int) $firstTeam['id'];
            $selectedTeam = getTeamById($conn, $selectedTeamId, (int) $activeOrganizationId);
        }

        if ($selectedTeam) {
            if ($activeTeamId !== (int) $selectedTeam['id']) {
                setActiveTeamContext($conn, (int) $activeOrganizationId, (int) $selectedTeam['id']);
            }

            $teamMembers = getTeamMembers($conn, (int) $selectedTeam['id']);
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $postedTeamId = (int) ($_POST['team_id'] ?? $selectedTeamId);

    if (!$activeOrganizationId) {
        $errors[] = 'Primero necesitas un contexto activo';
    } elseif (!$canManageTeams) {
        $errors[] = 'No tienes permisos para gestionar este equipo';
    } elseif ($postedTeamId <= 0) {
        $errors[] = 'Selecciona un equipo válido';
    } else {
        $selectedTeam = getTeamById($conn, $postedTeamId, (int) $activeOrganizationId);

        if (!$selectedTeam) {
            $errors[] = 'No tienes acceso a ese equipo';
        } else {
            $selectedTeamId = (int) $selectedTeam['id'];

            if ($action === 'update_team') {
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

                if (empty($errors) && teamExistsByNameAndGame($conn, (int) $activeOrganizationId, $teamName, $gameId, $selectedTeamId)) {
                    $errors[] = 'Ya existe un equipo con ese nombre para ese juego';
                }

                if (empty($errors)) {
                    updateTeam(
                        $conn,
                        $selectedTeamId,
                        (int) $activeOrganizationId,
                        $gameId,
                        $teamName,
                        $teamTag !== '' ? $teamTag : null,
                        $teamDescription !== '' ? $teamDescription : null
                    );
                    $successMessage = 'Equipo actualizado correctamente';
                }
            }

            if ($action === 'add_member') {
                $memberEmail = trim((string) ($_POST['email'] ?? ''));
                $memberRole = strtolower(trim((string) ($_POST['role'] ?? 'player')));

                if ($memberEmail === '') {
                    $errors[] = 'El email es obligatorio';
                }

                if (!in_array($memberRole, $teamRoles, true)) {
                    $errors[] = 'Selecciona un rol válido';
                }

                if (empty($errors)) {
                    $result = addOrUpdateTeamMemberByEmail($conn, $selectedTeamId, $memberEmail, $memberRole);

                    if (!empty($result['success'])) {
                        $successMessage = 'Miembro añadido al roster correctamente';
                    } else {
                        $errors[] = $result['error'] ?? 'No se ha podido añadir el miembro';
                    }
                }
            }

            if ($action === 'update_member_role') {
                $memberUserId = (int) ($_POST['member_user_id'] ?? 0);
                $memberRole = strtolower(trim((string) ($_POST['role'] ?? '')));

                if ($memberUserId <= 0) {
                    $errors[] = 'Miembro no válido';
                }

                if (!in_array($memberRole, $teamRoles, true)) {
                    $errors[] = 'Selecciona un rol válido';
                }

                if (empty($errors)) {
                    updateTeamMemberRole($conn, $selectedTeamId, $memberUserId, $memberRole);
                    $successMessage = 'Rol del miembro actualizado';
                }
            }

            if ($action === 'remove_member') {
                $memberUserId = (int) ($_POST['member_user_id'] ?? 0);

                if ($memberUserId <= 0) {
                    $errors[] = 'Miembro no válido';
                } else {
                    removeTeamMember($conn, $selectedTeamId, $memberUserId);
                    $successMessage = 'Miembro retirado del roster';
                }
            }

            if (empty($errors)) {
                $selectedTeam = getTeamById($conn, $selectedTeamId, (int) $activeOrganizationId);
                $teamMembers = getTeamMembers($conn, $selectedTeamId);
                setActiveTeamContext($conn, (int) $activeOrganizationId, $selectedTeamId);
            }
        }
    }
}

if ($selectedTeam) {
    $selectedTeamId = (int) $selectedTeam['id'];
    $teamMembers = getTeamMembers($conn, $selectedTeamId);
}

$teamStats = [
    'members' => count($teamMembers),
    'coach' => 0,
    'player' => 0,
    'analyst' => 0,
    'substitute' => 0,
];

foreach ($teamMembers as $member) {
    $role = (string) ($member['role'] ?? '');
    if (isset($teamStats[$role])) {
        $teamStats[$role]++;
    }
}
?>

<div class="dashboard-section-head" style="margin-bottom: 20px;">
    <div>
        <div class="small">Detalle de roster</div>
        <h2 class="h2">Detalle de equipo</h2>
        <p>Gestiona el roster interno, los roles competitivos y la información base de cada equipo dentro del contexto activo.</p>
    </div>
    <a class="btn btn-secondary" href="app.php?view=teams">Volver a equipos</a>
</div>

<?php if (!$activeOrganizationId): ?>
    <div class="card">
        <div class="dashboard-empty-state">Todavía no tienes un contexto activo. Necesitas un equipo para abrir el detalle.</div>
    </div>
<?php elseif (!$selectedTeam): ?>
    <div class="card">
        <div class="dashboard-empty-state">No hay un equipo seleccionado todavía. Vuelve al listado para crear uno o abre un roster existente.</div>
    </div>
<?php else: ?>
    <div class="grid-2">
        <div class="card">
            <div class="dashboard-section-head">
                <div>
                    <div class="small">Contexto activo</div>
                    <h3 class="h3"><?php echo htmlspecialchars($selectedTeam['name'], ENT_QUOTES, 'UTF-8'); ?></h3>
                </div>
                <span class="badge badge-success">Activo</span>
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

            <div class="landing-list">
                <div class="dashboard-list-item">
                    <div class="dashboard-list-top">
                        <div>
                            <div class="dashboard-list-title"><?php echo htmlspecialchars($selectedTeam['game_name'], ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="dashboard-list-meta">Juego asociado</div>
                        </div>
                        <span class="badge badge-info"><?php echo (int) $teamStats['members']; ?> miembros</span>
                    </div>
                </div>

                <div class="dashboard-list-item">
                    <div class="dashboard-list-top">
                        <div>
                            <div class="dashboard-list-title"><?php echo htmlspecialchars($selectedTeam['tag'] ?: 'Sin tag', ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="dashboard-list-meta">Tag competitivo</div>
                        </div>
                        <span class="badge"><?php echo htmlspecialchars($activeOrganization['slug'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                </div>
            </div>

            <div class="stack-sm" style="margin-top: 20px;">
                <span class="badge">Coach: <?php echo (int) $teamStats['coach']; ?></span>
                <span class="badge">Player: <?php echo (int) $teamStats['player']; ?></span>
                <span class="badge">Analyst: <?php echo (int) $teamStats['analyst']; ?></span>
                <span class="badge">Substitute: <?php echo (int) $teamStats['substitute']; ?></span>
            </div>

            <div style="margin-top: 20px;">
                <p class="small"><?php echo htmlspecialchars($selectedTeam['description'] ?: 'Sin descripción', ENT_QUOTES, 'UTF-8'); ?></p>
            </div>

            <?php if ($canManageTeams): ?>
                <form class="form" method="post" novalidate style="margin-top: 24px;">
                    <input type="hidden" name="action" value="update_team" />
                    <input type="hidden" name="team_id" value="<?php echo (int) $selectedTeam['id']; ?>" />

                    <div class="field">
                        <label for="detail_team_name">Nombre</label>
                        <input id="detail_team_name" name="name" type="text" value="<?php echo htmlspecialchars($selectedTeam['name'], ENT_QUOTES, 'UTF-8'); ?>" />
                    </div>

                    <div class="field">
                        <label for="detail_team_tag">Tag</label>
                        <input id="detail_team_tag" name="tag" type="text" value="<?php echo htmlspecialchars($selectedTeam['tag'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                    </div>

                    <div class="field">
                        <label for="detail_team_game">Juego</label>
                        <select id="detail_team_game" name="game_id">
                            <option value="">Selecciona un juego</option>
                            <?php foreach ($games as $game): ?>
                                <option value="<?php echo (int) $game['id']; ?>" <?php echo (int) $game['id'] === (int) $selectedTeam['game_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($game['name'], ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="field">
                        <label for="detail_team_description">Descripción</label>
                        <textarea id="detail_team_description" name="description" placeholder="Descripción del roster..."><?php echo htmlspecialchars($selectedTeam['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>

                    <button class="btn btn-primary" type="submit">Guardar cambios</button>
                </form>
            <?php else: ?>
                <div class="dashboard-empty-state" style="margin-top: 24px;">
                    Solo los roles de gestión pueden editar la ficha del equipo.
                </div>
            <?php endif; ?>
        </div>

        <div class="card">
            <div class="dashboard-section-head">
                <div>
                    <div class="small">Navegación</div>
                    <h3 class="h3">Otros equipos disponibles</h3>
                </div>
                <span class="badge badge-info"><?php echo count($teams); ?> totales</span>
            </div>

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

                            <a class="btn <?php echo (int) $team['id'] === (int) $selectedTeam['id'] ? 'btn-secondary' : 'btn-primary'; ?>" href="app.php?view=team-detail&amp;team_id=<?php echo (int) $team['id']; ?>">
                                <?php echo (int) $team['id'] === (int) $selectedTeam['id'] ? 'Equipo actual' : 'Ver detalle'; ?>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="dashboard-empty-state">Todavía no hay equipos creados en este contexto.</div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card" style="margin-top: 24px;">
        <div class="dashboard-section-head">
            <div>
                <div class="small">Roster</div>
                <h3 class="h3">Miembros del equipo</h3>
            </div>
            <span class="badge badge-info"><?php echo count($teamMembers); ?> activos</span>
        </div>

        <?php if ($canManageTeams): ?>
            <div class="grid-2" style="margin-bottom: 24px;">
                <form class="form" method="post" novalidate>
                    <input type="hidden" name="action" value="add_member" />
                    <input type="hidden" name="team_id" value="<?php echo (int) $selectedTeam['id']; ?>" />

                    <div class="field">
                        <label for="member_email">Email del usuario</label>
                        <input id="member_email" name="email" type="email" placeholder="usuario@correo.com" />
                    </div>

                    <div class="field">
                        <label for="member_role">Rol interno</label>
                        <select id="member_role" name="role">
                            <?php foreach ($teamRoles as $teamRole): ?>
                                <option value="<?php echo htmlspecialchars($teamRole, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($teamRoleLabels[$teamRole] ?? ucfirst($teamRole), ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button class="btn btn-primary" type="submit">Añadir al roster</button>
                </form>

                <div class="landing-list">
                    <div class="landing-list-item">La gestión de roster usa emails de usuarios ya registrados.</div>
                    <div class="landing-list-item">Los roles internos de equipo son coach, player, analyst y substitute.</div>
                    <div class="landing-list-item">Puedes reactivar un miembro retirado volviéndolo a añadir.</div>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($teamMembers)): ?>
            <div class="landing-list">
                <?php foreach ($teamMembers as $member): ?>
                    <div class="dashboard-list-item">
                        <div class="dashboard-list-top">
                            <div>
                                <div class="dashboard-list-title"><?php echo htmlspecialchars($member['username'], ENT_QUOTES, 'UTF-8'); ?></div>
                                <div class="dashboard-list-meta"><?php echo htmlspecialchars($member['email'], ENT_QUOTES, 'UTF-8'); ?> · miembro desde <?php echo htmlspecialchars(date('d/m/Y', strtotime((string) $member['joined_at'])), ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>
                            <span class="badge badge-success"><?php echo htmlspecialchars($teamRoleLabels[$member['role']] ?? ucfirst((string) $member['role']), ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>

                        <?php if ($canManageTeams): ?>
                            <div class="stack-sm" style="margin-top: 16px;">
                                <form class="stack-sm" method="post">
                                    <input type="hidden" name="action" value="update_member_role" />
                                    <input type="hidden" name="team_id" value="<?php echo (int) $selectedTeam['id']; ?>" />
                                    <input type="hidden" name="member_user_id" value="<?php echo (int) $member['user_id']; ?>" />
                                    <select name="role">
                                        <?php foreach ($teamRoles as $teamRole): ?>
                                            <option value="<?php echo htmlspecialchars($teamRole, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $member['role'] === $teamRole ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($teamRoleLabels[$teamRole] ?? ucfirst($teamRole), ENT_QUOTES, 'UTF-8'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button class="btn btn-secondary" type="submit">Cambiar rol</button>
                                </form>

                                <form method="post" onsubmit="return confirm('¿Retirar este miembro del roster?');">
                                    <input type="hidden" name="action" value="remove_member" />
                                    <input type="hidden" name="team_id" value="<?php echo (int) $selectedTeam['id']; ?>" />
                                    <input type="hidden" name="member_user_id" value="<?php echo (int) $member['user_id']; ?>" />
                                    <button class="btn btn-primary" type="submit">Dar de baja</button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="dashboard-empty-state">Todavía no hay miembros activos en este equipo.</div>
        <?php endif; ?>
    </div>
<?php endif; ?>
