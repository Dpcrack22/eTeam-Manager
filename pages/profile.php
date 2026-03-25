<?php
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/profile_functions.php";

if (isLogged()) {
    echo '<script>window.location.href="app.php?view=login";</script>';
    exit;
}

$userEmail = $_SESSION["user"]["email"];

$errors = [];
$success = "";

// Actualizar el perfil
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST["username"] ?? "");
    $avatarInput = trim($_FILES["avatar_file"] ?? "");

    if (!$username) {
        $errors[] = "El nombre es obligatorio";
    }

    if (!empty($_FILES["avatar_file"]["tmp_name"])) {
        $targetDir = __DIR__ . "/../uploads/avatars/";
        $filename = uniqid() . "-" . basename($_FILES["avatar_file"]["name"]);
        $targetPath = $targetDir . $filename;

        if (move_uploaded_file($_FILES["avatar_file"]["tmp_name"], $targetPath)) {
            $avatarUrl = "/uploads/avatars/" . $filename;
        }
    }

    if (empty($errors)) {
        updateUserProfile($conn, $userEmail, $username, $avatarInput);

        // actualizar sesióm
        $_SESSION["user"]["name"] = $username;
        $_SESSION["user"]["avatar_url"] = $avatarInput;
        $success = "Perfil actualizado correctamente";
    }
}

// Cargar el usuario
$user = getUserProfile($conn, $userEmail);
$pageTitle = $pageTitle ?? ($currentModule['title'] ?? 'Perfil Usuario');
$pageEyebrow = $pageEyebrow ?? ($currentModule['eyebrow'] ?? 'Acceso');
$pageDescription = $pageDescription ?? ($currentModule['description'] ?? 'Observa tu perfil');
$shouldCloseLayout = false;

// ensure login page hides the sidebar when loaded directly
$hideSidebar = $hideSidebar ?? true;
if (empty($layoutIncluded)) {
    require __DIR__ . '/../includes/layout-start.php';
    $shouldCloseLayout = true;
}
?>

<div class="profile-page">
    <!-- MENSAJES -->
    <?php if (!empty($errors)): ?>
        <div class="error-container">
            <?php foreach ($errors as $error): ?>
                <div class="error-box"><?php echo $error; ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="success-box"><?php echo $success; ?></div>
    <?php endif; ?>

    <div class="card" style="max-width: 640px; margin: 24px auto;">
        <form method="POST" id="profile-form">
            <!-- AVATAR -->
            <div style="text-align:center; margin-bottom:20px;">
                <img
                    id="avatar-preview"
                    src="<?php echo htmlspecialchars($_SESSION["user"]['avatar_url'] ?: 'https://via.placeholder.com/100'); ?>"
                    style="width:100px; height:100px; border-radius:50%; object-fit:cover;"
                />
            </div>
            <!-- USERNAME -->
            <div class="field">
                <label>Username</label>
                <span id="username-text"><?php echo htmlspecialchars($user['username']); ?></span>
                <input
                    type="text"
                    name="username"
                    id="username-input"
                    value="<?php echo htmlspecialchars($user['username']); ?>"
                    style="display:none;"
                >
            </div>

            <!-- EMAIL (Solo lectura) -->
            <div class="field">
                <label>Email</label>
                <input type="text" value="<?php echo htmlspecialchars($user["email"]); ?>" disabled>
            </div>

            <!-- ROL -->
            <div class="field">
                <label>Rol</label>
                <input type="text" value="<?php echo htmlspecialchars($user["role"] ?? "Sin rol"); ?>" disabled>
            </div>

            <!-- ORGANIZACIÓN -->
            <div class="field">
                <label>Organización</label>
                <input type="text" value="<?php echo htmlspecialchars($user["organization_name"] ?? "Sin organización"); ?>" disabled>
            </div>

            <!-- AVATAR URL -->
            <div class="field" id="avatar-field" style="display:none;">
                <label>Avatar URL</label>
                <input 
                    type="file" 
                    name="avatar_file"
                    id="avatar-input"
                    accept="image/*"
                >
            </div>

            <!-- BOTONES -->
            <div style="display: flex; gap: 12px; margin-top: 20px;">
                <button type="button" class="btn btn-secondary" id="edit-btn">Editar Perfil</button>
                <button type="submit" class="btn btn-primary" id="save-btn" style="display:none;">
                    Guardar
                </button>

                <button type="button" class="btn btn-secondary" id="cancel-btn" style="display:none;">
                    Cancelar
                </button>
            </div>
        </form>
    </div>
</div>
<script src="/js/profile.js"></script>
<?php if ($shouldCloseLayout) { require __DIR__ . '/../includes/layout-end.php'; }