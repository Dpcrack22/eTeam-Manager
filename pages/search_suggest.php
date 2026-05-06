<?php
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json; charset=utf-8');

$q = trim((string) ($_GET['q'] ?? ''));
$type = trim((string) ($_GET['type'] ?? 'users'));

if ($q === '' || mb_strlen($q) < 2) {
    echo json_encode([]);
    exit;
}

$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

try {
    if ($type === 'teams') {
        $stmt = $conn->prepare('SELECT id, name, tag FROM teams WHERE name LIKE :q OR tag LIKE :q LIMIT 10');
        $stmt->bindValue(':q', '%' . $q . '%', PDO::PARAM_STR);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($rows ?: []);
        exit;
    }

    $stmt = $conn->prepare('SELECT id, username, avatar_url FROM users WHERE username LIKE :q OR email LIKE :q LIMIT 10');
    $stmt->bindValue(':q', '%' . $q . '%', PDO::PARAM_STR);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($rows ?: []);
} catch (Throwable $e) {
    echo json_encode([]);
}
