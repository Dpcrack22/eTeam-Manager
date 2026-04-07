<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/register_functions.php';

$name = '';
$email = '';
$password = '';
$passwordConfirm = '';
$errors = [];

if (isLogged()) {
    echo '<script>window.location.href="app.php?view=dashboard";</script>';
    exit;
}

function validateRegister(string $name, string $email, string $password, string $passwordConfirm): array
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

    if ($passwordConfirm === '') {
        $errors['password_confirm'] = 'Repite la contraseña';
    } elseif ($passwordConfirm !== $password) {
        $errors['password_confirm'] = 'Las contraseñas no coinciden';
    }

    return $errors;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    $passwordConfirm = (string) ($_POST['password_confirm'] ?? '');

    $errors = validateRegister($name, $email, $password, $passwordConfirm);

    if (empty($errors)) {
        $statement = $conn->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $statement->execute([$email]);

        if ($statement->fetch()) {
            $errors['email'] = 'Este correo ya está registrado';
        } else {
            $userId = createUser($conn, $name, $email, $password, $_FILES['avatar_file'] ?? null);

            echo '<script>window.location.href="app.php?view=login";</script>';
            exit;
        }
    }
}
$pageTitle = $pageTitle ?? ($currentModule['title'] ?? 'Registro');
$pageEyebrow = $pageEyebrow ?? ($currentModule['eyebrow'] ?? 'Crear cuenta');
$pageDescription = $pageDescription ?? ($currentModule['description'] ?? 'Crea una cuenta para acceder a eTeam Manager');

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

<div class="auth-card card auth-card--wide">
    <div class="auth-card-head">
        <div class="small">Crear cuenta</div>
        <h2 class="h3">Registro de usuario</h2>
        <p>Crea tu acceso y después el equipo te asignará organización y rol por invitación.</p>
    </div>

    <form class="form auth-form" method="post" enctype="multipart/form-data" novalidate>
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

        <div class="field <?php echo isset($errors['password_confirm']) ? 'form-group-error' : ''; ?>">
            <label for="password_confirm">Repetir contraseña</label>
            <input id="password_confirm" name="password_confirm" type="password" placeholder="••••••••" />
        </div>

        <div class="field">
            <label for="avatar_file">Avatar</label>
            <input id="avatar_file" name="avatar_file" type="file" accept="image/*" />
        </div>

        <div class="help">
            Las organizaciones, equipos y roles se asignan después mediante invitación o por un administrador.
        </div>

        <div class="auth-actions">
            <button class="btn btn-primary" type="submit">Crear cuenta</button>
            <a class="btn btn-secondary" href="app.php?view=login">Ya tengo cuenta</a>
        </div>
    </form>
</div>
<?php if ($shouldCloseLayout) { require __DIR__ . '/../includes/layout-end.php'; }

