<?php
require_once __DIR__ . '/../../config/database.php';

class Auth
{
    private static function initSession()
    {
        if (session_status() === PHP_SESSION_NONE) {
            if ($_SERVER['SERVER_NAME'] !== 'localhost' && $_SERVER['SERVER_NAME'] !== '127.0.0.1') {
                ini_set('session.cookie_domain', '.samsantos.com.br');
            }
            session_start();
        }
    }

    // REGISTRO COM AUTO-LOGIN
    public static function register($name, $email, $password)
    {
        $db = getConnection();
        $email = trim($email);

        $stmt = $db->prepare("SELECT id FROM todo_users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch())
            return "Este e-mail já está cadastrado.";

        $hash = password_hash(trim($password), PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT INTO todo_users (name, email, password) VALUES (?, ?, ?)");

        if ($stmt->execute([trim($name), $email, $hash])) {
            // Após cadastrar, faz o login automático
            return self::login($email, $password);
        }
        return "Erro ao cadastrar usuário.";
    }

    public static function login($email, $password)
    {
        $db = getConnection();
        $stmt = $db->prepare("SELECT * FROM todo_users WHERE email = ?");
        $stmt->execute([trim($email)]);
        $user = $stmt->fetch();

        if ($user && password_verify(trim($password), $user['password'])) {
            self::initSession();
            $plan = $user['subscription_plan'] ?? 'free';
            if ($plan === 'premium' && !empty($user['expires_at'])) {
                if (strtotime($user['expires_at']) < time())
                    $plan = 'free';
            }
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_plan'] = $plan;
            return true;
        }
        return "E-mail ou senha inválidos.";
    }

    public static function generateResetToken($email)
    {
        $db = getConnection();
        $stmt = $db->prepare("SELECT id FROM todo_users WHERE email = ?");
        $stmt->execute([$email]);
        if (!$stmt->fetch())
            return false;

        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

        $stmt = $db->prepare("INSERT INTO todo_password_resets (email, token, expires_at) VALUES (?, ?, ?)");
        $stmt->execute([$email, $token, $expires]);
        return $token;
    }

    public static function resetPasswordWithToken($token, $newPassword)
    {
        $db = getConnection();
        $stmt = $db->prepare("SELECT email FROM todo_password_resets WHERE token = ? AND expires_at > NOW() LIMIT 1");
        $stmt->execute([$token]);
        $reset = $stmt->fetch();

        if ($reset) {
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            $db->prepare("UPDATE todo_users SET password = ? WHERE email = ?")->execute([$hash, $reset['email']]);
            $db->prepare("DELETE FROM todo_password_resets WHERE email = ?")->execute([$reset['email']]);
            return true;
        }
        return false;
    }

    public static function updatePassword($userId, $newPassword)
    {
        $db = getConnection();
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        return $db->prepare("UPDATE todo_users SET password = ? WHERE id = ?")->execute([$hash, $userId]);
    }

    public static function check()
    {
        self::initSession();
        if (!isset($_SESSION['user_id'])) {
            header("Location: login.php");
            exit;
        }
    }

    public static function logout()
    {
        self::initSession();
        session_destroy();
        header("Location: login.php");
        exit;
    }
}