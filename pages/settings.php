<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/security_functions.php';
requireAuth();

global $conn;

// Ensure DB has the optional profile columns before selecting/updating
ensureUserSecurityStorage($conn);

$currentUser = $_SESSION['user'] ?? [];
$userId = (int) ($currentUser['id'] ?? 0);
$errors = [];
$successMessage = '';

$userStatement = $conn->prepare(
    'SELECT u.id, u.username, u.email, u.avatar_url, u.bio, u.profile_public, u.bio_public, om.role, o.name AS organization_name
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
    'bio' => $currentUser['bio'] ?? null,
    'role' => $currentUser['role'] ?? 'Member',
    'organization_name' => $currentUser['organization'] ?? 'Sin organización',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $bio = trim((string) ($_POST['bio'] ?? ''));
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
        $bioValue = $bio !== '' ? $bio : null;
        $profilePublic = !empty($_POST['profile_public']) ? 1 : 0;
        $bioPublic = !empty($_POST['bio_public']) ? 1 : 0;

        if ($avatarUrl !== null) {
            $updateStatement = $conn->prepare(
                'UPDATE users SET username = :username, bio = :bio, avatar_url = :avatar_url, profile_public = :profile_public, bio_public = :bio_public, updated_at = NOW() WHERE id = :user_id'
            );
            $updateStatement->bindValue(':avatar_url', $avatarUrl, PDO::PARAM_STR);
        } else {
            $updateStatement = $conn->prepare(
                'UPDATE users SET username = :username, bio = :bio, profile_public = :profile_public, bio_public = :bio_public, updated_at = NOW() WHERE id = :user_id'
            );
        }

        $updateStatement->bindValue(':username', $username, PDO::PARAM_STR);
        if ($bioValue === null) {
            $updateStatement->bindValue(':bio', null, PDO::PARAM_NULL);
        } else {
            $updateStatement->bindValue(':bio', $bioValue, PDO::PARAM_STR);
        }
        $updateStatement->bindValue(':profile_public', $profilePublic, PDO::PARAM_INT);
        $updateStatement->bindValue(':bio_public', $bioPublic, PDO::PARAM_INT);
        $updateStatement->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $updateStatement->execute();

        $_SESSION['user']['name'] = $username;
        $_SESSION['user']['bio'] = $bioValue;
        $_SESSION['user']['profile_public'] = $profilePublic;
        $_SESSION['user']['bio_public'] = $bioPublic;
        if ($avatarUrl !== null) {
            $_SESSION['user']['avatar_url'] = $avatarUrl;
        }

        $successMessage = 'Perfil actualizado correctamente';

        $userStatement->execute();
        $profileUser = $userStatement->fetch() ?: $profileUser;
        $profileUser['username'] = $username;
        $profileUser['bio'] = $bioValue;
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
                <div class="profile-summary-item">
                    <div class="profile-summary-label">Perfil público</div>
                    <div class="profile-summary-value"><a href="profile.php?user=<?php echo urlencode((string) $profileUser['username']); ?>">Ver perfil</a></div>
                </div>
            </div>
        </div>

        <div class="card profile-form-card">
            <?php if ($successMessage !== ''): ?>
                <div class="success-box" style="border-color: rgba(46, 204, 113, 0.4); background: rgba(46, 204, 113, 0.1); margin-bottom: 16px; border-left: 4px solid var(--success); color: var(--text-main);">
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
                        <label for="bio">Bio pública</label>
                        <textarea id="bio" name="bio" rows="4" placeholder="Cuéntale a la gente quién eres"><?php echo htmlspecialchars((string) ($profileUser['bio'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>

                    <div class="field">
                        <label for="profile_public">Perfil público</label>
                        <div class="small">Activa para permitir que cualquiera vea tu perfil público.</div>
                        <input id="profile_public" name="profile_public" type="checkbox" value="1" <?php echo (!empty($profileUser['profile_public']) ? 'checked' : ''); ?> />
                    </div>

                    <div class="field">
                        <label for="bio_public">Mostrar bio públicamente</label>
                        <div class="small">Si está desactivado, tu bio no aparecerá en el perfil público.</div>
                        <input id="bio_public" name="bio_public" type="checkbox" value="1" <?php echo (!empty($profileUser['bio_public']) ? 'checked' : ''); ?> />
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