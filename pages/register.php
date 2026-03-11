<?php
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

// Comprobaciones basicas para que el register sea correcto
function validateRegister($name, $surname, $email, $birthday, $password, $rol) {
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
        if (!checkdate($month, $day, $year)) {
            $errors["date"] = "La fecha introducida no es válida";
        } elseif ($birthdate > date("Y-m-d")) {
            $errors["date"] = "La fecha no puede ser futura";
        } elseif ($age > 120) {
            $errors["date"] = "La fecha introducida no es válida";
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
?>

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
            <label for="birthday">Birthday</label>
            <input id="birthday" name="birthday" type="date" placeholder="30/09/2006" />
        </div>

        <div class="field">
            <label for="password">Password</label>
            <input id="password" name="password" type="password" placeholder="••••••••" />
        </div>

        <div class="field">
            <label for="role">Rol</label>
            <select id="role" name="role">
                <option>Owner</option>
                <option>Manager</option>
                <option>Coach</option>
                <option>Player</option>
                <option>Viewer</option>
            </select>
        </div>

        <div style="display:flex; gap:12px; flex-wrap:wrap;">
            <button class="btn btn-primary" type="submit">Crear cuenta</button>
            <a class="btn btn-secondary" href="app.php?view=login">Ya tengo cuenta</a>
        </div>
    </form>
</div>

<?php if ($shouldCloseLayout) { require __DIR__ . '/../includes/app-layout-end.php'; }

