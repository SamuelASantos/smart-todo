<?php
// src/Models/Note.php
require_once __DIR__ . '/../../config/database.php';

class Note
{
    /**
     * Busca todos os lembretes fixos de um usuário
     */
    public static function getByUser($userId)
    {
        $db = getConnection();
        $stmt = $db->prepare("SELECT * FROM todo_notes WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    /**
     * Salva um novo lembrete
     */
    public static function save($userId, $content)
    {
        $db = getConnection();
        $stmt = $db->prepare("INSERT INTO todo_notes (user_id, content) VALUES (?, ?)");
        return $stmt->execute([$userId, $content]);
    }

    /**
     * Exclui um lembrete
     */
    public static function delete($userId, $id)
    {
        $db = getConnection();
        $stmt = $db->prepare("DELETE FROM todo_notes WHERE id = ? AND user_id = ?");
        return $stmt->execute([$id, $userId]);
    }
}