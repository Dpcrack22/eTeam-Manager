<?php
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/register_functions.php";
$name = "";
$email = "";
$date = "";
$password = "";
$rol = "";
$errores_campos = array();
$mensaje = "";


if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $date = $_POST["date"] ?? "";
    $password = $_POST["password"] ?? "";
    $rol = $_POST["role"] ?? "";
    
    $errors = array();
    $errores_campos = array();

    if (!$name) {
        $errors[] = "El nombre és obligatorio.";
        $campos_error["nombre"] = true;
    }
    if (!$email) {
        $errors[] = "El email es obligatorio.";
        $campos_error["email"] = true;
    }
    if (!$password) {
        $errors[] = "La constraseña es obligatoria.";
        $campos_error["password"] = true;
    }
    if ($password && strlen($password) < 8) {
        $errors[] = "La contraseña debe tener al menos 8 caracteres.";
        $campos_error["password"] = true;
    }
    if ($password && strlen($password) > 30) {
        $errors[] = "La contraseña no puede superar los 30 caracteres.";
        $campos_error["password"] = true;
    }
    /*
    if (!$date) {
        $errors[] = "La fecha es obligatoria.";
        $campos_error["date"] = true;
    }
    list($year, $month, $day) = explode("-", $date);
    if (!checkdate((int)$month, (int)$day, (int)$year)) {
        $errors[] = "La fecha introducida no es válida";
        $campos_error["date"] = true;
    } else {
        $birthdate = new DateTime($date);
        $today = new DateTime();
        $age = $today->diff($birthdate)->y;

        if ($birthdate > $today) {
            $errors[] = "La fecha no puede ser futura";
            $campos_error["date"] = true;
        } elseif ($age > 120) {
            $errors[] = "La fecha introducida no es válida";
            $campos_error["date"] = true;
        }
    }
    if (!$rol) {
        $errors[] = "Los roles son obligatorios.";
        $campos_error["rol"] = true;
    }
    $allowed = ['Owner', 'Manager', 'Coach', "Player", "Viewer"];
    if (!in_array($rol, $allowed)) {
        $errors[] = "Selección inválida";
        $campos_error["rol"] = true;
    }*/

    if (count($errors) > 0) {
        $mensaje = '<div class="error"><ul><li>' . implode('</li><li>', $errors) . '</li></ul></div>';
    } else {
        // Comprobar primero que el email no exista
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $mensaje = '<div class="error">El email ya está registrado.</div>';
        } else {
            $userId = createUser($conn, $name, $email, $password);
            addUserToOrganization($conn, $userId, )

            // Mensaje de creación de cuenta
            $mensaje = '<div class="success">Registro exitoso. Revisa tu correo para validar la cuenta.</div>';
            echo '<script>window.location.href="app.php?view=login";</script>';
            exit;
        }
    }
}

$pageTitle = $pageTitle ?? ($currentModule['title'] ?? 'Registro');
$pageEyebrow = $pageEyebrow ?? ($currentModule['eyebrow'] ?? 'Crear cuenta');
$pageDescription = $pageDescription ?? ($currentModule['description'] ?? 'Registra una cuenta de demo en eTeam Manager');

// ensure register page hides the sidebar when loaded directly
$hideSidebar = $hideSidebar ?? true;
$shouldCloseLayout = false;

if (empty($layoutIncluded)) {
    require __DIR__ . '/../includes/layout-start.php';
    $shouldCloseLayout = true;
}
?>
<!-- Mostrar los errores -->
<div class="register-page">
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
        <form class="form" method="post" novalidate autocomplete="off">
            <div class="field" <?php echo isset($campos_error['nombre']) ? 'form-group-error' : ''; ?>>
                <label for="name">Name</label>
                <input id="name" name="name" type="text" placeholder="Paco" value="<?php echo htmlspecialchars($name ?? ''); ?>"/>
            </div>

            <div class="field" <?php echo isset($campos_error['email']) ? 'form-group-error' : ''; ?>>
                <label for="email">Email</label>
                <input id="email" name="email" type="email" placeholder="player@team.gg" value="<?php echo htmlspecialchars($email ?? ''); ?>"/>
            </div>
            
            <!--
            <div class="field">
                <label for="date">Birthday</label>
                <input id="date" name="date" type="date" />
            </div>
            -->

            <div class="field" <?php echo isset($campos_error['email']) ? 'form-group-error' : ''; ?>>
                <label for="password">Password</label>
                <input id="password" name="password" type="password" placeholder="••••••••" value="<?php echo htmlspecialchars($password ?? ''); ?>"/>
            </div>
            
            <!--
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
            -->

            <div style="display:flex; gap:12px; flex-wrap:wrap;">
                <button class="btn btn-primary" type="submit">Crear cuenta</button>
                <a class="btn btn-secondary" href="app.php?view=login">Volver al login</a>
            </div>
        </form>

        <script>
            document.addEventListener("DOMContentLoaded", function() {
                const camposError = document.querySelectorAll(".form-group-error");
                camposError.forEach(function(grupo) {
                    const input = grupo.querySelector("input, select");
                    if (input) {
                        input.value = "";
                        // Remover la clase de error desprúes de que el usuario escriba
                        input.addEventListener("input", function() {
                            grupo.classList.remove("form-group-error");
                        });
                        input.addEventListener("change", function() {
                            grupo.classList.remove("form-group-error");
                        });
                    }
                });
            });
        </script>
    </div>
</div>
<?php if ($shouldCloseLayout) { require __DIR__ . '/../includes/layout-end.php'; }

