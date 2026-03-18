<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/auth.php";

function isLoggedIn()
{
    return !empty($_SESSION['user']);
}

$email = "";
$password = "";
$errors = [];
$users = file_exists($usersFile) ? json_decode(file_get_contents($usersFile), true) : [];

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

if (isLoggedIn()) {
    // Redirigir con JS
    echo '<script>window.location.href="app.php?view=dashboard";</script>';
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $validUser = false;
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
    $errors = validateLogin($email, $password);

    if (empty($errors)) {
        $result = login($email, $password);
        if ($result["success"]) {
            $validUser = true;
        } else {
            if (isset($result["errors"])) {
                $errors = $result["errors"];
            }
        }
    }

    if ($validUser) {
        echo '<script>window.location.href="app.php?view=dashboard";</script>';
        exit;
    } elseif (empty($errors)) {
        $errors['login'] = "Usuario o contraseña incorrectos";
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

<?php if ($shouldCloseLayout) { require __DIR__ . '/../includes/layout-end.php'; }