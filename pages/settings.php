<?php
require_once __DIR__ . '/../includes/auth.php';
requireAuth();

global $conn;

$currentUser = $_SESSION['user'] ?? [];
$userId = (int) ($currentUser['id'] ?? 0);
$errors = [];
$successMessage = '';

$userStatement = $conn->prepare(
    'SELECT u.id, u.username, u.email, u.avatar_url, om.role, o.name AS organization_name
     FROM users u
     LEFT JOIN organization_members om ON om.user_id = u.id AND om.is_active = 1
     LEFT JOIN organizations o ON o.id = om.organization_id
     WHERE u.id = :user_id
     LIMIT 1'
);
$userStatement->bindValue(':user_id', $userId, PDO::PARAM_INT);
$userStatement->execute();
$profileUser = $userStatement->fetch() ?: [
    'username' => $currentUser['name'] ?? 'Usuario',
    'email' => $currentUser['email'] ?? '',
    'avatar_url' => $currentUser['avatar_url'] ?? null,
    'role' => $currentUser['role'] ?? 'Member',
    'organization_name' => $currentUser['organization'] ?? 'Sin organización',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $avatarUrl = $profileUser['avatar_url'] ?? null;

    if ($username === '') {
        $errors['username'] = 'El nombre de usuario es obligatorio';
    } elseif (strlen($username) < 3) {
        $errors['username'] = 'El nombre de usuario debe tener al menos 3 caracteres';
    }

    if (!empty($_FILES['avatar_file']['tmp_name'])) {
        $targetDir = __DIR__ . '/../uploads/avatars/';
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $filename = uniqid('avatar_', true) . '-' . basename($_FILES['avatar_file']['name']);
        $targetPath = $targetDir . $filename;

        if (move_uploaded_file($_FILES['avatar_file']['tmp_name'], $targetPath)) {
            $avatarUrl = '/uploads/avatars/' . $filename;
        } else {
            $errors['avatar'] = 'No se ha podido subir el avatar';
        }
    }

    if (empty($errors)) {
        if ($avatarUrl !== null) {
            $updateStatement = $conn->prepare(
                'UPDATE users SET username = :username, avatar_url = :avatar_url, updated_at = NOW() WHERE id = :user_id'
            );
            $updateStatement->bindValue(':avatar_url', $avatarUrl, PDO::PARAM_STR);
        } else {
            $updateStatement = $conn->prepare(
                'UPDATE users SET username = :username, updated_at = NOW() WHERE id = :user_id'
            );
        }

        $updateStatement->bindValue(':username', $username, PDO::PARAM_STR);
        $updateStatement->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $updateStatement->execute();

        $_SESSION['user']['name'] = $username;
        if ($avatarUrl !== null) {
            $_SESSION['user']['avatar_url'] = $avatarUrl;
        }

        $successMessage = 'Perfil actualizado correctamente';

        $userStatement->execute();
        $profileUser = $userStatement->fetch() ?: $profileUser;
        $profileUser['username'] = $username;
        if ($avatarUrl !== null) {
            $profileUser['avatar_url'] = $avatarUrl;
        }
    }
}

$pageTitle = $pageTitle ?? ($currentModule['title'] ?? 'Perfil y ajustes');
$pageEyebrow = $pageEyebrow ?? ($currentModule['eyebrow'] ?? 'Cuenta');
$pageDescription = $pageDescription ?? ($currentModule['description'] ?? 'Gestiona tu perfil y preferencias de acceso');
$shouldCloseLayout = false;

$hideSidebar = $hideSidebar ?? true;
if (empty($layoutIncluded)) {
    require __DIR__ . '/../includes/layout-start.php';
    $shouldCloseLayout = true;
}

$displayAvatar = $profileUser['avatar_url'] ?: null;
$avatarInitials = strtoupper(substr(preg_replace('/\s+/', '', (string) $profileUser['username']), 0, 2));
if ($avatarInitials === '') {
    $avatarInitials = 'EM';
}
?>

<section class="profile-page">
    <div class="card profile-hero">
        <div class="profile-hero-copy">
            <div class="small">Perfil y ajustes</div>
            <h2 class="profile-hero-title">Tu perfil dentro de la organización</h2>
            <p>Actualiza el nombre visible y el avatar del usuario que ha iniciado sesión sin salir del contexto operativo de la app.</p>
            <div class="stack-sm">
                <span class="badge badge-info"><?php echo htmlspecialchars($profileUser['role'] ?? 'Member', ENT_QUOTES, 'UTF-8'); ?></span>
                <span class="badge badge-success"><?php echo htmlspecialchars($profileUser['organization_name'] ?? 'Sin organización', ENT_QUOTES, 'UTF-8'); ?></span>
                <span class="badge">Acceso seguro</span>
            </div>
        </div>

        <div class="profile-hero-avatar" aria-hidden="true">
            <?php if ($displayAvatar): ?>
                <img class="profile-avatar-preview" src="<?php echo htmlspecialchars($displayAvatar, ENT_QUOTES, 'UTF-8'); ?>" alt="" />
            <?php else: ?>
                <?php echo htmlspecialchars($avatarInitials, ENT_QUOTES, 'UTF-8'); ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="profile-layout">
        <div class="card">
            <div class="dashboard-section-head">
                <div>
                    <div class="small">Resumen de cuenta</div>
                    <h3 class="h3">Contexto activo</h3>
                </div>
            </div>

            <div class="profile-summary-grid">
                <div class="profile-summary-item">
                    <div class="profile-summary-label">Usuario</div>
                    <div class="profile-summary-value"><?php echo htmlspecialchars($profileUser['username'] ?? 'Usuario', ENT_QUOTES, 'UTF-8'); ?></div>
                </div>
                <div class="profile-summary-item">
                    <div class="profile-summary-label">Email</div>
                    <div class="profile-summary-value"><?php echo htmlspecialchars($profileUser['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                </div>
                <div class="profile-summary-item">
                    <div class="profile-summary-label">Organización</div>
                    <div class="profile-summary-value"><?php echo htmlspecialchars($profileUser['organization_name'] ?? 'Sin organización', ENT_QUOTES, 'UTF-8'); ?></div>
                </div>
                <div class="profile-summary-item">
                    <div class="profile-summary-label">Rol</div>
                    <div class="profile-summary-value"><?php echo htmlspecialchars($profileUser['role'] ?? 'Member', ENT_QUOTES, 'UTF-8'); ?></div>
                </div>
            </div>

            <div class="landing-list" style="margin-top: 16px;">
                <div class="landing-list-item">El nombre se actualiza en la sesión y en la base de datos.</div>
                <div class="landing-list-item">El avatar se sube localmente y se reutiliza en toda la app.</div>
                <div class="landing-list-item">El perfil mantiene el contexto operativo del usuario.</div>
            </div>
        </div>

        <div class="card profile-form-card">
            <?php if ($successMessage !== ''): ?>
                <div class="error-box" style="border-color: rgba(46, 204, 113, 0.4); background: rgba(46, 204, 113, 0.1); margin-bottom: 16px;">
                    <?php echo htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="error-container">
                    <?php foreach ($errors as $error): ?>
                        <div class="error-box">
                            <?php echo htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="dashboard-section-head">
                <div>
                    <div class="small">Edición visual</div>
                    <h3 class="h3">Actualizar perfil</h3>
                </div>
            </div>

            <form class="form" method="post" enctype="multipart/form-data" novalidate>
                <div class="profile-avatar-row">
                    <?php if ($displayAvatar): ?>
                        <img class="profile-avatar-preview" src="<?php echo htmlspecialchars($displayAvatar, ENT_QUOTES, 'UTF-8'); ?>" alt="Avatar actual" />
                    <?php else: ?>
                        <div class="profile-hero-avatar"><?php echo htmlspecialchars($avatarInitials, ENT_QUOTES, 'UTF-8'); ?></div>
                    <?php endif; ?>
                    <div class="small">Usa una imagen cuadrada para mantener una presentación limpia en la app.</div>
                </div>

                <div class="profile-form-grid">
                    <div class="field <?php echo isset($errors['username']) ? 'form-group-error' : ''; ?>">
                        <label for="username">Nombre de usuario</label>
                        <input id="username" name="username" type="text" value="<?php echo htmlspecialchars($profileUser['username'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                    </div>

                    <div class="field">
                        <label>Email</label>
                        <input type="text" value="<?php echo htmlspecialchars($profileUser['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" disabled />
                    </div>

                    <div class="field">
                        <label>Organización</label>
                        <input type="text" value="<?php echo htmlspecialchars($profileUser['organization_name'] ?? 'Sin organización', ENT_QUOTES, 'UTF-8'); ?>" disabled />
                    </div>

                    <div class="field">
                        <label>Rol</label>
                        <input type="text" value="<?php echo htmlspecialchars($profileUser['role'] ?? 'Member', ENT_QUOTES, 'UTF-8'); ?>" disabled />
                    </div>

                    <div class="field <?php echo isset($errors['avatar']) ? 'form-group-error' : ''; ?>">
                        <label for="avatar_file">Cambiar avatar</label>
                        <input id="avatar_file" name="avatar_file" type="file" accept="image/*" />
                    </div>
                </div>

                <div class="profile-form-actions">
                    <button class="btn btn-primary" type="submit">Guardar cambios</button>
                    <a class="btn btn-secondary" href="app.php?view=dashboard">Volver al dashboard</a>
                </div>
            </form>
        </div>
    </div>
</section>

<?php if ($shouldCloseLayout) { require __DIR__ . '/../includes/layout-end.php'; } ?>