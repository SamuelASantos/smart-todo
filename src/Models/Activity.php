<?php
// src/Models/Activity.php
require_once __DIR__ . '/../../config/database.php';

class Activity
{
    public static function getWeeklyStats($userId)
    {
        $db = getConnection();
        // Conta tarefas concluídas por nível de energia nos últimos 7 dias
        $sql = "SELECT t.energy_level, COUNT(*) as total 
                FROM todo_activity_log al
                JOIN todo_tasks t ON al.task_id = t.id
                WHERE al.user_id = ? AND al.action = 'completed' 
                AND al.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                GROUP BY t.energy_level";

        $stmt = $db->prepare($sql);
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public static function getRecentLog($userId)
    {
        $db = getConnection();
        $stmt = $db->prepare("SELECT al.*, t.title 
                             FROM todo_activity_log al 
                             JOIN todo_tasks t ON al.task_id = t.id 
                             WHERE al.user_id = ? 
                             ORDER BY al.created_at DESC LIMIT 10");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }
}