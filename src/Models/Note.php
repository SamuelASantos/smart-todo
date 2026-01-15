<?php
require_once __DIR__ . '/../../config/database.php';

class Note
{
    public static function getByUser($userId)
    {
        $db = getConnection();
        $stmt = $db->prepare("SELECT * FROM todo_notes WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public static function save($userId, $content)
    {
        $db = getConnection();
        // Para manter simples, vamos permitir apenas 5 notas fixas (UX de lembretes rápidos)
        $stmt = $db->prepare("INSERT INTO todo_notes (user_id, content) VALUES (?, ?)");
        return $stmt->execute([$userId, $content]);
    }

    public static function delete($userId, $id)
    {
        $db = getConnection();
        $stmt = $db->prepare("DELETE FROM todo_notes WHERE id = ? AND user_id = ?");
        return $stmt->execute([$id, $userId]);
    }
}