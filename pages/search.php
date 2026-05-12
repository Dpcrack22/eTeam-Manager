<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/notification_functions.php';

requireAuth();

$q = trim((string) ($_GET['q'] ?? ''));
$type = trim((string) ($_GET['type'] ?? 'users'));
$results = [];

// Obtener datos del usuario actual para las validaciones de Owner/Admin
$currentUserId = (int)$_SESSION['user']['id'];
$currentUserRole = strtolower($_SESSION['user']['role'] ?? '');
$isPrivileged = in_array($currentUserRole, ['owner', 'admin']);

if ($q !== '') {
    global $conn;
    $searchTerm = '%' . $q . '%';
    
    if ($type === 'teams') {
        $stmt = $conn->prepare('SELECT t.id, t.name, t.tag, t.description, g.name AS game_name, t.organization_id 
                                FROM teams t 
                                LEFT JOIN games g ON g.id = t.game_id 
                                WHERE t.name LIKE :q OR t.tag LIKE :q LIMIT 20');
    } else {
        $stmt = $conn->prepare('SELECT id, username, avatar_url, bio FROM users WHERE username LIKE :q LIMIT 20');
    }
    
    $stmt->bindValue(':q', $searchTerm, PDO::PARAM_STR);
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<section class="page">
    <div class="container">
        <div class="card">
            <h2 class="h3">Buscador rápido</h2>
            
            <form method="get" action="app.php" style="margin-bottom: 20px;">
                <input type="hidden" name="view" value="search">
                <div style="display:flex; gap:10px;">
                    <input name="q" type="search" placeholder="Buscar jugador o equipo..." value="<?php echo htmlspecialchars($q); ?>" style="flex:1;">
                    <select name="type">
                        <option value="users" <?php echo $type === 'users' ? 'selected' : ''; ?>>Jugadores</option>
                        <option value="teams" <?php echo $type === 'teams' ? 'selected' : ''; ?>>Equipos</option>
                    </select>
                    <button class="btn btn-primary" type="submit">Buscar</button>
                </div>
            </form>

            <div class="results-grid" style="display: flex; flex-direction: column; gap: 12px;">
                <?php if ($q !== '' && empty($results)): ?>
                    <p class="small">No se han encontrado <?php echo $type === 'teams' ? 'equipos' : 'jugadores'; ?>.</p>
                <?php endif; ?>

                <?php foreach ($results as $r): ?>
                    <div class="search-item" style="display: flex; align-items: center; justify-content: space-between; padding: 15px; border: 1px solid #eee; border-radius: 8px;">
                        
                        <div style="display: flex; align-items: center; gap: 15px;">
                            <?php if ($type === 'users'): ?>
                                <img src="<?php echo $r['avatar_url'] ?: 'assets/img/default-avatar.png'; ?>" style="width: 50px; height: 50px; border-radius: 50%; object-fit: cover;">
                                <div>
                                    <div style="font-weight: bold;"><?php echo htmlspecialchars($r['username']); ?></div>
                                    <div class="small text-muted"><?php echo htmlspecialchars($r['bio'] ?: 'Sin biografía'); ?></div>
                                </div>
                            <?php else: ?>
                                <div style="width: 50px; height: 50px; background: #f0f0f0; display: flex; align-items: center; justify-content: center; border-radius: 8px; font-weight: bold; color: #666;">
                                    <?php echo htmlspecialchars($r['tag'] ?: 'T'); ?>
                                </div>
                                <div>
                                    <div style="font-weight: bold;"><?php echo htmlspecialchars($r['name']); ?></div>
                                    <div class="small text-muted"><?php echo htmlspecialchars($r['game_name'] ?: 'Multigame'); ?></div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div>
                            <?php if ($type === 'users'): ?>
                                <?php if ($isPrivileged): ?>
                                    <button class="btn btn-sm btn-primary" onclick="alert('Invitación enviada a <?php echo $r['username']; ?>')">Invitar al equipo</button>
                                <?php endif; ?>
                            <?php else: ?>
                                <button class="btn btn-sm btn-secondary" onclick="alert('Solicitud enviada al equipo <?php echo $r['name']; ?>')">Solicitar unirse</button>
                            <?php endif; ?>
                        </div>

                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>