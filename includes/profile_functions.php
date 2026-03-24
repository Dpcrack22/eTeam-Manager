<?php
    require_once __DIR__ . "/db.php";
    
    function getUserProfile(PDO $conn, string $email) : array|false {
        $stmt = $conn->prepare("
            SELECT
                u.username,
                u.email,
                u.avatar_url,
                om.role,
                o.name AS organization_name
            FROM users u
            LEFT JOIN organization_members om ON om.user_id = u.id
            LEFT JOIN organizations o ON o.id = om.organization_id
            WHERE u.email = ?
            LIMIT 1
        ");
        $stmt->execute([$email]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    function updateUserProfile(PDO $conn, string $email, string $username, string $avatarUrl = null): bool {
        $stmt = $conn->prepare("
            UPDATE users
            set username = ?, avatar_url = ?, updated_at = NOW()
            WHERE email = ?
        ");
        return $stmt->execute([$username, $avatarUrl, $email]);
    }

    function changeUserPassword(PDO $conn, string $email, string $currentPassword, string $newPassword) {
        $stmt = $conn->prepare("SELECT password_hash FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) return "Usuario no encontrado.";

        if (hash("sha256", $currentPassword) !== $user["password_hash"]) {
            return "Contraseña actual incorrecta";
        }

        // Guardar la nueva contraseña
        $newHash = hash("sha256", $newPassword);
        $stmt = $conn->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE email = ?");
        return $stmt->execute([$newHash, $email]);
    }

    function changeUserEmail(PDO $conn, string $email, string $newEmail): bool|string {
        // Comprobar que no existan mas usuarios con ese email
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND email != ?");
        $stmt->execute([$newEmail, $email]);
        if ($stmt->fetch()) return "Email ya registrado";

        $stmt = $conn->prepare("UPDATE users SET email = ?, updated_at = NOW() WHERE email = ?");
        return $stmt->execute([$newEmail, $email]);
    }
?>