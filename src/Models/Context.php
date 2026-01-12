<?php
// src/Models/Context.php
require_once __DIR__ . '/../../config/database.php';

class Context
{
    public static function getAllByUser($userId)
    {
        $db = getConnection();
        // A tabela correta é todo_contexts
        $stmt = $db->prepare("SELECT * FROM todo_contexts WHERE user_id = ? ORDER BY name ASC");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public static function create($userId, $name, $icon = '📍')
    {
        $db = getConnection();
        if ($_SESSION['user_plan'] === 'free') {
            $stmt = $db->prepare("SELECT COUNT(*) FROM todo_contexts WHERE user_id = ?");
            $stmt->execute([$userId]);
            if ($stmt->fetchColumn() >= 3)
                return false; // Bloqueia no banco
        }
        $stmt = $db->prepare("INSERT INTO todo_contexts (user_id, name, icon) VALUES (?, ?, ?)");
        return $stmt->execute([$userId, $name, $icon]);
    }

    public static function update($userId, $id, $name, $icon)
    {
        $db = getConnection();
        $stmt = $db->prepare("UPDATE todo_contexts SET name = ?, icon = ? WHERE id = ? AND user_id = ?");
        return $stmt->execute([$name, $icon, $id, $userId]);
    }

    public static function delete($userId, $id)
    {
        $db = getConnection();
        // Aqui estava o erro: a tabela deve ser todo_contexts
        $stmt = $db->prepare("DELETE FROM todo_contexts WHERE id = ? AND user_id = ?");
        return $stmt->execute([$id, $userId]);
    }
}