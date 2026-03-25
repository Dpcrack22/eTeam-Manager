<?php
    require_once __DIR__ . "/db.php";
    function createUser(PDO $conn, $name, $email, $password, $avatarFile = null) {
        $password_hash = hash('sha256', $password);

        $avatarUrl = null;

        // Manejar upload de avatar si viene archivo
        if ($avatarFile && !empty($avatarFile['tmp_name'])) {
            $targetDir = __DIR__ . "/../uploads/avatars/";
            $filename = uniqid() . "-" . basename($avatarFile['name']);
            $targetPath = $targetDir . $filename;

            // Mover archivo a carpeta uploads
            if (move_uploaded_file($avatarFile['tmp_name'], $targetPath)) {
                // Ruta web relativa para la DB
                $avatarUrl = "/uploads/avatars/" . $filename;
            }
        }

        $stmt = $conn->prepare("
            INSERT INTO users 
            (username, email, password_hash, avatar_url, is_active, created_at, updated_at, last_login_at) 
            VALUES (?, ?, ?, ?, 1, NOW(), NOW(), NOW())
        ");
        $stmt->execute([$name, $email, $password_hash, $avatarUrl]);

        return $conn->lastInsertId();
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