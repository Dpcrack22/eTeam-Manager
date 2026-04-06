<?php
require_once __DIR__ . '/../includes/auth.php';

$email = '';
$password = '';
$rememberMe = false;
$errors = [];

if (isLogged()) {
    echo '<script>window.location.href="app.php?view=dashboard";</script>';
    exit;
}

function validateLogin(string $email, string $password): array
{
    $errors = [];

    if ($email === '') {
        $errors['email'] = 'El correo electronico es obligatorio';
    } elseif (strpos($email, '@') === false) {
        $errors['email'] = 'El correo electronico introducido no es valido (falta "@")';
    } elseif (strpos($email, ' ') !== false) {
        $errors['email'] = 'El correo electronico no puede contener espacios';
    } elseif (strlen($email) < 5) {
        $errors['email'] = 'El correo electronico introducido es demasiado corto';
    }

    if ($password === '') {
        $errors['password'] = 'La contraseña es obligatoria';
    } elseif (strlen($password) < 5) {
        $errors['password'] = 'La contraseña introducida es demasiado corta (minimo 5 caracteres)';
    }

    return $errors;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    $rememberMe = !empty($_POST['remember_me']);
    $errors = validateLogin($email, $password);

    if (empty($errors)) {
        $result = login($email, $password, $rememberMe);

        if (!empty($result['success'])) {
            echo '<script>window.location.href="app.php?view=dashboard";</script>';
            exit;
        }

        $errors = $result['errors'] ?? ['login' => $result['error'] ?? 'Usuario o contraseña incorrectos'];
    }
}

$pageTitle = $pageTitle ?? ($currentModule['title'] ?? 'Login');
$pageEyebrow = $pageEyebrow ?? ($currentModule['eyebrow'] ?? 'Acceso');
$pageDescription = $pageDescription ?? ($currentModule['description'] ?? 'Inicia sesión en eTeam Manager');
$shouldCloseLayout = false;

// ensure login page hides the sidebar when loaded directly
$hideSidebar = $hideSidebar ?? true;
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

<div class="card" style="max-width: 520px; margin: 24px auto;">
    <form class="form" method="post" novalidate>
        <div class="field <?php echo isset($errors['email']) ? 'form-group-error' : ''; ?>">
            <label for="email">Email</label>
            <input id="email" name="email" type="email" placeholder="player@team.gg" value="<?php echo htmlspecialchars($email); ?>" />
        </div>

        <div class="field <?php echo isset($errors['password']) ? 'form-group-error' : ''; ?>">
            <label for="password">Contraseña</label>
            <input id="password" name="password" type="password" placeholder="••••••••" />
        </div>

        <label class="login-remember-row">
            <input type="checkbox" name="remember_me" value="1" <?php echo $rememberMe ? 'checked' : ''; ?> />
            <span>
                <strong>Recordarme</strong>
                <small>Mantiene la sesión activa en este navegador durante más tiempo.</small>
            </span>
        </label>

        <div style="display:flex; gap:12px; flex-wrap:wrap;">
            <button class="btn btn-primary" type="submit">Entrar</button>
            <a class="btn btn-secondary" href="app.php?view=register">Crear cuenta</a>
        </div>
    </form>
</div>

<?php if ($shouldCloseLayout) { require __DIR__ . '/../includes/layout-end.php'; }