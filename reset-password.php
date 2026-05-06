<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/security_functions.php';

$token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
$password = '';
$passwordConfirm = '';
$errorMessage = '';
$message = '';
$user = $token !== '' ? findUserByPasswordResetToken($conn, $token) : false;
$shouldCloseLayout = false;
$hideSidebar = true;

if ($token !== '' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = (string) ($_POST['password'] ?? '');
    $passwordConfirm = (string) ($_POST['password_confirm'] ?? '');

    if (!$user) {
        $errorMessage = 'El enlace no es válido o ya ha caducado.';
    } elseif (!empty($user['password_reset_expires_at']) && strtotime((string) $user['password_reset_expires_at']) < time()) {
        $errorMessage = 'El enlace ya ha caducado. Pide otro desde recuperación de contraseña.';
    } elseif ($password === '') {
        $errorMessage = 'La contraseña es obligatoria.';
    } elseif (strlen($password) < 8) {
        $errorMessage = 'La contraseña debe tener al menos 8 caracteres.';
    } elseif ($password !== $passwordConfirm) {
        $errorMessage = 'Las contraseñas no coinciden.';
    } else {
        resetUserPassword($conn, (int) $user['id'], hash('sha256', $password));
        $message = 'La contraseña se ha actualizado correctamente. Ya puedes iniciar sesión.';
        $token = '';
        $user = false;
    }
}

$pageTitle = 'Nueva contraseña';
$pageEyebrow = 'Seguridad';
$pageDescription = 'Crea una contraseña nueva con el enlace recibido por correo.';

if (empty($layoutIncluded)) {
    require __DIR__ . '/includes/layout-start.php';
    $shouldCloseLayout = true;
}
?>
<div class="auth-page">
    <div class="auth-card card">
        <div class="auth-card-head">
            <div class="small">Recuperación</div>
            <h2 class="h3">Crear nueva contraseña</h2>
            <p>Introduce una contraseña nueva para volver a entrar en tu cuenta.</p>
        </div>

        <?php if ($message !== ''): ?>
            <div class="error-box app-feedback app-feedback-success" role="status"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="auth-actions">
                <a class="btn btn-primary" href="login.php">Ir al acceso</a>
            </div>
        <?php elseif ($errorMessage !== ''): ?>
            <div class="error-box" role="alert"><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="auth-actions">
                <a class="btn btn-primary" href="forgot-password.php">Pedir otro enlace</a>
                <a class="btn btn-secondary" href="login.php">Volver al acceso</a>
            </div>
        <?php else: ?>
            <form class="form auth-form" method="post">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>" />
                <div class="field">
                    <label for="password">Nueva contraseña</label>
                    <input id="password" name="password" type="password" placeholder="••••••••" />
                </div>
                <div class="field">
                    <label for="password_confirm">Repetir contraseña</label>
                    <input id="password_confirm" name="password_confirm" type="password" placeholder="••••••••" />
                </div>
                <div class="auth-actions">
                    <button class="btn btn-primary" type="submit">Cambiar contraseña</button>
                    <a class="btn btn-secondary" href="login.php">Volver al acceso</a>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>
<?php if ($shouldCloseLayout) { require __DIR__ . '/includes/layout-end.php'; } ?>