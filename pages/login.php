<?php
session_start();
$pageTitle = $pageTitle ?? ($currentModule['title'] ?? 'Login');
$pageEyebrow = $pageEyebrow ?? ($currentModule['eyebrow'] ?? 'Acceso');
$pageDescription = $pageDescription ?? ($currentModule['description'] ?? 'Inicia sesión en eTeam Manager');

// ensure login page hides the sidebar when loaded directly
$hideSidebar = $hideSidebar ?? true;
$shouldCloseLayout = false;
if (empty($layoutIncluded)) {
    require __DIR__ . '/../includes/app-layout-start.php';
    $shouldCloseLayout = true;
}

// Comprobaciones basicas para que el login sea correcto
function validateLogin($email, $password) {
    $errors = [];

    // Validamos el email
    if ($email === "") {
        $errors["email"] = "El correo electronico es obligatorio";
    } else {
        if (strpos($email, "@") === false) {
            $errors["email"] = 'El correo electronico introducido no es valido (falta "@")';
        } else if (strpos($email, " ") !== false) {
            $errors["email"] = "El correo electronico no puede contener espacios";
        } else if (strlen($email) < 5) {
            $errors["email"] = "El correo electronico introducido es demasiado corto";
        }
    }

    // Validamos la contraseña
    if ($password === "") {
        $errors["password"] = "La contraseña és obligatoria";
    } else {
        if (strlen($password) < 5) {
            $errors["password"] = "La contraseña introducida es demasiado corta (minimo 5 caracteres)";
        } elseif (strlen($password) > 30) {
            $errors["password"] = "Como vas a poner mas de 30 caracteres maldito loco";
        }
    }

    return $errors;
}

$email = "";
$password = "";
$errors = [];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
    $errors = validateLogin($email, $password);

    if (empty($errors)) {
        header("Location: app.php?view=dashboard");
        exit;
    }
}
?>

<!-- Mostrar los errores -->
<?php if (!empty($errors)): ?>
    <div class="error-container">
        <?php foreach ($errors as $error): ?>
            <div class="error-box">
                <?php echo $error; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="card" style="max-width: 520px; margin: 24px auto;">
    <form class="form" method="post" novalidate>
        <div class="field">
            <label for="email">Email</label>
            <input id="email" name="email" type="email" placeholder="player@team.gg" />
        </div>

        <div class="field">
            <label for="password">Contraseña</label>
            <input id="password" name="password" type="password" placeholder="••••••••" />
        </div>

        <div style="display:flex; gap:12px; flex-wrap:wrap;">
            <button class="btn btn-primary" type="submit">Entrar</button>
            <a class="btn btn-secondary" href="app.php?view=register">Crear cuenta</a>
        </div>
    </form>
</div>

<?php if ($shouldCloseLayout) { require __DIR__ . '/../includes/app-layout-end.php'; }

