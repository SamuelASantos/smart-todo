<?php
// src/Helpers/SmartAI.php

class SmartAI
{
    public static function generateInsight($userId)
    {
        $db = getConnection();

        // 1. Analisa o padrão de horário (Quando o usuário é mais produtivo?)
        $stmt = $db->prepare("SELECT HOUR(created_at) as hora, COUNT(*) as total 
                             FROM todo_activity_log 
                             WHERE user_id = ? AND action = 'completed' 
                             GROUP BY hora ORDER BY total DESC LIMIT 1");
        $stmt->execute([$userId]);
        $peakTime = $stmt->fetch();

        // 2. Analisa o equilíbrio de energia (Ele só faz coisa fácil?)
        $stmt = $db->prepare("SELECT energy_level, COUNT(*) as total 
                             FROM todo_activity_log al JOIN todo_tasks t ON al.task_id = t.id
                             WHERE al.user_id = ? AND al.action = 'completed'
                             GROUP BY energy_level");
        $stmt->execute([$userId]);
        $energies = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        // 3. Verifica se há tarefas de alta prioridade "mofando"
        $stmt = $db->prepare("SELECT COUNT(*) FROM todo_tasks 
                             WHERE user_id = ? AND status = 'pending' 
                             AND priority = 'high' AND created_at < DATE_SUB(NOW(), INTERVAL 3 DAY)");
        $stmt->execute([$userId]);
        $staleHighPriority = $stmt->fetchColumn();

        // --- GERAÇÃO DA SUGESTÃO ---

        // Regra 1: Procrastinação de tarefas importantes
        if ($staleHighPriority > 0) {
            return [
                'icon' => '⚠️',
                'title' => 'Atenção às Prioridades',
                'message' => "Você tem {$staleHighPriority} tarefas críticas paradas há mais de 3 dias. Que tal usar o Modo Foco nelas agora?",
                'color' => 'amber'
            ];
        }

        // Regra 2: Padrão de Horário
        if ($peakTime) {
            $h = $peakTime['hora'];
            $periodo = ($h < 12) ? "manhã" : (($h < 18) ? "tarde" : "noite");
            return [
                'icon' => '📈',
                'title' => 'Seu Ritmo Biológico',
                'message' => "Seu pico de produtividade costuma ser à **{$periodo}**. Reserve esse horário para suas tarefas de **Alta Energia (🧠)**.",
                'color' => 'blue'
            ];
        }

        // Regra 3: Equilíbrio de Energia
        $high = $energies['high'] ?? 0;
        $low = $energies['low'] ?? 0;
        if ($low > $high * 3) {
            return [
                'icon' => '🧠',
                'title' => 'Desafio Sugerido',
                'message' => "Você concluiu muitas tarefas simples ultimamente. Que tal encarar um desafio de alta concentração hoje?",
                'color' => 'purple'
            ];
        }

        // Default: Incentivo geral
        return [
            'icon' => '✨',
            'title' => 'Continue assim!',
            'message' => "Sua consistência é a chave. Continue alimentando o sistema para receber insights mais precisos.",
            'color' => 'slate'
        ];
    }
}