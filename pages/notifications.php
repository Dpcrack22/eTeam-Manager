<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/invitation_functions.php';
require_once __DIR__ . '/../includes/notification_functions.php';
require_once __DIR__ . '/../includes/organization_functions.php';
require_once __DIR__ . '/../includes/team_functions.php'; // Asegúrate de incluir esto

requireAuth();

global $conn;

$currentUser = $_SESSION['user'] ?? [];
$userId = (int) ($currentUser['id'] ?? 0);
$errors = [];
$successMessage = '';

// Obtener el contexto actual del usuario (Organización y Equipo activos)
$activeOrganizationId = getActiveOrganizationContext($conn, $userId);
$activeTeamId = getActiveTeamId($conn, $activeOrganizationId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['notification_action'] ?? '');

    if ($action === 'accept_join_request') {
        $playerId = (int)($_POST['player_id'] ?? 0);
        $notificationId = (int)($_POST['notification_id'] ?? 0);
        
        // 1. Obtenemos el equipo activo del capitán que está aceptando
        // Asegúrate de que $activeTeamId está definido en tu contexto global
        if ($activeTeamId > 0 && $playerId > 0) {
            
            // 2. IMPORTANTE: Insertar al jugador en la ORGANIZACIÓN primero (si no está)
            // Esto permite que el sistema le deje "ver" los recursos de la organización
            $stmtOrg = $conn->prepare("INSERT IGNORE INTO organization_members (organization_id, user_id, role, is_active) VALUES (:oid, :uid, 'member', 1)");
            $stmtOrg->execute(['oid' => $activeOrganizationId, 'uid' => $playerId]);

            // 3. Insertar al jugador en el EQUIPO como ACTIVO
            // Usamos una consulta directa si joinTeam() te está fallando
            $stmtTeam = $conn->prepare("INSERT INTO team_members (team_id, user_id, role, is_active) VALUES (:tid, :uid, 'player', 1) ON DUPLICATE KEY UPDATE is_active = 1");
            $success = $stmtTeam->execute(['tid' => $activeTeamId, 'uid' => $playerId]);

            if ($success) {
                // 4. Marcar la notificación como leída para que DESAPAREZCA de la lista
                markNotificationAsRead($conn, $notificationId, $userId);

                // 5. Notificar al jugador que ha sido aceptado
                createNotification($conn, $playerId, 'team_invite_accepted', $activeTeamId, "¡Tu solicitud para unirte al equipo ha sido aceptada!");

                $_SESSION['flash_success'] = 'Jugador aceptado y añadido al equipo.';
                header('Location: app.php?view=notifications');
                exit;
            } else {
                $errors[] = "Error al actualizar la base de datos.";
            }
        } else {
            $errors[] = "No tienes un equipo activo seleccionado para aceptar jugadores.";
        }
    }

    if ($action === 'mark_all_read') {
        markAllNotificationsAsRead($conn, $userId);
        $_SESSION['flash_success'] = 'Todas las notificaciones se han marcado como leidas';
        header('Location: app.php?view=notifications');
        exit;
    }

    if ($action === 'mark_read') {
        $notificationId = (int) ($_POST['notification_id'] ?? 0);
        if ($notificationId > 0) {
            markNotificationAsRead($conn, $notificationId, $userId);
            $_SESSION['flash_success'] = 'Notificacion marcada como leida';
            header('Location: app.php?view=notifications');
            exit;
        }

        $errors[] = 'Selecciona una notificacion valida';
    }

    if ($action === 'accept_invite' || $action === 'decline_invite') {
        $invitationId = (int) ($_POST['invitation_id'] ?? 0);
        if ($invitationId <= 0) {
            $errors[] = 'Selecciona una invitacion valida';
        } elseif ($action === 'accept_invite') {
            $result = acceptTeamInvitation($conn, $invitationId, $userId);
            if (!empty($result['success'])) {
                if (!empty($result['team'])) {
                    setActiveOrganizationContext($conn, $userId, (int) $result['team']['organization_id']);
                    setActiveTeamContext($conn, (int) $result['team']['organization_id'], (int) $result['team']['id']);
                }

                $_SESSION['flash_success'] = 'Invitación aceptada';
                header('Location: app.php?view=notifications');
                exit;
            }

            $errors[] = $result['error'] ?? 'No se ha podido aceptar la invitación';
        } else {
            $result = declineTeamInvitation($conn, $invitationId, $userId);
            if (!empty($result['success'])) {
                $_SESSION['flash_success'] = 'Invitación rechazada';
                header('Location: app.php?view=notifications');
                exit;
            }

            $errors[] = $result['error'] ?? 'No se ha podido rechazar la invitación';
        }
    }
}

if (!empty($_SESSION['flash_success'])) {
    $successMessage = (string) $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}

$notifications = getRecentNotifications($conn, $userId, 50);
$unreadCount = getUnreadNotificationsCount($conn, $userId);
$pendingInvitations = getPendingTeamInvitationsForUser($conn, $userId);
$pendingInvitationsById = [];

foreach ($pendingInvitations as $pendingInvitation) {
    $pendingInvitationsById[(int) $pendingInvitation['id']] = $pendingInvitation;
}

function notificationViewLink(array $notification): string
{
    return match ((string) $notification['type']) {
        'team_join', 'team_leave', 'organization_invite', 'team_invite', 'team_invite_accepted', 'team_invite_declined' => 'app.php?view=teams',
        'moderation_suspended', 'moderation_banned', 'moderation_restored' => 'admin.php',
        'event' => 'app.php?view=calendar',
        'task' => 'app.php?view=boards',
        'note' => 'app.php?view=notes',
        default => 'app.php?view=dashboard',
    };
}

$pageTitle = 'Notificaciones';
$pageEyebrow = 'Modulo';
$pageDescription = 'Centro de avisos, invitaciones y actividad reciente para el usuario autenticado.';
$activeSection = 'notifications';
?>

<section class="notifications-page">
    <div class="dashboard-hero card">
        <div>
            <div class="small">Centro de avisos</div>
            <h2 class="h2">Notificaciones</h2>
            <p>Desde aquí puedes revisar la actividad reciente de la app, abrir el módulo relacionado y marcar avisos como leídos.</p>
            <div class="stack-sm">
                <span class="badge badge-info"><?php echo (int) $unreadCount; ?> sin leer</span>
                <span class="badge badge-success"><?php echo count($notifications); ?> recientes</span>
            </div>
        </div>
        <div class="dashboard-hero-meta">
            <div class="dashboard-hero-chip">
                <div class="small">Estado</div>
                <div class="dashboard-hero-value"><?php echo $unreadCount > 0 ? 'Pendiente' : 'Al día'; ?></div>
            </div>
        </div>
    </div>

    <?php if (!empty($successMessage)): ?>
        <div class="error-box app-feedback app-feedback-success" data-flash-message role="status" aria-live="polite">
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
        <div class="card">
            <div class="dashboard-section-head">
                <div>
                    <div class="small">Invitaciones pendientes</div>
                    <h3 class="h3">Responde desde aquí</h3>
                </div>
            </div>

            <div class="team-invitations-list">
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
                                <input type="hidden" name="notification_action" value="accept_invite" />
                                <input type="hidden" name="invitation_id" value="<?php echo (int) $invitation['id']; ?>" />
                                <button class="btn btn-primary" type="submit">Aceptar</button>
                            </form>
                            <form method="post">
                                <input type="hidden" name="notification_action" value="decline_invite" />
                                <input type="hidden" name="invitation_id" value="<?php echo (int) $invitation['id']; ?>" />
                                <button class="btn btn-secondary" type="submit">Rechazar</button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="dashboard-section-head">
            <div>
                <div class="small">Historial</div>
                <h3 class="h3">Actividad reciente</h3>
            </div>
            <form method="post">
                <input type="hidden" name="notification_action" value="mark_all_read" />
                <button class="btn btn-secondary" type="submit"<?php echo empty($notifications) ? ' disabled' : ''; ?>>Marcar todo como leído</button>
            </form>
        </div>

        <?php if (empty($notifications)): ?>
            <div class="dashboard-empty-state">No hay notificaciones por ahora.</div>
        <?php else: ?>
            <div class="notifications-list">
                <?php foreach ($notifications as $notification): ?>
                    <article class="notification-card<?php echo empty($notification['is_read']) ? ' is-unread' : ''; ?>">
                        <div class="notification-card-main">
                            <div class="notification-card-top">
                                <span class="badge badge-info"><?php echo htmlspecialchars(notificationTypeLabel((string) $notification['type']), ENT_QUOTES, 'UTF-8'); ?></span>
                                <span class="small"><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime((string) $notification['created_at'])), ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                            <p class="notification-card-message"><?php echo htmlspecialchars((string) $notification['message'], ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>

                        <div class="notification-card-actions">
                            <a class="btn btn-secondary" href="<?php echo htmlspecialchars(notificationViewLink($notification), ENT_QUOTES, 'UTF-8'); ?>">Abrir módulo</a>

                            <?php if ((string) $notification['type'] === 'team_invite' && !empty($notification['reference_id']) && isset($pendingInvitationsById[(int) $notification['reference_id']])): ?>
                                <form method="post">
                                    <input type="hidden" name="notification_action" value="accept_invite" />
                                    <input type="hidden" name="invitation_id" value="<?php echo (int) $notification['reference_id']; ?>" />
                                    <button class="btn btn-primary" type="submit">Aceptar</button>
                                </form>
                                <form method="post">
                                    <input type="hidden" name="notification_action" value="decline_invite" />
                                    <input type="hidden" name="invitation_id" value="<?php echo (int) $notification['reference_id']; ?>" />
                                    <button class="btn btn-secondary" type="submit">Rechazar</button>
                                </form>

                            <?php elseif ((string) $notification['type'] === 'team_join_request' && !empty($notification['reference_id'])): ?>
                                <form method="post">
                                    <input type="hidden" name="notification_action" value="accept_join_request" />
                                    <input type="hidden" name="player_id" value="<?php echo (int) $notification['reference_id']; ?>" />
                                    <input type="hidden" name="notification_id" value="<?php echo (int) $notification['id']; ?>" />
                                    <button class="btn btn-primary" type="submit">Aceptar Jugador</button>
                                </form>
                                <form method="post">
                                    <input type="hidden" name="notification_action" value="mark_read" />
                                    <input type="hidden" name="notification_id" value="<?php echo (int) $notification['id']; ?>" />
                                    <button class="btn btn-secondary" type="submit">Denegar</button>
                                </form>

                            <?php elseif (empty($notification['is_read'])): ?>
                                <form method="post">
                                    <input type="hidden" name="notification_action" value="mark_read" />
                                    <input type="hidden" name="notification_id" value="<?php echo (int) $notification['id']; ?>" />
                                    <button class="btn btn-primary" type="submit">Marcar leído</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
