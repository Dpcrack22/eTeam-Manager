<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/organization_functions.php';

requireAuth();

global $conn;

$currentUser = $_SESSION['user'] ?? [];
$userId = (int) ($currentUser['id'] ?? 0);
$allowedRoles = ['owner', 'admin', 'manager', 'coach', 'analyst', 'player', 'viewer'];
$roleManagementRoles = ['owner', 'admin'];
$canManageOrganizationRoles = in_array((string) ($_SESSION['user']['role'] ?? ''), $roleManagementRoles, true);
$roleLabels = [
    'owner' => 'Owner',
    'admin' => 'Admin',
    'manager' => 'Manager',
    'coach' => 'Coach',
    'analyst' => 'Analyst',
    'player' => 'Player',
    'viewer' => 'Viewer',
];
$errors = [];
$successMessage = '';

function eteam_slugify(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    $ascii = $value;
    if (function_exists('iconv')) {
        $converted = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if ($converted !== false) {
            $ascii = $converted;
        }
    }

    $slug = strtolower((string) preg_replace('/[^a-z0-9]+/i', '-', $ascii));
    $slug = trim($slug, '-');

    return $slug !== '' ? $slug : 'organization';
}

function eteam_slug_exists(PDO $conn, string $slug, ?int $ignoreOrganizationId = null): bool
{
    $sql = 'SELECT id FROM organizations WHERE slug = :slug';
    if ($ignoreOrganizationId !== null) {
        $sql .= ' AND id <> :ignore_id';
    }
    $sql .= ' LIMIT 1';

    $statement = $conn->prepare($sql);
    $statement->bindValue(':slug', $slug, PDO::PARAM_STR);
    if ($ignoreOrganizationId !== null) {
        $statement->bindValue(':ignore_id', $ignoreOrganizationId, PDO::PARAM_INT);
    }
    $statement->execute();

    return (bool) $statement->fetch();
}

$userOrganizations = getUserOrganizations($conn, $userId);
$activeOrganizationId = getActiveOrganizationId($conn, $userId);

if ($activeOrganizationId === null && !empty($userOrganizations)) {
    $activeOrganizationId = (int) $userOrganizations[0]['id'];
    setActiveOrganizationContext($conn, $userId, $activeOrganizationId);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'activate_organization') {
        $organizationId = (int) ($_POST['organization_id'] ?? 0);
        $result = setActiveOrganizationContext($conn, $userId, $organizationId);

        if (!empty($result['success'])) {
            $activeOrganizationId = $organizationId;
            $successMessage = 'Contexto de organización actualizado';
        } else {
            $errors[] = $result['error'] ?? 'No se ha podido cambiar la organización activa';
        }
    }

    if ($action === 'create_organization') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $slug = trim((string) ($_POST['slug'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $logoUrl = trim((string) ($_POST['logo_url'] ?? ''));

        if ($name === '') {
            $errors[] = 'El nombre de la organización es obligatorio';
        }

        $slug = $slug !== '' ? eteam_slugify($slug) : eteam_slugify($name);
        if ($slug === '') {
            $errors[] = 'El slug de la organización es obligatorio';
        } elseif (eteam_slug_exists($conn, $slug)) {
            $errors[] = 'Ya existe una organización con ese slug';
        }

        if (empty($errors)) {
            $organizationId = createOrganization(
                $conn,
                $userId,
                $name,
                $slug,
                $description,
                $logoUrl !== '' ? $logoUrl : null
            );

            setActiveOrganizationContext($conn, $userId, $organizationId);
            $successMessage = 'Organización creada y marcada como activa';
            $activeOrganizationId = $organizationId;
        }
    }

    if ($action === 'update_organization') {
        $organizationId = (int) ($_POST['organization_id'] ?? 0);
        $organization = getOrganizationById($conn, $organizationId, $userId);

        if (!$organization) {
            $errors[] = 'No tienes acceso a esa organización';
        } else {
            $currentMember = null;
            foreach ($userOrganizations as $userOrganization) {
                if ((int) $userOrganization['id'] === $organizationId) {
                    $currentMember = $userOrganization;
                    break;
                }
            }

            $canEdit = $currentMember && in_array((string) $currentMember['member_role'], ['owner', 'admin', 'manager'], true);

            if (!$canEdit) {
                $errors[] = 'No tienes permisos para editar esta organización';
            } else {
                $name = trim((string) ($_POST['name'] ?? ''));
                $slug = trim((string) ($_POST['slug'] ?? ''));
                $description = trim((string) ($_POST['description'] ?? ''));
                $logoUrl = trim((string) ($_POST['logo_url'] ?? ''));

                if ($name === '') {
                    $errors[] = 'El nombre de la organización es obligatorio';
                }

                $slug = $slug !== '' ? eteam_slugify($slug) : eteam_slugify($name);
                if ($slug === '') {
                    $errors[] = 'El slug de la organización es obligatorio';
                } elseif (eteam_slug_exists($conn, $slug, $organizationId)) {
                    $errors[] = 'Ya existe otra organización con ese slug';
                }

                if (empty($errors)) {
                    updateOrganization(
                        $conn,
                        $organizationId,
                        $name,
                        $slug,
                        $description,
                        $logoUrl !== '' ? $logoUrl : null
                    );

                    if (!empty($_SESSION['user']) && $activeOrganizationId === $organizationId) {
                        $_SESSION['user']['organization'] = $name;
                    }

                    $successMessage = 'Organización actualizada correctamente';
                }
            }
        }
    }

    if ($action === 'add_member') {
        $organizationId = (int) ($_POST['organization_id'] ?? 0);
        $memberEmail = trim((string) ($_POST['email'] ?? ''));
        $memberRole = strtolower(trim((string) ($_POST['role'] ?? 'viewer')));

        $currentMember = null;
        foreach ($userOrganizations as $userOrganization) {
            if ((int) $userOrganization['id'] === $organizationId) {
                $currentMember = $userOrganization;
                break;
            }
        }

        if (!$currentMember) {
            $errors[] = 'No tienes acceso a esa organización';
        } elseif (!in_array((string) $currentMember['member_role'], $roleManagementRoles, true)) {
            $errors[] = 'No tienes permisos para añadir miembros';
        } else {
            if ($memberEmail === '') {
                $errors[] = 'El email es obligatorio';
            }

            if (!in_array($memberRole, $allowedRoles, true)) {
                $errors[] = 'Selecciona un rol válido';
            }

            if (empty($errors)) {
                $result = addOrUpdateOrganizationMemberByEmail($conn, $organizationId, $memberEmail, $memberRole);

                if (!empty($result['success'])) {
                    $successMessage = 'Miembro sincronizado correctamente';
                } else {
                    $errors[] = $result['error'] ?? 'No se ha podido añadir el miembro';
                }
            }
        }
    }

    if ($action === 'update_member_role') {
        $organizationId = (int) ($_POST['organization_id'] ?? 0);
        $memberUserId = (int) ($_POST['member_user_id'] ?? 0);
        $memberRole = strtolower(trim((string) ($_POST['role'] ?? '')));

        $currentMember = null;
        foreach ($userOrganizations as $userOrganization) {
            if ((int) $userOrganization['id'] === $organizationId) {
                $currentMember = $userOrganization;
                break;
            }
        }

        if (!$currentMember) {
            $errors[] = 'No tienes acceso a esa organización';
        } elseif (!in_array((string) $currentMember['member_role'], $roleManagementRoles, true)) {
            $errors[] = 'No tienes permisos para modificar roles';
        } elseif ($memberUserId === 0) {
            $errors[] = 'Miembro no válido';
        } elseif (!in_array($memberRole, $allowedRoles, true)) {
            $errors[] = 'Selecciona un rol válido';
        } else {
            updateOrganizationMemberRole($conn, $organizationId, $memberUserId, $memberRole);
            $successMessage = 'Rol del miembro actualizado';
        }
    }

    $userOrganizations = getUserOrganizations($conn, $userId);
}

$activeOrganization = null;
$activeMember = null;

foreach ($userOrganizations as $userOrganization) {
    if ((int) $userOrganization['id'] === (int) $activeOrganizationId) {
        $activeMember = $userOrganization;
        $activeOrganization = getOrganizationById($conn, (int) $userOrganization['id'], $userId);
        break;
    }
}

if (!$activeOrganization && !empty($userOrganizations)) {
    $firstOrganization = $userOrganizations[0];
    $activeOrganizationId = (int) $firstOrganization['id'];
    $activeMember = $firstOrganization;
    $activeOrganization = getOrganizationById($conn, $activeOrganizationId, $userId);
    setActiveOrganizationContext($conn, $userId, $activeOrganizationId);
}

$organizationMembers = [];
$organizationStats = ['members' => 0, 'teams' => 0];

if ($activeOrganization) {
    $organizationMembers = getOrganizationMembers($conn, (int) $activeOrganization['id']);
    $organizationStats = getOrganizationStats($conn, (int) $activeOrganization['id']);
}

$pageOrganizations = [];
foreach ($userOrganizations as $organization) {
    $organizationStatsRow = getOrganizationStats($conn, (int) $organization['id']);
    $pageOrganizations[] = [
        'id' => (int) $organization['id'],
        'name' => $organization['name'],
        'slug' => $organization['slug'],
        'description' => $organization['description'] ?: 'Sin descripción',
        'member_role' => $organization['member_role'],
        'is_active' => (int) $organization['id'] === (int) $activeOrganizationId,
        'members_count' => $organizationStatsRow['members'],
        'teams_count' => $organizationStatsRow['teams'],
    ];
}

$pageTitle = $pageTitle ?? 'Organizaciones';
$pageEyebrow = $pageEyebrow ?? 'Modulo';
$pageDescription = $pageDescription ?? 'Gestion visual de organizaciones, miembros y roles internos.';
?>

<div class="organization-page">
    <div class="organization-intro card">
        <div class="dashboard-section-head">
            <div>
                <div class="small">Sprint 3</div>
                <h2 class="h2">Organizaciones</h2>
                <p>Contexto activo, alta de organización y gestión de miembros en una vista más clara.</p>
            </div>
            <span class="badge badge-info"><?php echo count($pageOrganizations); ?> disponibles</span>
        </div>

        <?php if (!empty($successMessage)): ?>
            <div class="error-box organization-message organization-message-success">
                <?php echo htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="error-container">
                <?php foreach ($errors as $error): ?>
                    <div class="error-box organization-message">
                        <?php echo htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="organization-summary-strip">
            <div class="dashboard-hero-chip">
                <div class="small">Organización activa</div>
                <div class="dashboard-hero-value"><?php echo htmlspecialchars($activeOrganization['name'] ?? 'Sin organización', ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="small"><?php echo htmlspecialchars($activeOrganization['slug'] ?? 'sin-organizacion', ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
            <div class="dashboard-hero-chip">
                <div class="small">Miembros</div>
                <div class="dashboard-hero-value"><?php echo (int) $organizationStats['members']; ?></div>
            </div>
            <div class="dashboard-hero-chip">
                <div class="small">Equipos</div>
                <div class="dashboard-hero-value"><?php echo (int) $organizationStats['teams']; ?></div>
            </div>
        </div>
    </div>

    <div class="grid-2 organization-grid" style="margin-top: 24px;">
        <div class="card organization-panel">
            <div class="dashboard-section-head">
                <div>
                    <div class="small">Tus organizaciones</div>
                    <h3 class="h3">Selecciona contexto</h3>
                </div>
            </div>

            <?php if (!empty($pageOrganizations)): ?>
                <div class="landing-list organization-list">
                    <?php foreach ($pageOrganizations as $organization): ?>
                        <div class="dashboard-list-item organization-list-item">
                            <div class="dashboard-list-top organization-list-top">
                                <div>
                                    <div class="dashboard-list-title"><?php echo htmlspecialchars($organization['name'], ENT_QUOTES, 'UTF-8'); ?></div>
                                    <div class="dashboard-list-meta"><?php echo htmlspecialchars($organization['slug'], ENT_QUOTES, 'UTF-8'); ?> · Tu rol: <?php echo htmlspecialchars($roleLabels[$organization['member_role']] ?? ucfirst((string) $organization['member_role']), ENT_QUOTES, 'UTF-8'); ?></div>
                                </div>
                                <div class="stack-sm organization-badges">
                                    <?php if ($organization['is_active']): ?>
                                        <span class="badge badge-success">Activa</span>
                                    <?php endif; ?>
                                    <span class="badge badge-info"><?php echo (int) $organization['members_count']; ?> miembros</span>
                                    <span class="badge"><?php echo (int) $organization['teams_count']; ?> equipos</span>
                                </div>
                            </div>

                            <p class="small"><?php echo htmlspecialchars($organization['description'], ENT_QUOTES, 'UTF-8'); ?></p>

                            <form method="post" class="organization-action-form">
                                <input type="hidden" name="action" value="activate_organization" />
                                <input type="hidden" name="organization_id" value="<?php echo (int) $organization['id']; ?>" />
                                <button class="btn <?php echo $organization['is_active'] ? 'btn-secondary' : 'btn-primary'; ?>" type="submit">
                                    <?php echo $organization['is_active'] ? 'Contexto actual' : 'Usar esta organización'; ?>
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="dashboard-empty-state">
                    No tienes ninguna organización todavía. Crea la primera y quedará activa automáticamente.
                </div>
            <?php endif; ?>
        </div>

        <div class="card organization-panel">
            <div class="dashboard-section-head">
                <div>
                    <div class="small">Nueva organización</div>
                    <h3 class="h3">Crear organización</h3>
                </div>
            </div>

            <form class="form organization-form" method="post" novalidate>
                <input type="hidden" name="action" value="create_organization" />

                <div class="field">
                    <label for="new_org_name">Nombre</label>
                    <input id="new_org_name" name="name" type="text" placeholder="Parallax Esports" />
                </div>

                <div class="field">
                    <label for="new_org_slug">Slug</label>
                    <input id="new_org_slug" name="slug" type="text" placeholder="parallax-esports" />
                </div>

                <div class="field">
                    <label for="new_org_logo">Logo URL</label>
                    <input id="new_org_logo" name="logo_url" type="text" placeholder="https://..." />
                </div>

                <div class="field">
                    <label for="new_org_description">Descripción</label>
                    <textarea id="new_org_description" name="description" placeholder="Resumen de la organización..."></textarea>
                </div>

                <button class="btn btn-primary" type="submit">Crear organización</button>
            </form>
        </div>
    </div>

    <?php if ($activeOrganization): ?>
        <div class="grid-2 organization-grid" style="margin-top: 24px;">
            <div class="card organization-panel">
                <div class="dashboard-section-head">
                    <div>
                        <div class="small">Contexto activo</div>
                        <h3 class="h3"><?php echo htmlspecialchars($activeOrganization['name'], ENT_QUOTES, 'UTF-8'); ?></h3>
                    </div>
                    <span class="badge badge-info"><?php echo htmlspecialchars((string) ($activeMember['member_role'] ?? 'viewer'), ENT_QUOTES, 'UTF-8'); ?></span>
                </div>

                <div class="organization-summary-copy">
                    <p><?php echo htmlspecialchars($activeOrganization['description'] ?: 'Sin descripción', ENT_QUOTES, 'UTF-8'); ?></p>
                </div>

                <form class="form organization-form" method="post" novalidate>
                    <input type="hidden" name="action" value="update_organization" />
                    <input type="hidden" name="organization_id" value="<?php echo (int) $activeOrganization['id']; ?>" />

                    <div class="field">
                        <label for="active_org_name">Nombre</label>
                        <input id="active_org_name" name="name" type="text" value="<?php echo htmlspecialchars($activeOrganization['name'], ENT_QUOTES, 'UTF-8'); ?>" />
                    </div>

                    <div class="field">
                        <label for="active_org_slug">Slug</label>
                        <input id="active_org_slug" name="slug" type="text" value="<?php echo htmlspecialchars($activeOrganization['slug'], ENT_QUOTES, 'UTF-8'); ?>" />
                    </div>

                    <div class="field">
                        <label for="active_org_logo">Logo URL</label>
                        <input id="active_org_logo" name="logo_url" type="text" value="<?php echo htmlspecialchars($activeOrganization['logo_url'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                    </div>

                    <div class="field">
                        <label for="active_org_description">Descripción</label>
                        <textarea id="active_org_description" name="description"><?php echo htmlspecialchars($activeOrganization['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>

                    <button class="btn btn-secondary" type="submit">Guardar cambios</button>
                </form>

                <div class="organization-summary-strip organization-summary-strip--tight">
                    <div class="dashboard-hero-chip">
                        <div class="small">Miembros activos</div>
                        <div class="dashboard-hero-value"><?php echo (int) $organizationStats['members']; ?></div>
                    </div>
                    <div class="dashboard-hero-chip">
                        <div class="small">Equipos activos</div>
                        <div class="dashboard-hero-value"><?php echo (int) $organizationStats['teams']; ?></div>
                    </div>
                </div>
            </div>

            <div class="card organization-panel">
                <div class="dashboard-section-head">
                    <div>
                        <div class="small">Miembros</div>
                        <h3 class="h3">Roles y acceso</h3>
                    </div>
                </div>

                <?php if ($canManageOrganizationRoles): ?>
                    <form class="form organization-form" method="post" novalidate style="margin-bottom: 24px;">
                        <input type="hidden" name="action" value="add_member" />
                        <input type="hidden" name="organization_id" value="<?php echo (int) $activeOrganization['id']; ?>" />

                        <div class="field">
                            <label for="member_email">Email del miembro</label>
                            <input id="member_email" name="email" type="email" placeholder="member@team.gg" />
                        </div>

                        <div class="field">
                            <label for="member_role">Rol</label>
                            <select id="member_role" name="role">
                                <?php foreach ($roleLabels as $roleValue => $roleLabel): ?>
                                    <option value="<?php echo htmlspecialchars($roleValue, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <button class="btn btn-primary" type="submit">Añadir o actualizar miembro</button>
                    </form>
                <?php else: ?>
                    <div class="dashboard-empty-state organization-note">
                        Solo owner y admin pueden cambiar roles o añadir miembros en esta sección.
                    </div>
                <?php endif; ?>

                <?php if (!empty($organizationMembers)): ?>
                    <div class="table-wrap">
                        <table class="table organization-table">
                            <thead>
                                <tr>
                                    <th>Usuario</th>
                                    <th>Email</th>
                                    <th>Rol</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($organizationMembers as $member): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($member['username'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($member['email'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($roleLabels[$member['role']] ?? ucfirst((string) $member['role']), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td>
                                            <span class="badge <?php echo (int) $member['is_active'] === 1 ? 'badge-success' : 'badge-warning'; ?>">
                                                <?php echo (int) $member['is_active'] === 1 ? 'Activo' : 'Inactivo'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($canManageOrganizationRoles): ?>
                                                <form method="post" class="organization-member-actions">
                                                    <input type="hidden" name="action" value="update_member_role" />
                                                    <input type="hidden" name="organization_id" value="<?php echo (int) $activeOrganization['id']; ?>" />
                                                    <input type="hidden" name="member_user_id" value="<?php echo (int) $member['user_id']; ?>" />
                                                    <select name="role">
                                                        <?php foreach ($roleLabels as $roleValue => $roleLabel): ?>
                                                            <option value="<?php echo htmlspecialchars($roleValue, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $member['role'] === $roleValue ? 'selected' : ''; ?>><?php echo htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8'); ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <button class="btn btn-secondary" type="submit">Guardar rol</button>
                                                </form>
                                            <?php else: ?>
                                                <span class="small">Solo lectura</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="dashboard-empty-state">Todavía no hay miembros cargados para esta organización.</div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
