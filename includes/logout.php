<?php
session_start();
require_once "auth.php"; // para tener la función logout()

logout(); // limpia la sesión
var_dump($_SESSION); // debería estar vacío
// redirige al login
echo '<script>window.location.href="../app.php?view=login";</script>';
exit;