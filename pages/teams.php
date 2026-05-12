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

// Load teams for the current context (only the teams where the user is an active member).
if ($activeOrganizationId) {
    $teams = $userId ? getUserOrganizationTeams($conn, $activeOrganizationId, $userId) : [];

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
} else {
    $teams = [];
    $activeTeam = null;
    $activeTeamId = null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $returnTo = trim((string) ($_POST['return_to'] ?? 'app.php?view=teams'));

    if (!str_starts_with($returnTo, 'app.php?view=')) {
        $returnTo = 'app.php?view=teams';
    }

    if ($action === 'activate_team') {
        $teamId = (int) ($_POST['team_id'] ?? 0);

        if ($teamId <= 0) {
            $errors[] = 'Selecciona un equipo válido';
        } else {
            if (!$activeOrganizationId) {
                $errors[] = 'Necesitas seleccionar una organización activa';
            } else {
                $team = getTeamById($conn, $teamId, $activeOrganizationId);

                if (!$team) {
                    $errors[] = 'Equipo no encontrado o no tienes acceso';
                } else {
                    $teamOrganizationId = (int) ($team['organization_id'] ?? 0);

                    if ($teamOrganizationId <= 0) {
                        $errors[] = 'No tienes acceso a ese equipo';
                    } else {
                        $orgContext = setActiveOrganizationContext($conn, $userId, $teamOrganizationId);

                        if (empty($orgContext['success'])) {
                            $errors[] = $orgContext['error'] ?? 'No tienes acceso a esa organización';
                        } else {
                            $orgRole = strtolower((string) ($orgContext['organization']['role'] ?? ''));
                            $isMember = isUserActiveMember($conn, $teamId, $userId);

                        if (!$isMember && !in_array($orgRole, ['owner', 'admin', 'manager'], true)) {
                            $errors[] = 'No tienes acceso a ese equipo';
                        } else {
                            $result = setActiveTeamContext($conn, $teamOrganizationId, $teamId);

                            if (!empty($result['success'])) {
                                header('Location: ' . $returnTo);
                                exit;
                            }

                            $errors[] = $result['error'] ?? 'No se ha podido cambiar el equipo activo';
                        }
                    }
                }
                }
            }
        }
    } elseif ($action === 'delete_team') {
        $teamId = (int) ($_POST['team_id'] ?? 0);

        if ($teamId <= 0) {
            $errors[] = 'Selecciona un equipo válido';
        } else {
            if (!$activeOrganizationId) {
                $errors[] = 'Necesitas seleccionar una organización activa';
            } else {
                $team = getTeamById($conn, $teamId, $activeOrganizationId);

                if (!$team) {
                    $errors[] = 'Equipo no encontrado o no tienes acceso';
            } else {
                $teamOrganizationId = (int) ($team['organization_id'] ?? 0);

                $roleStatement = $conn->prepare(
                    'SELECT role
                     FROM organization_members
                     WHERE user_id = :user_id
                       AND organization_id = :organization_id
                       AND is_active = 1
                       AND COALESCE(moderation_status, "active") = "active"
                     LIMIT 1'
                );
                $roleStatement->bindValue(':user_id', $userId, PDO::PARAM_INT);
                $roleStatement->bindValue(':organization_id', $teamOrganizationId, PDO::PARAM_INT);
                $roleStatement->execute();
                $roleRow = $roleStatement->fetch();
                $role = strtolower((string) ($roleRow['role'] ?? ''));

                if ($role !== 'owner') {
                    $errors[] = 'Solo el owner puede eliminar equipos';
                } elseif (empty(deleteTeam($conn, $teamId, $teamOrganizationId))) {
                    $errors[] = 'No se ha podido eliminar el equipo';
                } else {
                    if (!empty($_SESSION['active_team_id']) && (int) $_SESSION['active_team_id'] === $teamId) {
                        unset($_SESSION['active_team_id']);
                    }
                    if (!empty($_SESSION['user']['team_id']) && (int) $_SESSION['user']['team_id'] === $teamId) {
                        unset($_SESSION['user']['team_id'], $_SESSION['user']['team']);
                    }

                    $_SESSION['flash_success'] = 'Equipo eliminado';
                    header('Location: ' . $returnTo);
                    exit;
                }
                }
            }
        }
    } elseif ($action === 'invite_member') {
        $teamId = (int) ($_POST['team_id'] ?? 0);
        $email = trim((string) ($_POST['email'] ?? ''));
        $role = strtolower(trim((string) ($_POST['role'] ?? 'player')));
        $allowedRoles = ['admin', 'coach', 'player', 'analyst', 'substitute'];

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
                    setActiveOrganizationContext($conn, $userId, (int) $result['team']['organization_id']);
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
        $teamName = trim((string) ($_POST['name'] ?? ''));
        $teamTag = trim((string) ($_POST['tag'] ?? ''));
        $teamDescription = trim((string) ($_POST['description'] ?? ''));
        $gameId = (int) ($_POST['game_id'] ?? 0);

        // 1. Intentar obtener una organización existente
        $targetOrgId = 0;
        if (!empty($userOrganizations)) {
            foreach ($userOrganizations as $org) {
                if ($activeOrganizationId > 0 && (int) $org['id'] === (int) $activeOrganizationId) {
                    $targetOrgId = (int) $org['id'];
                    break;
                }
            }
            if ($targetOrgId <= 0) {
                $targetOrgId = (int) $userOrganizations[0]['id'];
            }
        }

        // 2. SI NO HAY ORGANIZACIÓN: La creamos automáticamente para el usuario
        if ($targetOrgId <= 0) {
            try {
                // Creamos una organización con el nombre del usuario o el del equipo
                $orgName = "Valorant Organization";
                // Generamos un slug único básico
                $orgSlug = strtolower(preg_replace('/[^A-Za-z0-9-]+/', '-', $orgName)) . '-' . time();
                
                // INSERT en organizations incluyendo owner_id
                $stmtOrg = $conn->prepare('
                    INSERT INTO organizations (name, slug, owner_id, description, created_at, updated_at) 
                    VALUES (:name, :slug, :owner_id, :description, NOW(), NOW())
                ');
                
                $stmtOrg->execute([
                    ':name'     => $orgName,
                    ':slug'     => $orgSlug,
                    ':owner_id' => $userId, // Aquí asignamos al usuario como dueño legal
                    ':description' => 'Organización creada automáticamente al crear un equipo.'
                ]);
                $targetOrgId = (int) $conn->lastInsertId();

                // Hacer al usuario OWNER activo de esta nueva organización para evitar bloqueos de contexto
                $stmtMem = $conn->prepare('INSERT INTO organization_members (organization_id, user_id, role, moderation_status, joined_at, is_active) VALUES (?, ?, "owner", "active", NOW(), 1)');
                $stmtMem->execute([$targetOrgId, $userId]);
                
                // Refrescar las organizaciones del usuario en la sesión para que el resto del script las vea
                $userOrganizations = getUserOrganizations($conn, $userId);
            } catch (Exception $e) {
                $errors[] = 'No se pudo crear una organización base: ' . $e->getMessage();
            }
        }

        // 3. Validaciones de nombre y juego
        if ($teamName === '') { $errors[] = 'El nombre del equipo es obligatorio'; }
        if ($gameId <= 0) { $errors[] = 'Selecciona un juego'; }

        if (empty($errors) && $targetOrgId > 0) {
            if (teamExistsByNameAndGame($conn, $targetOrgId, $teamName, $gameId)) {
                $errors[] = 'Ya existe este equipo en la organización';
            } else {
                try {
                    $conn->beginTransaction();

                    $newTeamId = createTeam($conn, $targetOrgId, $gameId, $teamName, $teamTag ?: null, $teamDescription ?: null);
                    if ($newTeamId <= 0) {
                        throw new RuntimeException('No se pudo crear el equipo.');
                    }

                    $joinResult = joinTeam($conn, $newTeamId, $userId, 'owner');
                    if (empty($joinResult['success'])) {
                        throw new RuntimeException((string) ($joinResult['error'] ?? 'No se pudo asociar el usuario al equipo recién creado.'));
                    }

                    $conn->commit();

                    // Sincronizar contextos para que al recargar todo esté en su sitio
                    setActiveOrganizationContext($conn, $userId, $targetOrgId);
                    setActiveTeamContext($conn, $targetOrgId, $newTeamId);

                    $_SESSION['flash_success'] = '¡Equipo creado correctamente!';
                    header('Location: ' . $returnTo);
                    exit;
                } catch (Throwable $e) {
                    if ($conn->inTransaction()) {
                        $conn->rollBack();
                    }
                    $errors[] = 'Error al crear el equipo: ' . $e->getMessage();
                }
            }
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
}

// --- REFRESCO FINAL DE DATOS (IMPORTANTE PARA LA VISTA) ---
// Volvemos a cargar los equipos después de cualquier POST para que el HTML muestre la realidad
if ($activeOrganizationId && $userId) {
    $teams = getUserOrganizationTeams($conn, (int) $activeOrganizationId, $userId);
    
    // Si el usuario acaba de unirse o crear, asegurar que activeTeam no sea null
    if (!$activeTeamId && !empty($teams)) {
        $activeTeamId = (int) $teams[0]['id'];
        $activeTeam = $teams[0];
    } else {
        foreach ($teams as $t) {
            if ((int)$t['id'] === (int)$activeTeamId) {
                $activeTeam = $t;
                break;
            }
        }
    }
}

// build membership map for current user to show only active-member actions
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
                        No tienes un equipo activo. Activa uno para gestionar el contenido desde aquí.
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
            <div class="success-box" style="border-color: rgba(46, 204, 113, 0.4); background: rgba(46, 204, 113, 0.1); margin-bottom: 16px; border-left: 4px solid var(--success); color: var(--text-main);">
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
                                <button class="btn btn-outline" type="button" disabled title="Solo los miembros activos pueden activar este equipo">Usar este equipo</button>
                            <?php endif; ?>

                            <?php if ($userId && !empty($userTeamIds[(int)$team['id']])): ?>
                                <form method="post" style="display:inline-block; margin-left:8px;">
                                    <input type="hidden" name="action" value="unjoin_team" />
                                    <input type="hidden" name="team_id" value="<?php echo (int) $team['id']; ?>" />
                                    <button class="btn btn-outline" type="submit">Salir</button>
                                </form>
                            <?php endif; ?>

                            <?php if (strtolower((string) $currentOrganizationRole) === 'owner'): ?>
                                <form method="post" style="display:inline-block; margin-left:8px;" onsubmit="return confirm('¿Seguro que quieres eliminar este equipo? Esta acción no se puede deshacer.');">
                                    <input type="hidden" name="action" value="delete_team" />
                                    <input type="hidden" name="team_id" value="<?php echo (int) $team['id']; ?>" />
                                    <input type="hidden" name="return_to" value="<?php echo htmlspecialchars($appCurrentRequestUri ?? 'app.php?view=teams', ENT_QUOTES, 'UTF-8'); ?>" />
                                    <button class="btn btn-secondary" type="submit">Eliminar</button>
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

        <?php 
            // Verificamos si el usuario es el coach (u otro rol con permisos) del equipo activo
            $isTeamManager = false;
            if ($activeTeam) {
                // Si el usuario es el creador/coach del equipo activo según la lista de sus equipos
                foreach ($teams as $t) {
                    if ((int)$t['id'] === (int)$activeTeamId && $t['member_role'] === 'owner') {
                        $isTeamManager = true;
                        break;
                    }
                }
            }
        ?>

        <?php if (($canManageInvitations || $isTeamManager) && $activeTeamId !== null): ?>
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
                            <option value="admin">Admin</option>
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
    </div>
</div>