<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/notification_functions.php';

global $conn;
$teamId = (int) ($_GET['team_id'] ?? 0);
$team = null;
if ($teamId > 0) {
    $stmt = $conn->prepare('SELECT t.*, g.name AS game_name, o.name AS organization_name FROM teams t LEFT JOIN games g ON g.id = t.game_id LEFT JOIN organizations o ON o.id = t.organization_id WHERE t.id = :id LIMIT 1');
    $stmt->bindValue(':id', $teamId, PDO::PARAM_INT);
    $stmt->execute();
    $team = $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isLogged() && $team) {
    // Create join request by notifying owners/admins
    $userId = (int) ($_SESSION['user']['id'] ?? 0);
    $adminsStmt = $conn->prepare('SELECT user_id FROM organization_members WHERE organization_id = :org_id AND role IN ("owner","admin") AND is_active = 1');
    $adminsStmt->bindValue(':org_id', (int) $team['organization_id'], PDO::PARAM_INT);
    $adminsStmt->execute();
    $admins = $adminsStmt->fetchAll();
    foreach ($admins as $a) {
        createNotification($conn, (int)$a['user_id'], 'team_join', $teamId, 'El usuario ' . ($_SESSION['user']['name'] ?? 'un usuario') . ' solicita unirse al equipo ' . ($team['name'] ?? '') . '.');
    }
    $_SESSION['flash_success'] = 'Se ha solicitado unirse al equipo. Los admins han sido notificados.';
    header('Location: team_profile.php?team_id=' . $teamId);
    exit;
}

if (empty($layoutIncluded)) { require __DIR__ . '/includes/layout-start.php'; }
?>
<section class="page">
  <div class="container">
    <?php if (!$team): ?>
      <div class="card"><div class="small">Equipo</div><h2 class="h3">Equipo no encontrado</h2></div>
    <?php else: ?>
      <div class="card">
        <div class="dashboard-section-head">
          <div>
            <div class="small">Perfil de equipo</div>
            <h2 class="h3"><?php echo htmlspecialchars($team['name'], ENT_QUOTES, 'UTF-8'); ?></h2>
            <div class="small"><?php echo htmlspecialchars($team['game_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?> · <?php echo htmlspecialchars($team['organization_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
          </div>
          <div>
            <?php if (isLogged()): ?>
              <form method="post"><button class="btn btn-primary" type="submit">Solicitar unirse</button></form>
            <?php else: ?>
              <a class="btn btn-primary" href="login.php">Iniciar sesión para solicitar unirse</a>
            <?php endif; ?>
          </div>
        </div>

        <p><?php echo htmlspecialchars($team['description'] ?: 'Sin descripción', ENT_QUOTES, 'UTF-8'); ?></p>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php if (empty($layoutIncluded)) { require __DIR__ . '/includes/layout-end.php'; } ?>
