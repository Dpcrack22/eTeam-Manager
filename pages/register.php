<?php
$name = "";
$surname = "";
$email = "";
$date = "";
$password = "";
$rol = "";
$errors = [];


if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST["name"] ?? "");
    $surname = trim($_POST["surname"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $date = $_POST["date"] ?? "";
    $password = $_POST["password"] ?? "";
    $rol = $_POST["role"] ?? "";
    $errors = validateRegister($name, $surname, $email, $date, $password, $rol);
}

// Comprobaciones basicas para que el register sea correcto
function validateRegister($name, $surname, $email, $date, $password, $rol) {
    $errors = [];

    // Validamos el nombre
    if ($name === "") {
        $errors["name"] = "El nombre es obligatorio";
    } else {
        if (strpos($name, " ") !== false) {
            $errors["name"] = "El nombre no puede contener espacios";
        } else if (strlen($name) <= 2) {
            $errors["name"] = "El nombre no puede contener menos o igual que 2 caracteres";
        }
    }

    // Validamos el apellido
    if ($surname === "") {
        $errors["surname"] = "El apellido es obligatorio";
    } else {
        if (strlen($surname) <= 4) {
            $errors["surname"] = "El apellido no puede contener menos o igual que 4 caracteres";
        }
    }

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

    // Validamos la fecha
    if ($date === "") {
        $errors["date"] = "La fecha es obligatoria";
    } else {

        list($year, $month, $day) = explode("-", $date);

        if (!checkdate((int)$month, (int)$day, (int)$year)) {
            $errors["date"] = "La fecha introducida no es válida";
        } else {
            $birthdate = new DateTime($date);
            $today = new DateTime();
            $age = $today->diff($birthdate)->y;

            if ($birthdate > $today) {
                $errors["date"] = "La fecha no puede ser futura";
            } elseif ($age > 120) {
                $errors["date"] = "La fecha introducida no es válida";
            }
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

    // Validamos el rol
    if ($rol === "") {
        $errors["rol"] = "El rol és obligatorio";
    } else {
        $allowed = ['Owner', 'Manager', 'Coach', "Player", "Viewer"];

        if (!in_array($rol, $allowed)) {
            $errors['rol'] = "Selección inválida";
        }
    }

    return $errors;
}
$pageTitle = $pageTitle ?? ($currentModule['title'] ?? 'Registro');
$pageEyebrow = $pageEyebrow ?? ($currentModule['eyebrow'] ?? 'Crear cuenta');
$pageDescription = $pageDescription ?? ($currentModule['description'] ?? 'Registra una cuenta de demo en eTeam Manager');

// ensure register page hides the sidebar when loaded directly
$hideSidebar = $hideSidebar ?? true;
$shouldCloseLayout = false;

if (empty($layoutIncluded)) {
    require __DIR__ . '/../includes/app-layout-start.php';
    $shouldCloseLayout = true;
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

<div class="card" style="max-width: 640px; margin: 24px auto;">
    <form class="form" method="post" novalidate>
        <div class="field">
            <label for="name">Name</label>
            <input id="name" name="name" type="text" placeholder="Paco"/>
        </div>

        <div class="field">
            <label for="surname">Username</label>
            <input id="surname" name="surname" type="text" placeholder="Gonzalez Fernandez" />
        </div>

        <div class="field">
            <label for="email">Email</label>
            <input id="email" name="email" type="email" placeholder="player@team.gg" />
        </div>

        <div class="field">
            <label for="date">Birthday</label>
            <input id="date" name="date" type="date" />
        </div>

        <div class="field">
            <label for="password">Password</label>
            <input id="password" name="password" type="password" placeholder="••••••••" />
        </div>

        <div class="field">
            <label for="role">Rol</label>
            <select id="role" name="role">
                <option value="">Selecciona un rol</option>
                <option value="Owner">Owner</option>
                <option value="Manager">Manager</option>
                <option value="Coach">Coach</option>
                <option value="Player">Player</option>
                <option value="Viewer">Viewer</option>
            </select>
        </div>

        <div style="display:flex; gap:12px; flex-wrap:wrap;">
            <button class="btn btn-primary" type="submit">Crear cuenta</button>
            <a class="btn btn-secondary" href="app.php?view=login">Ya tengo cuenta</a>
        </div>
    </form>
</div>
<?php if (empty($errors) && $_SERVER['REQUEST_METHOD'] === 'POST'): ?>
    <script>
        // Redirige automáticamente al dashboard
        window.location.href = "app.php?view=login";
    </script>
<?php endif; ?>

<?php if ($shouldCloseLayout) { require __DIR__ . '/../includes/app-layout-end.php'; }

