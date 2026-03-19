<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once "auth.php"; // para tener la función logout()

logout(); // limpia la sesión
// redirige al login
echo '<script>window.location.href="app.php?view=dashboard";</script>';
exit;