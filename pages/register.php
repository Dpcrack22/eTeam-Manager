<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/register_functions.php';

$name = '';
$email = '';
$password = '';
$rol = '';
$organizationId = '';
$errors = [];
$organizations = getAllOrganizations($conn);

if (isLogged()) {
    echo '<script>window.location.href="app.php?view=dashboard";</script>';
    exit;
}

function validateRegister(string $name, string $email, string $password, string $rol, string $organizationId): array
{
    $errors = [];

    if ($name === '') {
        $errors['name'] = 'El nombre es obligatorio';
    } elseif (strlen($name) < 3) {
        $errors['name'] = 'El nombre debe tener al menos 3 caracteres';
    }

    if ($email === '') {
        $errors['email'] = 'El correo electronico es obligatorio';
    } elseif (strpos($email, '@') === false) {
        $errors['email'] = 'El correo electronico introducido no es valido (falta "@")';
    } elseif (strpos($email, ' ') !== false) {
        $errors['email'] = 'El correo electronico no puede contener espacios';
    }

    if ($password === '') {
        $errors['password'] = 'La contraseña es obligatoria';
    } elseif (strlen($password) < 8) {
        $errors['password'] = 'La contraseña debe tener al menos 8 caracteres';
    }

    if ($organizationId === '') {
        $errors['organization'] = 'Selecciona una organización';
    }

    $allowedRoles = ['Owner', 'Manager', 'Coach', 'Player', 'Viewer'];
    if ($rol === '' || !in_array($rol, $allowedRoles, true)) {
        $errors['rol'] = 'Selecciona un rol válido';
    }

    return $errors;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    $rol = trim($_POST['role'] ?? '');
    $organizationId = (string) ($_POST['organization_id'] ?? '');

    $errors = validateRegister($name, $email, $password, $rol, $organizationId);

    if (empty($errors)) {
        $statement = $conn->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $statement->execute([$email]);

        if ($statement->fetch()) {
            $errors['email'] = 'Este correo ya está registrado';
        } elseif (!validateOrganization($conn, (int) $organizationId)) {
            $errors['organization'] = 'La organización seleccionada no existe';
        } else {
            $userId = createUser($conn, $name, $email, $password, $_FILES['avatar_file'] ?? null);
            addUserToOrganization($conn, $userId, (int) $organizationId, $rol);

            echo '<script>window.location.href="app.php?view=login";</script>';
            exit;
        }
    }
}
$pageTitle = $pageTitle ?? ($currentModule['title'] ?? 'Registro');
$pageEyebrow = $pageEyebrow ?? ($currentModule['eyebrow'] ?? 'Crear cuenta');
$pageDescription = $pageDescription ?? ($currentModule['description'] ?? 'Registra una cuenta de demo en eTeam Manager');

// ensure register page hides the sidebar when loaded directly
$hideSidebar = $hideSidebar ?? true;
$shouldCloseLayout = false;

if (empty($layoutIncluded)) {
    require __DIR__ . '/../includes/layout-start.php';
    $shouldCloseLayout = true;
}
?>
<?php if (!empty($errors)): ?>
    <div class="error-container">
        <?php foreach ($errors as $error): ?>
            <div class="error-box">
                <?php echo htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="card" style="max-width: 640px; margin: 24px auto;">
    <form class="form" method="post" enctype="multipart/form-data" novalidate>
        <div class="field <?php echo isset($errors['name']) ? 'form-group-error' : ''; ?>">
            <label for="name">Nombre</label>
            <input id="name" name="name" type="text" placeholder="Paco" value="<?php echo htmlspecialchars($name); ?>" />
        </div>

        <div class="field <?php echo isset($errors['email']) ? 'form-group-error' : ''; ?>">
            <label for="email">Email</label>
            <input id="email" name="email" type="email" placeholder="player@team.gg" value="<?php echo htmlspecialchars($email); ?>" />
        </div>

        <div class="field <?php echo isset($errors['password']) ? 'form-group-error' : ''; ?>">
            <label for="password">Contraseña</label>
            <input id="password" name="password" type="password" placeholder="••••••••" />
        </div>

        <div class="field <?php echo isset($errors['organization']) ? 'form-group-error' : ''; ?>">
            <label for="organization">Organización</label>
            <select id="organization" name="organization_id">
                <option value="">Selecciona una organización</option>
                <?php foreach ($organizations as $organization): ?>
                    <option value="<?php echo (int) $organization['id']; ?>" <?php echo (string) $organizationId === (string) $organization['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($organization['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="field <?php echo isset($errors['rol']) ? 'form-group-error' : ''; ?>">
            <label for="role">Rol</label>
            <select id="role" name="role">
                <option value="">Selecciona un rol</option>
                <option value="Owner" <?php echo $rol === 'Owner' ? 'selected' : ''; ?>>Owner</option>
                <option value="Manager" <?php echo $rol === 'Manager' ? 'selected' : ''; ?>>Manager</option>
                <option value="Coach" <?php echo $rol === 'Coach' ? 'selected' : ''; ?>>Coach</option>
                <option value="Player" <?php echo $rol === 'Player' ? 'selected' : ''; ?>>Player</option>
                <option value="Viewer" <?php echo $rol === 'Viewer' ? 'selected' : ''; ?>>Viewer</option>
            </select>
        </div>

        <div class="field">
            <label for="avatar_file">Avatar</label>
            <input id="avatar_file" name="avatar_file" type="file" accept="image/*" />
        </div>

        <div style="display:flex; gap:12px; flex-wrap:wrap;">
            <button class="btn btn-primary" type="submit">Crear cuenta</button>
            <a class="btn btn-secondary" href="app.php?view=login">Ya tengo cuenta</a>
        </div>
    </form>
</div>
<?php if ($shouldCloseLayout) { require __DIR__ . '/../includes/layout-end.php'; }

