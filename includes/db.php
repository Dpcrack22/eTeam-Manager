<?php
/* Codigo para cargar la base de datos */

$env = parse_ini_file(__DIR__ . "/../.env");
var_dump($env);
$servername = "eteam-manager";
$username = $env['db_user'];
$password = $env['db_password'];
$dbname = "eteam_manager";

try {
    $conn = new PDO(
        "mysql:host=$servername;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    die("Error en connectar amb la base de dades.");
}
?>