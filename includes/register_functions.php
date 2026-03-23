<?php
    require_once __DIR__ . "/db.php";
    function createUser(PDO $conn, $name, $email, $password) {
        $password_hash = hash('sha256', $password);
        $stmt = $conn->prepare("INSERT INTO users (username, email, password_hash, avatar_url, is_active, created_at, updated_at, last_login_at) VALUES (?, ?, ?, NULL, 1, '2026-03-20 16:56:30', '2026-03-20 16:56:30', '2026-03-20 16:56:30')");
        $stmt->execute([$name, $email, $password_hash]);

        return $conn->lastInsertId(); // Función para devolver el ultimo id y usarlo mas adelante
    }

    function getAllOrganizations(PDO $conn) {
        $stmt = $conn->prepare("SELECT id, name FROM organizations ORDER BY name ASC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    function validateOrganization(PDO $conn, $organizationId) {
        $stmt = $conn->prepare("SELECT id FROM organizations WHERE id = ? LIMIT 1");
        $stmt->execute([$organizationId]);
        return $stmt->fetch() !== false;
    }

    function addUserToOrganization(PDO $conn, $userId, $organizationId, $role) {
        $now = date('Y-m-d H:i:s');
        $stmt = $conn->prepare("
            INSERT INTO organization_members (organization_id, user_id, role, joined_at, is_active)
            VALUES (?, ?, ?, ?, 1)
        ");
        $stmt->execute([$organizationId, $userId, $role, $now]);
    }
?>