<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/security_functions.php';

$errors = [];
$message = '';
$email = trim((string) ($_SESSION['login_code_email'] ?? ''));
$code = '';
$rememberMe = false;
$returnTo = safeReturnToTarget($_REQUEST['return_to'] ?? ($_SESSION['return_to'] ?? null));
unset($_SESSION['return_to']);

if (isLogged()) {
    header('Location: ' . $returnTo);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? 'request');
    $postedEmail = trim((string) ($_POST['email'] ?? $email));
    $code = trim((string) ($_POST['code'] ?? ''));
    $rememberMe = !empty($_POST['remember_me']);

    if ($action === 'request') {
        if ($postedEmail === '') {
            $errors['email'] = 'El correo electronico es obligatorio';
        } elseif (strpos($postedEmail, '@') === false) {
            $errors['email'] = 'El correo electronico introducido no es valido (falta "@")';
        }

        if (empty($errors)) {
            $result = requestLoginCode($conn, $postedEmail);
            $email = $postedEmail;
            $_SESSION['login_code_email'] = $postedEmail;
            $message = $result['message'] ?? 'Si el correo existe y está verificado, recibirás un código temporal';
        }
    }

    if ($action === 'verify') {
        $email = $postedEmail;
        $_SESSION['login_code_email'] = $postedEmail;

        if ($postedEmail === '') {
            $errors['email'] = 'Primero solicita un código temporal';
        } elseif ($code === '') {
            $errors['code'] = 'Introduce el código temporal';
        } else {
            $user = fetchUserForLoginCode($conn, $postedEmail);

            if (!$user) {
                $errors['general'] = 'No existe ninguna cuenta con ese correo';
            } else {
                $verification = verifyLoginCodeForUser($user, $code);

                if (!empty($verification['success'])) {
                    $updateStatement = $conn->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id LIMIT 1');
                    $updateStatement->bindValue(':id', (int) $user['id'], PDO::PARAM_INT);
                    $updateStatement->execute();

                    clearLoginCodeToken($conn, (int) $user['id']);
                    session_regenerate_id(true);
                    populateAuthenticatedSession($user);

                    if ($rememberMe) {
                        issueRememberMeToken($conn, (int) $user['id']);
                    } else {
                        deleteRememberMeToken($conn);
                        clearRememberMeCookie();
                    }

                    unset($_SESSION['login_code_email']);

                    header('Location: ' . $returnTo);
                    exit;
                }

                $errors = $verification['errors'] ?? ['code' => 'El código temporal no es válido'];
            }
        }
    }
}

$pageTitle = $pageTitle ?? 'Acceso temporal';
$pageEyebrow = $pageEyebrow ?? 'Acceso seguro';
$pageDescription = $pageDescription ?? 'Inicia sesión con un código temporal enviado por correo';
$hideSidebar = $hideSidebar ?? true;
$shouldCloseLayout = false;

if (empty($layoutIncluded)) {
    require __DIR__ . '/includes/layout-start.php';
    $shouldCloseLayout = true;
}
?>
<div class="auth-page">
    <?php if ($message !== ''): ?>
        <div class="error-container">
            <div class="error-box"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
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

    <div class="auth-card card auth-card--wide">
        <div class="auth-card-head">
            <div class="small">Acceso por código</div>
            <h2 class="h3">Entrar con un código temporal</h2>
            <p>Recibe un código en tu correo y úsalo una sola vez para entrar sin contraseña.</p>
        </div>

        <form class="form auth-form" method="post" novalidate>
            <input type="hidden" name="action" value="request" />
            <input type="hidden" name="return_to" value="<?php echo htmlspecialchars($returnTo, ENT_QUOTES, 'UTF-8'); ?>" />
            <div class="field <?php echo isset($errors['email']) ? 'form-group-error' : ''; ?>">
                <label for="request_email">Email</label>
                <input id="request_email" name="email" type="email" placeholder="player@team.gg" value="<?php echo htmlspecialchars($email); ?>" />
            </div>

            <div class="auth-actions">
                <button class="btn btn-primary" type="submit">Enviar código</button>
                <a class="btn btn-secondary" href="login.php">Volver al acceso normal</a>
            </div>
        </form>

        <form class="form auth-form" method="post" novalidate>
            <input type="hidden" name="action" value="verify" />
            <input type="hidden" name="return_to" value="<?php echo htmlspecialchars($returnTo, ENT_QUOTES, 'UTF-8'); ?>" />
            <div class="field <?php echo isset($errors['email']) ? 'form-group-error' : ''; ?>">
                <label for="verify_email">Email</label>
                <input id="verify_email" name="email" type="email" placeholder="player@team.gg" value="<?php echo htmlspecialchars($email); ?>" />
            </div>

            <div class="field <?php echo isset($errors['code']) ? 'form-group-error' : ''; ?>">
                <label for="code">Código temporal</label>
                <input id="code" name="code" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" placeholder="000000" value="<?php echo htmlspecialchars($code); ?>" />
            </div>

            <label class="login-remember-row">
                <input type="checkbox" name="remember_me" value="1" <?php echo $rememberMe ? 'checked' : ''; ?> />
                <span>
                    <strong>Recordarme</strong>
                    <small>Mantiene el acceso activo en este navegador durante más tiempo.</small>
                </span>
            </label>

            <div class="auth-actions">
                <button class="btn btn-primary" type="submit">Entrar con código</button>
                <a class="btn btn-secondary" href="register.php">Crear cuenta</a>
            </div>

            <div class="help">
                ¿No te ha llegado? Revisa spam o vuelve a enviar el código desde el primer bloque.
            </div>
        </form>
    </div>
</div>

<?php if ($shouldCloseLayout) { require __DIR__ . '/includes/layout-end.php'; } ?>