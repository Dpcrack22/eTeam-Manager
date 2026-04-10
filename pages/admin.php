<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/organization_functions.php';
require_once __DIR__ . '/../includes/team_functions.php';

requireAuth();

global $conn;

$currentUser = $_SESSION['user'] ?? [];
$userId = (int) ($currentUser['id'] ?? 0);
$currentRole = strtolower((string) ($currentUser['role'] ?? ''));
$canManageModeration = canManageOrganizationModeration($currentRole);
$activeOrganizationId = getActiveOrganizationId($conn, $userId);
$activeOrganization = $activeOrganizationId ? getOrganizationById($conn, $activeOrganizationId, $userId) : false;
$errors = [];
$successMessage = '';

if (!$canManageModeration) {
    $errors[] = 'No tienes permisos para acceder al panel de administracion';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canManageModeration) {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'moderate_member') {
        $targetUserId = (int) ($_POST['target_user_id'] ?? 0);
        $status = strtolower(trim((string) ($_POST['moderation_status'] ?? '')));
        $reason = trim((string) ($_POST['reason'] ?? ''));
        $until = trim((string) ($_POST['until'] ?? ''));

        if ($activeOrganizationId === null) {
            $errors[] = 'Primero necesitas una organizacion activa';
        } elseif ($targetUserId <= 0) {
            $errors[] = 'Selecciona un miembro valido';
        } elseif (!in_array($status, ['active', 'suspended', 'banned'], true)) {
            $errors[] = 'Selecciona un estado valido';
        } else {
            $memberStatement = $conn->prepare(
                'SELECT om.user_id, om.role, om.moderation_status, u.username
                 FROM organization_members om
                 INNER JOIN users u ON u.id = om.user_id
                 WHERE om.organization_id = :organization_id AND om.user_id = :user_id LIMIT 1'
            );
            $memberStatement->bindValue(':organization_id', (int) $activeOrganizationId, PDO::PARAM_INT);
            $memberStatement->bindValue(':user_id', $targetUserId, PDO::PARAM_INT);
            $memberStatement->execute();
            $targetMember = $memberStatement->fetch();

            if (!$targetMember) {
                $errors[] = 'El miembro no existe en esta organizacion';
            } elseif ((string) $targetMember['role'] === 'owner' && $currentRole !== 'owner') {
                $errors[] = 'Solo el owner puede moderar al owner';
            } elseif ($targetUserId === $userId) {
                $errors[] = 'No puedes moderarte a ti mismo';
            } else {
                $result = setOrganizationMemberModeration(
                    $conn,
                    (int) $activeOrganizationId,
                    $targetUserId,
                    $userId,
                    $status,
                    $reason,
                    $until !== '' ? $until : null
                );

                if (!empty($result['success'])) {
                    $_SESSION['flash_success'] = $status === 'active' ? 'Miembro restaurado' : 'Miembro moderado';
                    header('Location: app.php?view=admin');
                    exit;
                }

                $errors[] = $result['error'] ?? 'No se ha podido aplicar la moderacion';
            }
        }
    }
}

if (!empty($_SESSION['flash_success'])) {
    $successMessage = (string) $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}

$organizationMembers = $activeOrganizationId ? getOrganizationMembers($conn, (int) $activeOrganizationId) : [];
$organizationStats = $activeOrganizationId ? getOrganizationStats($conn, (int) $activeOrganizationId) : ['members' => 0, 'teams' => 0];

$pageTitle = 'Panel de administracion';
$pageEyebrow = 'Modulo';
$pageDescription = 'Moderacion de miembros, sanciones y control interno de la organizacion activa.';
$activeSection = 'admin';
?>

<section class="admin-page">
    <div class="dashboard-hero card">
        <div>
            <div class="small">Administracion interna</div>
            <h2 class="h2">Panel de administracion</h2>
            <p>Desde aquí puedes moderar miembros de la organización activa, suspender o banear accesos y restaurarlos cuando toque.</p>
            <div class="stack-sm">
                <span class="badge badge-info"><?php echo (int) $organizationStats['members']; ?> miembros activos</span>
                <span class="badge badge-success"><?php echo (int) $organizationStats['teams']; ?> equipos activos</span>
            </div>
        </div>
        <div class="dashboard-hero-meta">
            <div class="dashboard-hero-chip">
                <div class="small">Organizacion</div>
                <div class="dashboard-hero-value"><?php echo htmlspecialchars($activeOrganization['name'] ?? 'Sin organización', ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
            <div class="dashboard-hero-chip">
                <div class="small">Rol</div>
                <div class="dashboard-hero-value"><?php echo htmlspecialchars($currentRole ?: 'Member', ENT_QUOTES, 'UTF-8'); ?></div>
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

    <?php if (!$canManageModeration || !$activeOrganizationId): ?>
        <div class="card dashboard-empty-state">
            No tienes acceso a esta vista o no hay una organización activa con permisos de administracion.
        </div>
    <?php elseif (empty($organizationMembers)): ?>
        <div class="card dashboard-empty-state">
            Todavía no hay miembros para moderar en esta organización.
        </div>
    <?php else: ?>
        <div class="card admin-panel-card">
            <div class="dashboard-section-head">
                <div>
                    <div class="small">Miembros</div>
                    <h3 class="h3">Moderacion de usuarios</h3>
                </div>
            </div>

            <div class="admin-member-list">
                <?php foreach ($organizationMembers as $member): ?>
                    <article class="admin-member-card<?php echo (string) $member['moderation_status'] !== 'active' ? ' is-restricted' : ''; ?>">
                        <div class="admin-member-copy">
                            <div class="admin-member-top">
                                <div>
                                    <div class="dashboard-list-title"><?php echo htmlspecialchars((string) $member['username'], ENT_QUOTES, 'UTF-8'); ?></div>
                                    <div class="dashboard-list-meta"><?php echo htmlspecialchars((string) $member['email'], ENT_QUOTES, 'UTF-8'); ?></div>
                                </div>
                                <div class="stack-sm" style="align-items:flex-end;">
                                    <span class="badge badge-info"><?php echo htmlspecialchars((string) $member['role'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    <span class="badge <?php echo (string) $member['moderation_status'] === 'active' ? 'badge-success' : 'badge-error'; ?>"><?php echo htmlspecialchars(ucfirst((string) $member['moderation_status']), ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                            </div>
                            <div class="small">
                                <?php if (!empty($member['moderation_reason'])): ?>
                                    Motivo: <?php echo htmlspecialchars((string) $member['moderation_reason'], ENT_QUOTES, 'UTF-8'); ?> ·
                                <?php endif; ?>
                                Estado de equipo: <?php echo (int) $member['is_active'] ? 'Activo' : 'Inactivo'; ?>
                            </div>
                        </div>

                        <form class="admin-moderation-form" method="post" novalidate>
                            <input type="hidden" name="action" value="moderate_member" />
                            <input type="hidden" name="target_user_id" value="<?php echo (int) $member['user_id']; ?>" />

                            <div class="field">
                                <label for="moderation_status_<?php echo (int) $member['user_id']; ?>">Estado</label>
                                <select id="moderation_status_<?php echo (int) $member['user_id']; ?>" name="moderation_status">
                                    <option value="active" <?php echo (string) $member['moderation_status'] === 'active' ? 'selected' : ''; ?>>Restaurar</option>
                                    <option value="suspended" <?php echo (string) $member['moderation_status'] === 'suspended' ? 'selected' : ''; ?>>Suspender</option>
                                    <option value="banned" <?php echo (string) $member['moderation_status'] === 'banned' ? 'selected' : ''; ?>>Banear</option>
                                </select>
                            </div>

                            <div class="field">
                                <label for="moderation_reason_<?php echo (int) $member['user_id']; ?>">Motivo</label>
                                <input id="moderation_reason_<?php echo (int) $member['user_id']; ?>" name="reason" type="text" placeholder="Incumplimiento de normas" value="<?php echo htmlspecialchars((string) ($member['moderation_reason'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                            </div>

                            <div class="field">
                                <label for="moderation_until_<?php echo (int) $member['user_id']; ?>">Hasta</label>
                                <input id="moderation_until_<?php echo (int) $member['user_id']; ?>" name="until" type="datetime-local" value="<?php echo !empty($member['moderation_until']) ? htmlspecialchars(date('Y-m-d\TH:i', strtotime((string) $member['moderation_until'])), ENT_QUOTES, 'UTF-8') : ''; ?>" />
                            </div>

                            <button class="btn btn-primary" type="submit"><?php echo (string) $member['moderation_status'] === 'active' ? 'Aplicar' : 'Actualizar'; ?></button>
                        </form>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</section>
