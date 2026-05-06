<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/invitation_functions.php';

global $conn;

$username = trim((string) ($_GET['user'] ?? ''));
$shouldCloseLayout = false;
$hideSidebar = true;

$profileStatement = $conn->prepare(
    'SELECT u.id, u.username, u.email, u.avatar_url, u.bio, u.profile_public, u.bio_public, u.created_at, u.last_login_at, u.email_verified_at,
            COUNT(DISTINCT om.organization_id) AS organization_count,
            COUNT(DISTINCT tm.team_id) AS team_count,
            o.name AS organization_name, om.role AS organization_role
     FROM users u
     LEFT JOIN organization_members om ON om.user_id = u.id AND om.is_active = 1
     LEFT JOIN organizations o ON o.id = om.organization_id
     LEFT JOIN team_members tm ON tm.user_id = u.id AND tm.is_active = 1
     WHERE u.username = :username
    GROUP BY u.id, u.username, u.email, u.avatar_url, u.bio, u.profile_public, u.bio_public, u.created_at, u.last_login_at, u.email_verified_at, o.name, om.role
     LIMIT 1'
);
$profileStatement->bindValue(':username', $username, PDO::PARAM_STR);
$profileStatement->execute();
$profileUser = $profileStatement->fetch();

if (!$profileUser) {
    $pageTitle = 'Perfil no encontrado';
    $pageEyebrow = 'Perfil público';
    $pageDescription = 'No existe un perfil público con ese usuario.';
} else {
    $pageTitle = $profileUser['username'] . ' · Perfil público';
    $pageEyebrow = 'Perfil público';
    $pageDescription = 'Perfil público de ' . $profileUser['username'] . ' en eTeam Manager';
}

if (empty($layoutIncluded)) {
    require __DIR__ . '/includes/layout-start.php';
    $shouldCloseLayout = true;
}

$avatarText = 'EM';
if ($profileUser) {
    $avatarText = strtoupper(substr(preg_replace('/\s+/', '', (string) $profileUser['username']), 0, 2));
    if ($avatarText === '') {
        $avatarText = 'EM';
    }
}
?>
<section class="profile-page">
    <?php if (!$profileUser): ?>
        <div class="card">
            <div class="small">Perfil público</div>
            <h2 class="h2">No hemos encontrado ese usuario</h2>
            <p>No existe un perfil público para ese nombre de usuario.</p>
            <div class="auth-actions">
                <a class="btn btn-primary" href="index.php">Volver al inicio</a>
            </div>
        </div>
    <?php else: ?>
        <?php if (empty($profileUser['profile_public']) && !(isLogged() && !empty($_SESSION['user']['id']) && (int) $_SESSION['user']['id'] === (int) $profileUser['id'])): ?>
            <div class="card">
                <div class="small">Perfil privado</div>
                <h2 class="h2">Este perfil no es público</h2>
                <p>El usuario ha marcado su perfil como privado. Si quieres contactar con él, utiliza las herramientas internas del equipo.</p>
                <?php if (isLogged()): ?>
                    <div class="auth-actions">
                        <a class="btn btn-primary" href="app.php?view=teams">Ver equipos</a>
                    </div>
                <?php else: ?>
                    <div class="auth-actions">
                        <a class="btn btn-primary" href="login.php">Iniciar sesión</a>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
        <div class="card profile-hero">
            <div class="profile-hero-copy">
                <div class="small">Perfil público</div>
                <h2 class="profile-hero-title"><?php echo htmlspecialchars($profileUser['username'], ENT_QUOTES, 'UTF-8'); ?></h2>
                <p><?php echo (!empty($profileUser['bio_public']) ? htmlspecialchars($profileUser['bio'] ?: 'Sin bio pública todavía.', ENT_QUOTES, 'UTF-8') : 'El usuario no comparte su bio públicamente.'); ?></p>
                <div class="stack-sm">
                    <span class="badge badge-info"><?php echo htmlspecialchars($profileUser['organization_role'] ?? 'Sin rol', ENT_QUOTES, 'UTF-8'); ?></span>
                    <span class="badge badge-success"><?php echo (int) $profileUser['team_count']; ?> equipos</span>
                    <span class="badge"><?php echo (int) $profileUser['organization_count']; ?> organizaciones</span>
                </div>
            </div>

            <div class="profile-hero-avatar" aria-hidden="true">
                <?php if (!empty($profileUser['avatar_url'])): ?>
                    <img class="profile-avatar-preview" src="<?php echo htmlspecialchars($profileUser['avatar_url'], ENT_QUOTES, 'UTF-8'); ?>" alt="" />
                <?php else: ?>
                    <?php echo htmlspecialchars($avatarText, ENT_QUOTES, 'UTF-8'); ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="profile-layout">
            <div class="card">
                <div class="dashboard-section-head">
                    <div>
                        <div class="small">Actividad visible</div>
                        <h3 class="h3">Resumen público</h3>
                    </div>
                </div>

                <div class="profile-summary-grid">
                    <div class="profile-summary-item">
                        <div class="profile-summary-label">Miembro desde</div>
                        <div class="profile-summary-value"><?php echo htmlspecialchars(date('d/m/Y', strtotime((string) $profileUser['created_at'])), ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>
                    <div class="profile-summary-item">
                        <div class="profile-summary-label">Último acceso</div>
                        <div class="profile-summary-value"><?php echo !empty($profileUser['last_login_at']) ? htmlspecialchars(date('d/m/Y H:i', strtotime((string) $profileUser['last_login_at'])), ENT_QUOTES, 'UTF-8') : 'Sin datos'; ?></div>
                    </div>
                    <div class="profile-summary-item">
                        <div class="profile-summary-label">Organización visible</div>
                        <div class="profile-summary-value"><?php echo htmlspecialchars($profileUser['organization_name'] ?? 'Sin organización', ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>
                    <div class="profile-summary-item">
                        <div class="profile-summary-label">Estado</div>
                        <div class="profile-summary-value"><?php echo !empty($profileUser['email_verified_at']) ? 'Verificado' : 'Pendiente'; ?></div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="dashboard-section-head">
                    <div>
                        <div class="small">Acciones</div>
                        <h3 class="h3">Compartir y editar</h3>
                    </div>
                </div>
                <div class="landing-list">
                    <div class="landing-list-item">Enlace público: <a href="profile.php?user=<?php echo urlencode((string) $profileUser['username']); ?>"><?php echo htmlspecialchars('profile.php?user=' . $profileUser['username'], ENT_QUOTES, 'UTF-8'); ?></a></div>
                    <?php if (isLogged() && !empty($_SESSION['user']['name']) && strcasecmp((string) $_SESSION['user']['name'], (string) $profileUser['username']) === 0): ?>
                        <div class="landing-list-item"><a href="app.php?view=settings">Editar mi perfil</a></div>
                    <?php endif; ?>
                    <?php if (isLogged() && !empty($_SESSION['user']['id']) && (int) $_SESSION['user']['id'] !== (int) $profileUser['id']): ?>
                        <?php // If current user has an active team and role allowing invites, show quick invite form ?>
                        <?php $activeTeamId = !empty($_SESSION['active_team_id']) ? (int) $_SESSION['active_team_id'] : 0; ?>
                        <?php if ($activeTeamId): ?>
                            <form method="post" style="margin-top:8px;">
                                <input type="hidden" name="action" value="invite_user" />
                                <input type="hidden" name="team_id" value="<?php echo $activeTeamId; ?>" />
                                <button class="btn btn-primary" type="submit">Invitar al usuario al equipo activo</button>
                            </form>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</section>
<?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action']) && $_POST['action'] === 'invite_user' && isLogged()):
    $inviterId = (int) ($_SESSION['user']['id'] ?? 0);
    $teamId = (int) ($_POST['team_id'] ?? 0);
    if ($teamId > 0 && $inviterId > 0 && $profileUser) {
        // check inviter role in organization
        $stmt = $conn->prepare('SELECT role FROM organization_members WHERE user_id = :user_id AND organization_id = (SELECT organization_id FROM teams WHERE id = :team_id) AND is_active = 1 LIMIT 1');
        $stmt->bindValue(':user_id', $inviterId, PDO::PARAM_INT);
        $stmt->bindValue(':team_id', $teamId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        $role = strtolower((string) ($row['role'] ?? ''));
        if (in_array($role, ['owner','admin','manager'], true)) {
            require_once __DIR__ . '/includes/notification_functions.php';
            // notify all org admins/owners
            $adminsStmt = $conn->prepare('SELECT user_id FROM organization_members WHERE organization_id = (SELECT organization_id FROM teams WHERE id = :team_id) AND role IN ("owner","admin") AND is_active = 1');
            $adminsStmt->bindValue(':team_id', $teamId, PDO::PARAM_INT);
            $adminsStmt->execute();
            $admins = $adminsStmt->fetchAll();
            foreach ($admins as $a) {
                createNotification($conn, (int)$a['user_id'], 'team_join', $teamId, 'El usuario ' . ($profileUser['username'] ?? 'usuario') . ' ha solicitado unirse al equipo.');
            }
        }
    }
    // Redirect back to profile
    header('Location: ' . ($_SERVER['REQUEST_URI'] ?? 'profile.php?user=' . urlencode((string)$profileUser['username'])));
    exit;
endif; ?>
<?php if ($shouldCloseLayout) { require __DIR__ . '/includes/layout-end.php'; } ?>