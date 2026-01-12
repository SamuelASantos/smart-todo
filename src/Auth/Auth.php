<?php
require_once __DIR__ . '/../../config/database.php';

class Auth
{
    /**
     * Inicia a sessão de forma segura
     */
    private static function initSession()
    {
        if (session_status() === PHP_SESSION_NONE) {
            if ($_SERVER['SERVER_NAME'] !== 'localhost' && $_SERVER['SERVER_NAME'] !== '127.0.0.1') {
                ini_set('session.cookie_domain', '.samsantos.com.br');
            }
            session_start();
        }
    }

    /**
     * Registra um novo usuário (Inicia como Free por padrão)
     */
    public static function register($name, $email, $password)
    {
        $db = getConnection();

        // Verifica se o email já existe
        $stmt = $db->prepare("SELECT id FROM todo_users WHERE email = ?");
        $stmt->execute([trim($email)]);
        if ($stmt->fetch()) {
            return "Este e-mail já está cadastrado.";
        }

        $hash = password_hash(trim($password), PASSWORD_DEFAULT);

        // Insere o usuário. subscription_plan é 'free' por padrão no banco.
        $stmt = $db->prepare("INSERT INTO todo_users (name, email, password) VALUES (?, ?, ?)");
        if ($stmt->execute([trim($name), trim($email), $hash])) {
            return true;
        }
        return "Erro ao cadastrar usuário.";
    }

    /**
     * Realiza o login do usuário e verifica validade do plano
     */
    public static function login($email, $password)
    {
        $db = getConnection();
        $stmt = $db->prepare("SELECT * FROM todo_users WHERE email = ?");
        $stmt->execute([trim($email)]);
        $user = $stmt->fetch();

        if ($user && password_verify(trim($password), $user['password'])) {
            self::initSession();

            // Lógica de Expiração de Plano
            $plan = $user['subscription_plan'];
            if ($plan === 'premium' && !empty($user['expires_at'])) {
                if (strtotime($user['expires_at']) < time()) {
                    $plan = 'free';
                }
            }

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_plan'] = $plan;
            $_SESSION['expires_at'] = $user['expires_at'];
            return true;
        }
        return "E-mail ou senha inválidos.";
    }

    /**
     * Atualiza a senha de um usuário autenticado
     */
    public static function updatePassword($userId, $newPassword)
    {
        $db = getConnection();
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        return $db->prepare("UPDATE todo_users SET password = ? WHERE id = ?")->execute([$hash, $userId]);
    }

    /**
     * Verifica se o usuário está logado
     */
    public static function check()
    {
        self::initSession();
        if (!isset($_SESSION['user_id'])) {
            header("Location: login.php");
            exit;
        }
    }

    /**
     * Encerra a sessão
     */
    public static function logout()
    {
        self::initSession();
        session_destroy();
        header("Location: login.php");
        exit;
    }
}