<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

$q = trim((string) ($_GET['q'] ?? ''));
$type = trim((string) ($_GET['type'] ?? 'users'));

$results = [];
if ($q !== '') {
    global $conn;
    if ($type === 'teams') {
        $stmt = $conn->prepare('SELECT t.id, t.name, t.tag, t.description, g.name AS game_name, t.organization_id FROM teams t LEFT JOIN games g ON g.id = t.game_id WHERE t.name LIKE :q OR t.tag LIKE :q LIMIT 50');
        $stmt->bindValue(':q', '%' . $q . '%', PDO::PARAM_STR);
        $stmt->execute();
        $results = $stmt->fetchAll();
    } else {
        $stmt = $conn->prepare('SELECT id, username, avatar_url, bio FROM users WHERE username LIKE :q OR email LIKE :q LIMIT 50');
        $stmt->bindValue(':q', '%' . $q . '%', PDO::PARAM_STR);
        $stmt->execute();
        $results = $stmt->fetchAll();
    }
}

if (empty($layoutIncluded)) { require __DIR__ . '/../includes/layout-start.php'; }
?>
<section class="page">
    <div class="container">
        <div class="card">
            <div class="small">Buscar</div>
            <h2 class="h3">Buscar usuarios y equipos</h2>

            <form method="get" action="/pages/search.php" style="margin-top:12px;">
                <div style="display:flex; gap:8px; align-items:center;">
                    <input id="page-search-input" name="q" type="search" placeholder="Escribe al menos 2 caracteres" value="<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off" />
                    <select id="page-search-type" name="type" aria-label="Tipo">
                        <option value="users" <?php echo $type === 'users' ? 'selected' : ''; ?>>Usuarios</option>
                        <option value="teams" <?php echo $type === 'teams' ? 'selected' : ''; ?>>Equipos</option>
                    </select>
                    <button class="btn btn-primary" type="submit">Buscar</button>
                </div>
                <div id="page-search-suggestions" class="sidebar-search-suggestions" aria-hidden="true" style="margin-top:8px;"></div>
            </form>

            <?php if ($q === ''): ?>
                <p class="small" style="margin-top:12px;">Introduce un término y pulsa Buscar o selecciona una sugerencia.</p>
            <?php else: ?>
                <?php if (empty($results)): ?>
                    <div class="dashboard-empty-state">No se han encontrado resultados.</div>
                <?php else: ?>
                    <div class="landing-list">
                        <?php foreach ($results as $r): ?>
                            <?php if ($type === 'teams'): ?>
                                <div class="landing-list-item">
                                    <div style="display:flex; justify-content:space-between; align-items:center;">
                                        <div>
                                            <strong><?php echo htmlspecialchars($r['name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                            <div class="small"><?php echo htmlspecialchars($r['game_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?> <?php echo !empty($r['tag']) ? '· ' . htmlspecialchars($r['tag'], ENT_QUOTES, 'UTF-8') : ''; ?></div>
                                        </div>
                                        <a class="btn btn-secondary" href="/pages/team_profile.php?team_id=<?php echo (int)$r['id']; ?>">Ver equipo</a>
                                    </div>
                                    <div class="small"><?php echo htmlspecialchars($r['description'] ?: 'Sin descripción', ENT_QUOTES, 'UTF-8'); ?></div>
                                </div>
                            <?php else: ?>
                                <div class="landing-list-item">
                                    <div style="display:flex; justify-content:space-between; align-items:center;">
                                        <div>
                                            <strong><?php echo htmlspecialchars($r['username'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                            <div class="small"><?php echo htmlspecialchars($r['bio'] ?: '', ENT_QUOTES, 'UTF-8'); ?></div>
                                        </div>
                                        <a class="btn btn-secondary" href="profile.php?user=<?php echo urlencode((string)$r['username']); ?>">Ver perfil</a>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php if (empty($layoutIncluded)) { require __DIR__ . '/../includes/layout-end.php'; } ?>
