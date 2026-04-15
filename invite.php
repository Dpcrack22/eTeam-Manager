<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/invitation_functions.php';
require_once __DIR__ . '/includes/organization_functions.php';
require_once __DIR__ . '/includes/team_functions.php';

$hideSidebar = true;
$token = trim((string) ($_GET['token'] ?? ''));
$team = $token !== '' ? getTeamByInviteToken($conn, $token) : false;
$currentUser = $_SESSION['user'] ?? [];
$isAuthenticated = isLogged();
$userId = (int) ($currentUser['id'] ?? 0);
$errors = [];
$successMessage = '';
$pendingInvitation = false;

if ($isAuthenticated && $team) {
    $pendingInvitations = getPendingTeamInvitationsForUser($conn, $userId);

    foreach ($pendingInvitations as $pendingInvitationCandidate) {
        if ((int) $pendingInvitationCandidate['team_id'] === (int) $team['id']) {
            $pendingInvitation = $pendingInvitationCandidate;
            break;
        }
    }
}

if ($team && $isAuthenticated && !$pendingInvitation && (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    if (!isUserActiveMember($conn, (int) $team['id'], $userId)) {
        $joinResult = joinTeam($conn, (int) $team['id'], $userId, 'player');

        if (!empty($joinResult['success'])) {
            if (!empty($joinResult['team'])) {
                setActiveTeamContext($conn, (int) $joinResult['team']['organization_id'], (int) $joinResult['team']['id']);
            }

            $_SESSION['flash_success'] = 'Te has unido al equipo desde el enlace de invitación';
            header('Location: app.php?view=teams');
            exit;
        }

        $errors[] = $joinResult['error'] ?? 'No se ha podido unir al equipo desde el enlace';
    } else {
        setActiveTeamContext($conn, (int) $team['organization_id'], (int) $team['id']);
        $_SESSION['flash_success'] = 'Ya perteneces a este equipo';
        header('Location: app.php?view=teams');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['invite_action'] ?? '');

    if (!$team) {
        $errors[] = 'El enlace de invitación no es válido';
    } elseif (!$isAuthenticated) {
        header('Location: app.php?view=login&cb=1&return_to=' . urlencode('invite.php?token=' . $token));
        exit;
    } elseif ($action === 'join') {
        if (isUserActiveMember($conn, (int) $team['id'], $userId)) {
            setActiveTeamContext($conn, (int) $team['organization_id'], (int) $team['id']);
            $_SESSION['flash_success'] = 'Ya perteneces a este equipo';
            header('Location: app.php?view=teams');
            exit;
        }

        $joinResult = joinTeam($conn, (int) $team['id'], $userId, 'player');
        if (!empty($joinResult['success'])) {
            if (!empty($joinResult['team'])) {
                setActiveTeamContext($conn, (int) $joinResult['team']['organization_id'], (int) $joinResult['team']['id']);
            }

            $_SESSION['flash_success'] = 'Te has unido al equipo';
            header('Location: app.php?view=teams');
            exit;
        }

        $errors[] = $joinResult['error'] ?? 'No se ha podido unir al equipo';
    } elseif (!$pendingInvitation) {
        $errors[] = 'No tienes una invitación pendiente para este equipo';
    } elseif ($action === 'accept') {
        $result = acceptTeamInvitation($conn, (int) $pendingInvitation['id'], $userId);

        if (!empty($result['success'])) {
            if (!empty($result['team'])) {
                setActiveTeamContext($conn, (int) $result['team']['organization_id'], (int) $result['team']['id']);
            }

            $_SESSION['flash_success'] = 'Invitación aceptada';
            header('Location: app.php?view=teams');
            exit;
        }

        $errors[] = $result['error'] ?? 'No se ha podido aceptar la invitación';
    } elseif ($action === 'decline') {
        $result = declineTeamInvitation($conn, (int) $pendingInvitation['id'], $userId);

        if (!empty($result['success'])) {
            $_SESSION['flash_success'] = 'Invitación rechazada';
            header('Location: app.php?view=notifications');
            exit;
        }

        $errors[] = $result['error'] ?? 'No se ha podido rechazar la invitación';
    }
}

if (!$team && $token !== '') {
    $errors[] = 'El enlace de invitación no existe o ha caducado';
}

$pageTitle = 'Invitación de equipo';
$pageEyebrow = 'Acceso';
$pageDescription = 'Revisa una invitación de equipo compartida por enlace y responde desde aquí.';

require __DIR__ . '/includes/layout-start.php';
?>
<div class="auth-page">
    <div class="auth-card card auth-card--wide">
        <div class="auth-card-head">
            <div class="small">Invitación compartida</div>
            <h2 class="h3"><?php echo htmlspecialchars((string) ($team['name'] ?? 'Invitación de equipo'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <p><?php echo htmlspecialchars($team ? 'Si tu usuario tiene una invitación pendiente, puedes aceptarla o rechazarla. Si no, entrarás directamente al equipo con este enlace.' : 'No se ha podido resolver el enlace compartido.', ENT_QUOTES, 'UTF-8'); ?></p>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="error-container">
                <?php foreach ($errors as $error): ?>
                    <div class="error-box"><?php echo htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($team): ?>
            <div class="landing-list">
                <div class="landing-list-item"><strong>Equipo:</strong> <?php echo htmlspecialchars((string) $team['name'], ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="landing-list-item"><strong>Juego:</strong> <?php echo htmlspecialchars((string) $team['game_name'], ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="landing-list-item"><strong>Tag:</strong> <?php echo htmlspecialchars((string) ($team['tag'] ?: '--'), ENT_QUOTES, 'UTF-8'); ?></div>
            </div>

            <?php if ($isAuthenticated && $pendingInvitation): ?>
                <form class="form auth-form" method="post" novalidate>
                    <div class="field">
                        <label for="invite_role">Rol invitado</label>
                        <input id="invite_role" type="text" value="<?php echo htmlspecialchars((string) $pendingInvitation['role'], ENT_QUOTES, 'UTF-8'); ?>" readonly />
                    </div>

                    <div class="auth-actions">
                        <button class="btn btn-primary" type="submit" name="invite_action" value="accept">Aceptar invitación</button>
                        <button class="btn btn-secondary" type="submit" name="invite_action" value="decline">Rechazar</button>
                    </div>
                </form>
            <?php elseif ($isAuthenticated): ?>
                <div class="dashboard-empty-state">Este enlace puede usarse para entrar directamente al equipo.</div>
                <form class="auth-actions" method="post" style="margin-top: 16px;">
                    <input type="hidden" name="invite_action" value="join" />
                    <button class="btn btn-primary" type="submit">Unirme al equipo</button>
                    <a class="btn btn-secondary" href="app.php?view=teams">Ver equipos</a>
                </form>
            <?php else: ?>
                <div class="dashboard-empty-state">Necesitas iniciar sesión para usar el enlace y entrar al equipo.</div>
                <div class="auth-actions" style="margin-top: 16px;">
                    <a class="btn btn-primary" href="app.php?view=login&cb=1&amp;return_to=<?php echo urlencode('invite.php?token=' . $token); ?>">Entrar</a>
                    <a class="btn btn-secondary" href="app.php?view=register&cb=1&amp;return_to=<?php echo urlencode('invite.php?token=' . $token); ?>">Crear cuenta</a>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
<?php require __DIR__ . '/includes/layout-end.php'; ?>