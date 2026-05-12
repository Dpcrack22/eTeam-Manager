<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/invitation_functions.php'; // Esencial
requireAuth();

$q = trim((string) ($_GET['q'] ?? ''));
$type = trim((string) ($_GET['type'] ?? 'users'));
$results = [];

// Datos del usuario actual
$currentUserId = (int)($_SESSION['user']['id'] ?? 0);
$currentUserRole = strtolower((string)($_SESSION['user']['role'] ?? ''));
// Verificamos si es Owner o Admin (ajusta los strings según tu BD)
$isPrivileged = in_array($currentUserRole, ['owner', 'admin', 'manager']);

if ($q !== '') {
    global $conn;
    try {
        $searchTerm = '%' . $q . '%';
        if ($type === 'teams') {
            $stmt = $conn->prepare('SELECT id, name, tag, description FROM teams WHERE name LIKE :q1 OR tag LIKE :q2 LIMIT 20');
            $stmt->bindValue(':q1', $searchTerm, PDO::PARAM_STR);
            $stmt->bindValue(':q2', $searchTerm, PDO::PARAM_STR); // Lo mandamos también para el tag
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $stmt = $conn->prepare('SELECT id, username, avatar_url, bio FROM users WHERE username LIKE :q LIMIT 20');
            $stmt->bindValue(':q', $searchTerm, PDO::PARAM_STR);
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        // Si falla, al menos veremos el error en lugar de página en blanco
        echo "<div class='alert alert-danger'>Error en la base de datos: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

$currentOrgId = (int) ($_SESSION['active_organization_id'] ?? ($_SESSION['user']['organization_id'] ?? 0));
$activeTeamId = getActiveTeamId($conn, $currentOrgId);

if ($_SERVER['REQUEST_METHOD'] === "POST") {
    $stmtEmail = $conn->prepare("SELECT email FROM users WHERE id = :id LIMIT 1");
    $stmtEmail->execute(['id' => $targetId]);
    $userToInvite = $stmtEmail->fetch();

    if ($userToInvite && $activeTeamId) {
        // 1. Creamos la invitación oficial en la tabla de invitaciones
        $res = createTeamInvitation($conn, (int)$activeTeamId, $userId, $userToInvite['email'], 'player');
        
        if (!empty($res['success'])) {
            // IMPORTANTE: Obtenemos el ID de la invitación recién creada
            $invitationId = (int)$res['invitation_id']; 

            // 2. Creamos la notificación vinculándola con esa invitación
            // Usamos 'team_invite' como tipo y el ID de la invitación como referencia
            createNotification(
                $conn, 
                $targetId, 
                'team_invite', 
                $invitationId, // <--- Este es el reference_id que hace que salgan los botones
                "Has sido invitado al equipo " . ($activeTeam['name'] ?? 'un equipo')
            );
            
            $successMsg = "¡Invitación enviada!";
        } else {
            $errorMsg = $res['error'] ?? "Error al invitar.";
        }
    }

    // CASO B: Solicitar unirse a un Equipo
    if ($action === 'request_join') {
        // 1. Buscamos al dueño (owner) del equipo
        $stmtOwner = $conn->prepare("
            SELECT om.user_id 
            FROM teams t 
            JOIN organization_members om ON t.organization_id = om.organization_id 
            WHERE t.id = :team_id AND om.role = 'owner' LIMIT 1
        ");
        $stmtOwner->execute(['team_id' => $targetId]);
        $owner = $stmtOwner->fetch();

        if ($owner) {
            // 2. Creamos la notificación. 
            // Usamos 'team_join_request' como tipo y el ID del usuario actual como referencia.
            createNotification(
                $conn, 
                (int)$owner['user_id'], 
                'team_join_request', 
                $userId, // Referencia: El ID del jugador que solicita
                $_SESSION['user']['username'] . " quiere unirse a tu equipo."
            );
            $successMsg = "Solicitud enviada al capitán.";
        }
    }
}
?>

<section class="page">
    <div class="container">
        <div class="card">
            <div class="small text-muted">Buscador global</div>
            <h2 class="h4" style="margin-bottom: 20px;">Explorar la plataforma</h2>

            <form method="get" action="app.php" style="margin-bottom: 30px;">
                <input type="hidden" name="view" value="search">
                <div style="display: flex; gap: 10px; align-items: stretch;">
                    <input name="q" type="search" 
                           placeholder="Buscar por nombre o tag..." 
                           value="<?php echo htmlspecialchars($q); ?>" 
                           style="flex: 4; padding: 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 1rem;" 
                           required>
                    
                    <select name="type" style="flex: 1; min-width: 120px; padding: 0 10px; border: 1px solid #ddd; border-radius: 6px; background: #374151; color: #f3f4f6;">
                        <option value="users" <?php echo $type === 'users' ? 'selected' : ''; ?>>Jugadores</option>
                        <option value="teams" <?php echo $type === 'teams' ? 'selected' : ''; ?>>Equipos</option>
                    </select>
                    
                    <button class="btn btn-primary" type="submit" style="padding: 0 25px;">Buscar</button>
                </div>
            </form>

            <div class="results-container">
                <?php if ($q !== ''): ?>
                    <h5 class="small text-muted" style="margin-bottom: 15px;">
                        Resultados para: "<?php echo htmlspecialchars($q); ?>"
                    </h5>
                    
                    <?php if (empty($results)): ?>
                        <div style="padding: 40px; text-align: center; color: #999; border: 2px dashed #eee; border-radius: 8px;">
                            No se han encontrado coincidencias.
                        </div>
                    <?php else: ?>
                        <div style="display: flex; flex-direction: column; gap: 10px;">
                            <?php foreach ($results as $r): ?>
                                <div class="item-card" style="display: flex; align-items: center; justify-content: space-between; padding: 12px 18px; background: #fff; border: 1px solid #eee; border-radius: 10px; transition: 0.2s;">
                                    
                                    <div style="display: flex; align-items: center; gap: 15px;">
                                        <?php if ($type === 'users'): ?>
                                            <div style="width: 45px; height: 45px; border-radius: 50%; background: #eee; overflow: hidden;">
                                                <img src="<?php echo $r['avatar_url'] ?: 'https://ui-avatars.com/api/?name='.urlencode($r['username']); ?>" style="width:100%; height:100%; object-fit:cover;">
                                            </div>
                                            <div>
                                                <div style="font-weight: 600; color: #333;"><?php echo htmlspecialchars($r['username']); ?></div>
                                                <div style="font-size: 0.8rem; color: #888;"><?php echo htmlspecialchars($r['bio'] ?: 'Sin descripción'); ?></div>
                                            </div>
                                        <?php else: ?>
                                            <div style="width: 45px; height: 45px; border-radius: 8px; background: #6366f1; color: white; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 0.9rem;">
                                                <?php echo htmlspecialchars($r['tag'] ?: 'EQ'); ?>
                                            </div>
                                            <div>
                                                <div style="font-weight: 600; color: #333;"><?php echo htmlspecialchars($r['name']); ?></div>
                                                <div style="font-size: 0.8rem; color: #888;">Equipo oficial</div>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="actions">
                                        <?php if ($type === 'users'): ?>
                                            <?php if ($isPrivileged && $activeTeamId): ?>
                                                <form method="POST">
                                                    <input type="hidden" name="action" value="invite_player">
                                                    <input type="hidden" name="target_id" value="<?php echo $r['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-primary">Invitar a <?php echo htmlspecialchars($activeTeam['name'] ?? 'Equipo'); ?></button>
                                                </form>
                                            <?php elseif (!$activeTeamId): ?>
                                                <span class="small text-muted">Selecciona un equipo primero</span>
                                            <?php else: ?>
                                                <span class="badge badge-light">Solo Admins</span>
                                            <?php endif; ?>

                                        <?php else: ?>
                                            <form method="POST">
                                                <input type="hidden" name="action" value="request_join">
                                                <input type="hidden" name="target_id" value="<?php echo $r['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-secondary">Solicitar Unirme</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<style>
    /* Efecto hover suave para los resultados */
    .item-card:hover { border-color: #6366f1 !important; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
    .btn-sm { padding: 6px 12px; border-radius: 5px; cursor: pointer; border: none; font-weight: 500; }
    .btn-secondary { background: #f3f4f6; color: #374151; }
    .btn-tercero { background: #000000; color: #f3f4f6; }
</style>