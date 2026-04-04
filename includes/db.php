<?php
$envPath = __DIR__ . '/../.env';
$env = is_file($envPath) ? parse_ini_file($envPath) : [];

$dbHost = $env['db_host'] ?? 'localhost';
$dbName = $env['db_name'] ?? 'eteam_manager';
$dbUser = $env['db_user'] ?? 'root';
$dbPassword = $env['db_password'] ?? '';

try {
    $conn = new PDO(
        "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4",
        $dbUser,
        $dbPassword,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $exception) {
    die('Error en connectar amb la base de dades.');
}
