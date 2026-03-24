<?php
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/profile_functions.php";

if (!estaLogeado()) {
    echo '<script>window.location.href="app.php?view=login";</script>';
    exit;
}

$userEmail = $_SESSION["user"]["email"];

$errors = [];
$success = "";

// Actualizar el perfil
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST["username"] ?? "");
    $avatar = trim($_POST["avatar"] ?? "");

    if (!$username) {
        $errors[] = "El nombre es obligatorio";
    }

    if (empty($errors)) {
        updateUserProfile($conn, $userEmail, $username, $avatar);

        // actualizar sesióm
        $_SESSION["user"]["name"] = $username;
        $success = "Perfil actualizado correctamente";
    }
}

// Cargar el usuario
$user = getUserProfile($conn, $userEmail);
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
                    src="<?php echo $user["avatar_url"] ?: "https://via.placeholder.com/100"; ?>"
                    style="width:100px; height:100px; border-radius:50%; object-fit:cover;"
                >
            </div>
            <!--  -->
            <div class="field">
                <label>Username</label>
                <span id="username-text"><?php echo htmlspecialchars($user['username']); ?></span>
                <input
                    type="text"
                    name="username"
                    id="username-input"
                    value="<?php echo htmlspecialchars($user['username']); ?>";
                    style="display:none;"
                >
            </div>



