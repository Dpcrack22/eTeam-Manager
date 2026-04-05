<?php
$envPath = __DIR__ . '/../.env';
$env = is_file($envPath) ? parse_ini_file($envPath) : [];

$dbHost = $env['db_host'] ?? getenv('DB_HOST') ?: 'localhost';
$dbName = $env['db_name'] ?? getenv('DB_NAME') ?: 'eteam_manager';

$connectionCandidates = [];

if (!empty($env['db_user']) || !empty($env['db_password'])) {
    $connectionCandidates[] = [
        'user' => $env['db_user'] ?? 'root',
        'password' => $env['db_password'] ?? '',
    ];
}

if (getenv('DB_USER') !== false || getenv('DB_PASSWORD') !== false) {
    $connectionCandidates[] = [
        'user' => getenv('DB_USER') !== false ? (string) getenv('DB_USER') : 'root',
        'password' => getenv('DB_PASSWORD') !== false ? (string) getenv('DB_PASSWORD') : '',
    ];
}

$connectionCandidates[] = [
    'user' => 'eteam_app',
    'password' => 'pV7!Qm2#Rk9@Ls4^Tx8$Nd3!Wa6Zc1',
];

$connectionCandidates[] = [
    'user' => 'root',
    'password' => '',
];

$lastException = null;

foreach ($connectionCandidates as $candidate) {
    try {
        $conn = new PDO(
            "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4",
            $candidate['user'],
            $candidate['password'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );

        return;
    } catch (PDOException $exception) {
        $lastException = $exception;
    }
}

die('Error en connectar amb la base de dades. Revisa .env, DB_HOST, DB_NAME, DB_USER y DB_PASSWORD.');
