<?php
require_once __DIR__ . '/../../config/database.php';

class Task
{
    public static function getByUser($userId, $contextId = null)
    {
        $db = getConnection();
        $sql = "SELECT t.*, c.name as context_name, c.icon as context_icon 
                FROM todo_tasks t LEFT JOIN todo_contexts c ON t.context_id = c.id 
                WHERE t.user_id = ? AND t.deleted_at IS NULL";

        $params = [$userId];
        if ($contextId) {
            $sql .= " AND t.context_id = ?";
            $params[] = $contextId;
        }

        $sql .= " ORDER BY t.status ASC, CASE t.priority WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END, t.created_at DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function create($userId, $data)
    {
        $db = getConnection();

        // VALIDAÇÃO DE PLANO FREE (Limite de 20 tarefas pendentes)
        if (isset($_SESSION['user_plan']) && $_SESSION['user_plan'] === 'free') {
            $stmtCount = $db->prepare("SELECT COUNT(*) FROM todo_tasks WHERE user_id = ? AND status = 'pending' AND deleted_at IS NULL");
            $stmtCount->execute([$userId]);
            if ($stmtCount->fetchColumn() >= 20) {
                return false; // Bloqueia a criação
            }
        }

        $stmt = $db->prepare("INSERT INTO todo_tasks (user_id, context_id, title, description, energy_level, priority, due_date) 
                             VALUES (?, ?, ?, ?, ?, ?, ?)");
        return $stmt->execute([
            $userId,
            $data['context_id'] ?: null,
            $data['title'],
            $data['description'] ?: null,
            $data['energy_level'],
            $data['priority'] ?: 'medium',
            !empty($data['due_date']) ? $data['due_date'] : null
        ]);
    }

    public static function update($userId, $id, $data)
    {
        $db = getConnection();
        $stmt = $db->prepare("UPDATE todo_tasks SET title = ?, description = ?, energy_level = ?, priority = ?, context_id = ?, due_date = ? 
                             WHERE id = ? AND user_id = ?");
        return $stmt->execute([
            $data['title'],
            $data['description'] ?: null,
            $data['energy_level'],
            $data['priority'],
            $data['context_id'] ?: null,
            !empty($data['due_date']) ? $data['due_date'] : null,
            $id,
            $userId
        ]);
    }

    public static function toggleStatus($userId, $taskId)
    {
        $db = getConnection();
        $stmt = $db->prepare("SELECT status, title FROM todo_tasks WHERE id = ? AND user_id = ?");
        $stmt->execute([$taskId, $userId]);
        $task = $stmt->fetch();

        if ($task) {
            $newStatus = ($task['status'] === 'completed') ? 'pending' : 'completed';
            $completedAt = ($newStatus === 'completed') ? date('Y-m-d H:i:s') : null;
            $db->prepare("UPDATE todo_tasks SET status = ?, completed_at = ? WHERE id = ?")->execute([$newStatus, $completedAt, $taskId]);

            if ($newStatus === 'completed') {
                $db->prepare("INSERT INTO todo_activity_log (user_id, task_id, action, details) VALUES (?, ?, 'completed', ?)")
                    ->execute([$userId, $taskId, "Tarefa '{$task['title']}' concluída."]);
            }
            return true;
        }
        return false;
    }

    public static function softDelete($userId, $id)
    {
        $db = getConnection();
        return $db->prepare("UPDATE todo_tasks SET deleted_at = NOW() WHERE id = ? AND user_id = ?")->execute([$id, $userId]);
    }

    public static function getSmartSuggestion($userId, $contextId = null, $energyLevel = null)
    {
        $db = getConnection();
        $sql = "SELECT t.*, c.name as context_name, c.icon as context_icon FROM todo_tasks t 
                LEFT JOIN todo_contexts c ON t.context_id = c.id 
                WHERE t.user_id = ? AND t.status = 'pending' AND t.deleted_at IS NULL";

        $params = [$userId];
        if ($contextId) {
            $sql .= " AND t.context_id = ?";
            $params[] = $contextId;
        }
        if ($energyLevel) {
            $sql .= " AND t.energy_level = ?";
            $params[] = $energyLevel;
        }

        $sql .= " ORDER BY CASE WHEN t.due_date IS NOT NULL AND t.due_date <= NOW() THEN 1 ELSE 2 END ASC, 
                  CASE t.priority WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END ASC, t.created_at ASC LIMIT 1";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch();
    }
}