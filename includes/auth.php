<?php
require_once "db.php";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function login($email, $password) {
    global $conn;
    try {
        $stmt = $conn->prepare("SELECT id, username, email, password_hash, is_active FROM users WHERE email = :email LIMIT 1");
        $stmt->bindParam(":email", $email, PDO::PARAM_STR);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return [
                'success' => false,
                'error' => 'Correu electrònic no registrat',
                'errors' => ['email' => 'No existeix cap compte amb aquest correu electrònic']
            ];
        }

        if (!$user['is_active']) {
            return [
                'success' => false,
                'error' => 'Compte pendent de validació',
                'errors' => ['general' => 'La teva compte està pendent de validació. Revisa el teu correu per activar-la.']
            ];
        }

        if (hash("sha256", $password) !== $user['password_hash']) {
            return [
                'success' => false,
                'error' => 'Contrasenya incorrecta',
                'errors' => ['password' => 'La contrasenya és incorrecta']
            ];
        }

        $_SESSION["user"] = [
            "id" => $user["id"],
            "email" => $user["email"],
            "name" => $user["username"]
        ];
        return ["success" => true];
    } catch (PDOException $e) {
        return [
            'success' => false,
            'error' => 'Error de base de dades',
            'errors' => ['general' => 'Hi ha hagut un error en accedir a la base de dades']
        ];
    }
}

function isLogged() {
    return isset($_SESSION["user"]);
}

function logout() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Limpiar todas las variables de sesión
    $_SESSION = [];

    // Destruir la sesión
    session_destroy();

    // Eliminar la cookie de sesión del navegador
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(), 
            '', 
            time() - 42000, 
            $params["path"], 
            $params["domain"], 
            $params["secure"], 
            $params["httponly"]
        );
    }
}

function estaLogeado() {
    return isset($_SESSION['user']);
}