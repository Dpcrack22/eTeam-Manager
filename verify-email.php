<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/security_functions.php';

$message = '';
$errorMessage = '';
$email = trim((string) ($_GET['email'] ?? $_POST['email'] ?? ''));
$token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
$shouldCloseLayout = false;
$hideSidebar = true;

if (!empty($token)) {
    $user = findUserByEmailToken($conn, $token);
    if ($user && empty($user['email_verified_at'])) {
        markUserEmailVerified($conn, (int) $user['id']);
        $message = 'Tu correo se ha verificado correctamente. Ya puedes iniciar sesión.';
    } elseif ($user) {
        $message = 'Ese correo ya estaba verificado.';
    } else {
        $errorMessage = 'El enlace de verificación no es válido o ya ha caducado.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $email !== '') {
    $result = requestVerificationEmail($conn, $email);
    if (!empty($result['success'])) {
        $message = (string) ($result['message'] ?? 'Hemos enviado un correo de verificación');
    } else {
        $errorMessage = (string) ($result['error'] ?? 'No se ha podido enviar la verificación');
    }
}

$pageTitle = 'Verificar correo';
$pageEyebrow = 'Seguridad';
$pageDescription = 'Verifica tu correo para terminar de activar la cuenta.';

if (empty($layoutIncluded)) {
    require __DIR__ . '/includes/layout-start.php';
    $shouldCloseLayout = true;
}
?>
<div class="auth-page">
    <div class="auth-card card">
        <div class="auth-card-head">
            <div class="small">Seguridad</div>
            <h2 class="h3">Verifica tu correo</h2>
            <p>Si acabas de registrarte, revisa tu bandeja y activa tu cuenta desde el enlace enviado.</p>
        </div>

        <?php if ($message !== ''): ?>
            <div class="error-box app-feedback app-feedback-success" role="status"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <?php if ($errorMessage !== ''): ?>
            <div class="error-box" role="alert"><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <form class="form auth-form" method="post">
            <div class="field">
                <label for="email">Correo</label>
                <input id="email" name="email" type="email" value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>" placeholder="tu@email.com" />
            </div>

            <div class="auth-actions">
                <button class="btn btn-primary" type="submit">Reenviar verificación</button>
                <a class="btn btn-secondary" href="login.php">Volver al acceso</a>
            </div>
        </form>
    </div>
</div>
<?php if ($shouldCloseLayout) { require __DIR__ . '/includes/layout-end.php'; } ?>