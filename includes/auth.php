<?php
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function login($email, $password)
{
    global $conn;

    try {
        $statement = $conn->prepare(
            'SELECT u.id, u.username, u.email, u.password_hash, u.avatar_url, u.is_active, om.role AS organization_role, o.name AS organization_name FROM users u LEFT JOIN organization_members om ON om.user_id = u.id AND om.is_active = 1 LEFT JOIN organizations o ON o.id = om.organization_id WHERE u.email = :email LIMIT 1'
        );
        $statement->bindValue(':email', $email, PDO::PARAM_STR);
        $statement->execute();
        $user = $statement->fetch();

        if (!$user) {
            return [
                'success' => false,
                'error' => 'Correu electrònic no registrat',
                'errors' => ['email' => 'No existeix cap compte amb aquest correu electrònic'],
            ];
        }

        if (!(int) $user['is_active']) {
            return [
                'success' => false,
                'error' => 'Compte pendent de validació',
                'errors' => ['general' => 'La teva compte està pendent de validació.'],
            ];
        }

        if (hash('sha256', $password) !== $user['password_hash']) {
            return [
                'success' => false,
                'error' => 'Contrasenya incorrecta',
                'errors' => ['password' => 'La contrasenya és incorrecta'],
            ];
        }

        $updateStatement = $conn->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id LIMIT 1');
        $updateStatement->bindValue(':id', (int) $user['id'], PDO::PARAM_INT);
        $updateStatement->execute();

        $_SESSION['user'] = [
            'id' => (int) $user['id'],
            'email' => $user['email'],
            'name' => $user['username'],
            'avatar_url' => $user['avatar_url'],
            'role' => $user['organization_role'] ?? 'Member',
            'organization' => $user['organization_name'] ?? 'Sin organización',
        ];

        return ['success' => true];
    } catch (PDOException $exception) {
        return [
            'success' => false,
            'error' => 'Error de base de dades',
            'errors' => ['general' => 'Hi ha hagut un error en accedir a la base de dades'],
        ];
    }
}

function isLogged(): bool
{
    return !empty($_SESSION['user']);
}

function logout(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}

function requireAuth(): void
{
    if (!isLogged()) {
        header('Location: app.php?view=login');
        exit;
    }
}

function redirectIfAuthenticated(): void
{
    if (isLogged()) {
        header('Location: app.php?view=dashboard');
        exit;
    }
}
