<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/invitation_functions.php';
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
$userOrganizations = $userId ? getUserOrganizations($conn, $userId) : [];
$currentOrganizationRole = null;

foreach ($userOrganizations as $userOrganization) {
    if ((int) $userOrganization['id'] === (int) $activeOrganizationId) {
        $currentOrganizationRole = (string) $userOrganization['member_role'];
        break;
    }
}

$canManageInvitations = in_array($currentOrganizationRole, ['owner', 'admin', 'manager'], true);
$teams = [];
$activeTeam = null;
$errors = [];
$successMessage = '';
$pendingInvitations = $userId ? getPendingTeamInvitationsForUser($conn, $userId) : [];
$pendingInvitationsById = [];

foreach ($pendingInvitations as $pendingInvitation) {
    $pendingInvitationsById[(int) $pendingInvitation['id']] = $pendingInvitation;
}

if (!empty($_SESSION['flash_success'])) {
    $successMessage = (string) $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}

// Load teams for the current context; if there's no active organization, show all active teams so users can join
if ($activeOrganizationId) {
    $teams = getOrganizationTeams($conn, $activeOrganizationId);

    if (empty($teams)) {
        $activeTeamId = null;
    } else {
        $activeTeamId = getActiveTeamId($conn, $activeOrganizationId);
        if ($activeTeamId === null) {
            // prefer a team the user is a member of; otherwise do not auto-activate to avoid overwriting session
            $found = false;
            foreach ($teams as $t) {
                if ($userId && isUserActiveMember($conn, (int)$t['id'], $userId)) {
                    $activeTeamId = (int) $t['id'];
                    setActiveTeamContext($conn, $activeOrganizationId, $activeTeamId);
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $activeTeamId = null;
            }
        }
    }

    foreach ($teams as $team) {
        if (isset($activeTeamId) && (int) $team['id'] === (int) $activeTeamId) {
            $activeTeam = $team;
            break;
        }
    }
} else {
    $teams = getAllActiveTeams($conn);

        if (!empty($teams)) {
        // prefer session active team if valid, otherwise pick first
        $activeTeamId = $_SESSION['active_team_id'] ?? null;
        if ($activeTeamId && getTeamById($conn, (int) $activeTeamId)) {
            // find and set activeTeam
            foreach ($teams as $team) {
                if ((int) $team['id'] === (int) $activeTeamId) {
                    $activeTeam = $team;
                    break;
                }
            }
        }

        if (!$activeTeam) {
            // pick first team only if the user is a member; otherwise leave no active team/context
            foreach ($teams as $t) {
                if ($userId && isUserActiveMember($conn, (int)$t['id'], $userId)) {
                    $activeTeam = $t;
                    setActiveTeamContext($conn, (int) ($activeTeam['organization_id'] ?? 0), (int) $activeTeam['id']);
                    break;
                }
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $returnTo = trim((string) ($_POST['return_to'] ?? 'app.php?view=teams'));

    if (!str_starts_with($returnTo, 'app.php?view=')) {
        $returnTo = 'app.php?view=teams';
    }

    if ($action === 'activate_team') {
        $teamId = (int) ($_POST['team_id'] ?? 0);

        if (!$activeOrganizationId) {
            $errors[] = 'Primero necesitas un contexto activo';
        } elseif ($teamId <= 0) {
            $errors[] = 'Selecciona un equipo válido';
        } else {
            $result = setActiveTeamContext($conn, (int) $activeOrganizationId, $teamId);

            if (!empty($result['success'])) {
                header('Location: ' . $returnTo);
                exit;
            }

            $errors[] = $result['error'] ?? 'No se ha podido cambiar el equipo activo';
        }
    } elseif ($action === 'invite_member') {
        $teamId = (int) ($_POST['team_id'] ?? 0);
        $email = trim((string) ($_POST['email'] ?? ''));
        $role = strtolower(trim((string) ($_POST['role'] ?? 'player')));
        $allowedRoles = ['coach', 'player', 'analyst', 'substitute'];

        if (!$canManageInvitations) {
            $errors[] = 'No tienes permisos para invitar miembros en este contexto';
        } elseif ($teamId <= 0 || !$activeTeamId || (int) $activeTeamId !== $teamId) {
            $errors[] = 'Selecciona el equipo activo para enviar la invitación';
        } elseif ($email === '' || strpos($email, '@') === false) {
            $errors[] = 'Introduce un email válido';
        } elseif (!in_array($role, $allowedRoles, true)) {
            $errors[] = 'Selecciona un rol válido';
        } else {
            $inviteResult = createTeamInvitation($conn, $teamId, $userId, $email, $role);
            if (!empty($inviteResult['success'])) {
                $_SESSION['flash_success'] = 'Invitación enviada';
                header('Location: app.php?view=teams');
                exit;
            }

            $errors[] = $inviteResult['error'] ?? 'No se ha podido enviar la invitación';
        }
    } elseif ($action === 'accept_invite' || $action === 'decline_invite') {
        $invitationId = (int) ($_POST['invitation_id'] ?? 0);

        if ($invitationId <= 0) {
            $errors[] = 'Selecciona una invitación válida';
        } elseif ($action === 'accept_invite') {
            $result = acceptTeamInvitation($conn, $invitationId, $userId);
            if (!empty($result['success'])) {
                if (!empty($result['team'])) {
                    setActiveTeamContext($conn, (int) $result['team']['organization_id'], (int) $result['team']['id']);
                }

                $_SESSION['flash_success'] = 'Invitación aceptada';
                header('Location: app.php?view=teams');
                exit;
            }

            $errors[] = $result['error'] ?? 'No se ha podido aceptar la invitación';
        } else {
            $result = declineTeamInvitation($conn, $invitationId, $userId);
            if (!empty($result['success'])) {
                $_SESSION['flash_success'] = 'Invitación rechazada';
                header('Location: app.php?view=teams');
                exit;
            }

            $errors[] = $result['error'] ?? 'No se ha podido rechazar la invitación';
        }
    } elseif ($action === 'create_team') {
        // allow creating a team even if there's no active organization by falling back
        // to the first organization the user belongs to (if any)
        $teamName = trim((string) ($_POST['name'] ?? ''));
        $teamTag = trim((string) ($_POST['tag'] ?? ''));
        $teamDescription = trim((string) ($_POST['description'] ?? ''));
        $gameId = (int) ($_POST['game_id'] ?? 0);

        $userOrgs = getUserOrganizations($conn, $userId);
        $targetOrgId = $activeOrganizationId ?: ($userOrgs[0]['id'] ?? 0);

        if ($targetOrgId <= 0) {
            $errors[] = 'Primero necesitas un contexto activo';
        }

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

        if (empty($errors) && teamExistsByNameAndGame($conn, (int) $targetOrgId, $teamName, $gameId)) {
            $errors[] = 'Ya existe un equipo con ese nombre para ese juego';
        }

        // check permissions in the target organization
        $allowed = false;
        foreach ($userOrgs as $uo) {
            if ((int) $uo['id'] === (int) $targetOrgId && in_array($uo['member_role'], ['owner', 'admin', 'manager'], true)) {
                $allowed = true;
                break;
            }
        }

        if (!$allowed) {
            $errors[] = 'No tienes permisos para gestionar equipos en la organización seleccionada';
        }

        if (empty($errors)) {
            $newTeamId = createTeam(
                $conn,
                $targetOrgId,
                $gameId,
                $teamName,
                $teamTag !== '' ? $teamTag : null,
                $teamDescription !== '' ? $teamDescription : null
            );

            setActiveTeamContext($conn, $targetOrgId, $newTeamId);
            $_SESSION['flash_success'] = 'Equipo creado y marcado como activo';
            header('Location: ' . $returnTo);
            exit;
        }
    } elseif ($action === 'join_team') {
        $teamId = (int) ($_POST['team_id'] ?? 0);

        if ($teamId <= 0) {
            $errors[] = 'Selecciona un equipo válido';
        } else {
            $joinResult = joinTeam($conn, $teamId, $userId, 'player');
            if (!empty($joinResult['success'])) {
                $team = $joinResult['team'];
                setActiveTeamContext($conn, (int) ($team['organization_id'] ?? 0), $teamId);
                $_SESSION['flash_success'] = 'Te has unido al equipo';
                header('Location: ' . $returnTo);
                exit;
            }

            $errors[] = $joinResult['error'] ?? 'No se ha podido unir al equipo';
        }
    } else if ($action === "unjoin_team") {
        $teamId = (int) ($_POST["team_id"] ?? 0);

        if ($teamId <= 0) {
            $errors[] = "Selecciona un equipo válido";
        } else {
            $unjoinResult = unjoinTeam($conn, $teamId, $userId);
            if (!empty($unjoinResult["success"])) {
                $_SESSION["flash_success"] = "Has dejado el equipo";
                header("Location: " . $returnTo);
                exit;
            }
        }
    } elseif (!$activeOrganizationId) {
        // keep the message when attempting other management actions without context
        $errors[] = 'Primero necesitas un contexto activo';
    }
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
            $_SESSION['flash_success'] = 'Equipo creado y marcado como activo';
            header('Location: ' . $returnTo);
            exit;
        }
    }

    // refresh teams list according to current context
    if ($activeOrganizationId) {
        $teams = getOrganizationTeams($conn, (int) $activeOrganizationId);
    } else {
        $teams = getAllActiveTeams($conn);
    }

// build membership map for current user to show "Unirme" when appropriate
$userTeamIds = [];
if ($userId && !empty($teams)) {
    $ids = array_map(function($t){ return (int)$t['id']; }, $teams);
    $in = implode(',', $ids);
    $stmt = $conn->prepare('SELECT team_id FROM team_members WHERE user_id = :user_id AND is_active = 1' . (count($ids) ? " AND team_id IN ($in)" : ''));
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();
    foreach ($rows as $r) {
        $userTeamIds[(int)$r['team_id']] = true;
    }
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

        <?php if (!empty($pendingInvitations)): ?>
            <div class="team-invitations-list" style="margin-bottom: 16px;">
                <?php foreach ($pendingInvitations as $invitation): ?>
                    <article class="team-invitation-card">
                        <div class="team-invitation-copy">
                            <div class="team-invitation-top">
                                <span class="badge badge-info"><?php echo htmlspecialchars((string) $invitation['team_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <span class="small"><?php echo htmlspecialchars((string) $invitation['organization_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                            <p class="team-invitation-message">Te ha invitado <?php echo htmlspecialchars((string) $invitation['inviter_name'], ENT_QUOTES, 'UTF-8'); ?> para el rol de <?php echo htmlspecialchars((string) $invitation['role'], ENT_QUOTES, 'UTF-8'); ?>.</p>
                        </div>

                        <div class="team-invitation-actions">
                            <form method="post">
                                <input type="hidden" name="action" value="accept_invite" />
                                <input type="hidden" name="invitation_id" value="<?php echo (int) $invitation['id']; ?>" />
                                <button class="btn btn-primary" type="submit">Aceptar</button>
                            </form>
                            <form method="post">
                                <input type="hidden" name="action" value="decline_invite" />
                                <input type="hidden" name="invitation_id" value="<?php echo (int) $invitation['id']; ?>" />
                                <button class="btn btn-secondary" type="submit">Rechazar</button>
                            </form>
                        </div>
                    </article>
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
                            <?php if ($userId && !empty($userTeamIds[(int)$team['id']])): ?>
                                <form method="post" style="display:inline-block;">
                                    <input type="hidden" name="action" value="activate_team" />
                                    <input type="hidden" name="team_id" value="<?php echo (int) $team['id']; ?>" />
                                    <button class="btn <?php echo $activeTeam && (int) $activeTeam['id'] === (int) $team['id'] ? 'btn-secondary' : 'btn-primary'; ?>" type="submit">
                                        <?php echo $activeTeam && (int) $activeTeam['id'] === (int) $team['id'] ? 'Equipo actual' : 'Usar este equipo'; ?>
                                    </button>
                                </form>
                            <?php else: ?>
                                <button class="btn btn-outline" type="button" disabled title="Debes unirte al equipo para activarlo">Usar este equipo</button>
                            <?php endif; ?>

                            <?php if ($userId && empty($userTeamIds[(int)$team['id']])): ?>
                                <form method="post" style="display:inline-block; margin-left:8px;">
                                    <input type="hidden" name="action" value="join_team" />
                                    <input type="hidden" name="team_id" value="<?php echo (int) $team['id']; ?>" />
                                    <button class="btn btn-outline" type="submit">Unirme</button>
                                </form>
                            <?php elseif ($userId && !empty($userTeamIds[(int)$team['id']])): ?>
                                <form method="post" style="display:inline-block; margin-left:8px;">
                                    <input type="hidden" name="action" value="unjoin_team" />
                                    <input type="hidden" name="team_id" value="<?php echo (int) $team['id']; ?>" />
                                    <button class="btn btn-outline" type="submit">Salir</button>
                                </form>
                            <?php endif; ?>
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

        <?php if ($canManageInvitations && $activeTeamId !== null): ?>
            <div class="team-invite-panel" style="margin-top: 20px;">
                <div class="dashboard-section-head">
                    <div>
                        <div class="small">Invitaciones</div>
                        <h3 class="h3">Invitar al equipo activo</h3>
                    </div>
                </div>

                <form class="form team-invite-form" method="post" novalidate>
                    <input type="hidden" name="action" value="invite_member" />
                    <input type="hidden" name="team_id" value="<?php echo (int) $activeTeamId; ?>" />

                    <div class="field">
                        <label for="invite_email">Email</label>
                        <input id="invite_email" name="email" type="email" placeholder="player@team.gg" />
                    </div>

                    <div class="field">
                        <label for="invite_role">Rol</label>
                        <select id="invite_role" name="role">
                            <option value="player">Player</option>
                            <option value="coach">Coach</option>
                            <option value="analyst">Analyst</option>
                            <option value="substitute">Substitute</option>
                        </select>
                    </div>

                    <button class="btn btn-primary" type="submit">Enviar invitación</button>
                </form>

                <div class="small">La invitación aparecerá en notificaciones y se podrá aceptar desde ahí o desde esta página.</div>
            </div>
        <?php elseif ($activeTeamId !== null): ?>
            <div class="team-invite-panel team-invite-panel--muted" style="margin-top: 20px;">
                <div class="small">Solo los roles de gestión pueden enviar invitaciones a este equipo.</div>
            </div>
        <?php endif; ?>

        <div class="landing-list">
            <div class="landing-list-item">El equipo activo se usa como contexto operativo del dashboard.</div>
            <div class="landing-list-item">La gestión de miembros y roles vive en el detalle de cada equipo.</div>
            <div class="landing-list-item">Solo roles de gestión pueden crear equipos.</div>
        </div>
    </div>
</div>