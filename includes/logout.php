<?php
require_once "auth.php"; // para tener la función logout()

logout(); // limpia la sesión
console.log(var_dump($_SESSION));
// redirige al login
echo '<script>window.location.href="app.php?view=login";</script>';
exit;