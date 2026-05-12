<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/security_functions.php';

$email = '';
$password = '';
$rememberMe = false;
$errors = [];
$verificationSuccess = (string) ($_SESSION['verification_success'] ?? '');
unset($_SESSION['verification_success']);
$returnTo = safeReturnToTarget($_REQUEST['return_to'] ?? ($_SESSION['return_to'] ?? null));
unset($_SESSION['return_to']);

if (isLogged()) {
    header('Location: ' . $returnTo);
    exit;
}

function validateLogin(string $email, string $password): array
{
    $errors = [];

    if ($email === '') {
        $errors['email'] = 'El correo electrónico es obligatorio';
    } elseif (strpos($email, '@') === false) {
        $errors['email'] = 'El correo electrónico introducido no es válido (falta "@")';
    } elseif (strpos($email, ' ') !== false) {
        $errors['email'] = 'El correo electrónico no puede contener espacios';
    } elseif (strlen($email) < 5) {
        $errors['email'] = 'El correo electrónico introducido es demasiado corto';
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
            header('Location: ' . $returnTo);
            exit;
        }

        $errors = $result['errors'] ?? ['login' => $result['error'] ?? 'Usuario o contraseña incorrectos'];

        if (($result['error'] ?? '') === 'Correo pendiente de verificación') {
            $errors['verification'] = 'Debes verificar tu correo antes de entrar. Usa la página de verificación o revisa el email.';
        }
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
<div class="auth-page">
    <?php if ($verificationSuccess !== ''): ?>
        <div class="success-box" style="margin-bottom: 24px; width: min(100%, 640px);">
            <?php echo htmlspecialchars($verificationSuccess, ENT_QUOTES, 'UTF-8'); ?>
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

    <div class="auth-card card">
        <div class="auth-card-head">
            <div class="small">Acceso seguro</div>
            <h2 class="h3">Entrar en eTeam Manager</h2>
            <p>Usa tu email y contraseña para entrar al espacio interno del equipo.</p>
        </div>

        <form class="form auth-form" method="post" novalidate>
            <input type="hidden" name="return_to" value="<?php echo htmlspecialchars($returnTo, ENT_QUOTES, 'UTF-8'); ?>" />
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

            <div class="auth-actions">
                <button class="btn btn-primary" type="submit">Entrar</button>
                <a class="btn btn-secondary" href="register.php">Crear cuenta</a>
            </div>

            <div class="help">
                <a href="forgot-password.php">He olvidado mi contraseña</a> · <a href="verify-email.php">Reenviar verificación</a>
            </div>
        </form>
    </div>
</div>

<?php if ($shouldCloseLayout) { require __DIR__ . '/../includes/layout-end.php'; }