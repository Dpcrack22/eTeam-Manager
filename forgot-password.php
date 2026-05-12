<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/security_functions.php';

$message = '';
$errorMessage = '';
$email = trim((string) ($_POST['email'] ?? ''));
$shouldCloseLayout = false;
$hideSidebar = true;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = requestPasswordReset($conn, $email);
    if (!empty($result['success'])) {
        $message = (string) ($result['message'] ?? 'Revisa tu correo para cambiar la contraseña');
    } else {
        $errorMessage = (string) ($result['error'] ?? 'No se ha podido iniciar la recuperación');
    }
}

$pageTitle = 'Recuperar contraseña';
$pageEyebrow = 'Seguridad';
$pageDescription = 'Solicita un enlace para restablecer tu contraseña.';

if (empty($layoutIncluded)) {
    require __DIR__ . '/includes/layout-start.php';
    $shouldCloseLayout = true;
}
?>
<div class="auth-page">
    <div class="auth-card card">
        <div class="auth-card-head">
            <div class="small">Recuperación</div>
            <h2 class="h3">Restablecer contraseña</h2>
            <p>Escribe tu correo y te enviaremos un enlace temporal para crear una contraseña nueva.</p>
        </div>

        <?php if ($message !== ''): ?>
            <div class="success-box" style="border-color: rgba(46, 204, 113, 0.4); background: rgba(46, 204, 113, 0.1); margin-bottom: 16px; border-left: 4px solid #2ecc71; color: var(--text-main);" role="status"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
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
                <button class="btn btn-primary" type="submit">Enviar enlace</button>
                <a class="btn btn-secondary" href="login.php">Volver al acceso</a>
            </div>
        </form>
    </div>
</div>
<?php if ($shouldCloseLayout) { require __DIR__ . '/includes/layout-end.php'; } ?>