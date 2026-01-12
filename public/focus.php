<?php
require_once __DIR__ . '/../src/Auth/Auth.php';
require_once __DIR__ . '/../src/Models/Task.php';

Auth::check();
$userId = $_SESSION['user_id'];
$contextId = $_GET['context'] ?? null;
$task = Task::getSmartSuggestion($userId, $contextId);

function getEnergyLabel($level)
{
    $map = ['low' => '🌱 Baixa Energia', 'medium' => '⚡ Média Energia', 'high' => '🧠 Alta Concentração'];
    return $map[$level] ?? '⚡ Média Energia';
}
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modo Foco - Smart Todo</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="icon" type="image/png" href="/assets/images/favicon-16x16.png?v=1.1">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #020617;
        }

        .animate-fade-in {
            animation: fadeIn 0.5s ease-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>

<body class="text-white h-screen flex flex-col">

    <!-- BOTÃO SAIR (Topo Esquerdo) -->
    <div class="p-6 shrink-0">
        <a href="index.php"
            class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-slate-900 border border-slate-800 text-slate-400 hover:text-white transition-colors">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
        </a>
    </div>

    <!-- CONTEÚDO CENTRAL -->
    <main class="flex-1 flex flex-col items-center justify-center px-6 pb-12 overflow-y-auto">

        <?php if ($task): ?>
            <div class="w-full max-w-md flex flex-col items-center text-center animate-fade-in space-y-8">

                <!-- Badges de Contexto e Energia -->
                <div class="flex flex-col items-center gap-3">
                    <span
                        class="px-4 py-1.5 bg-blue-500/10 border border-blue-500/20 text-blue-400 rounded-full text-[10px] font-black uppercase tracking-widest">
                        <?= $task['context_icon'] ?>     <?= $task['context_name'] ?>
                    </span>
                    <span
                        class="px-4 py-1.5 bg-slate-800 text-slate-400 rounded-full text-[10px] font-black uppercase tracking-widest">
                        <?= getEnergyLabel($task['energy_level']) ?>
                    </span>
                </div>

                <!-- Título da Tarefa -->
                <h1 class="text-4xl md:text-5xl font-extrabold tracking-tight leading-tight px-2">
                    <?= htmlspecialchars($task['title']) ?>
                </h1>

                <!-- Descrição -->
                <?php if ($task['description']): ?>
                    <div class="bg-slate-900/50 p-6 rounded-3xl border border-slate-800/50 w-full">
                        <p class="text-slate-400 text-base md:text-lg leading-relaxed italic">
                            "<?= nl2br(htmlspecialchars($task['description'])) ?>"
                        </p>
                    </div>
                <?php endif; ?>

                <!-- Prazo -->
                <?php if ($task['due_date']): ?>
                    <div
                        class="flex items-center gap-2 text-amber-500 font-bold bg-amber-500/5 px-4 py-2 rounded-xl border border-amber-500/10">
                        <span class="text-xl">📅</span>
                        <span class="text-sm uppercase tracking-wider">Prazo:
                            <?= date('d/m/Y', strtotime($task['due_date'])) ?></span>
                    </div>
                <?php endif; ?>

                <!-- Ações -->
                <div class="w-full space-y-4 pt-4">
                    <form method="POST" action="index.php">
                        <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                        <input type="hidden" name="toggle_task" value="1">
                        <button type="submit"
                            class="w-full bg-blue-600 hover:bg-blue-500 active:scale-95 text-white py-5 rounded-[2rem] font-black text-xl shadow-2xl shadow-blue-600/20 transition-all">
                            Concluir agora
                        </button>
                    </form>

                    <a href="index.php"
                        class="block text-slate-500 hover:text-white font-bold text-sm uppercase tracking-[0.2em] transition-colors py-2">
                        Pular tarefa
                    </a>
                </div>

            </div>
        <?php else: ?>
            <div class="flex flex-col items-center space-y-6 animate-fade-in">
                <span class="text-8xl">🎉</span>
                <h2 class="text-3xl font-bold">Tudo pronto!</h2>
                <p class="text-slate-500 max-w-xs leading-relaxed">Você concluiu todas as tarefas planejadas para este
                    contexto.</p>
                <a href="index.php" class="bg-white text-black px-10 py-4 rounded-full font-bold shadow-lg">Voltar</a>
            </div>
        <?php endif; ?>

    </main>

    <!-- FOOTER SUBTIL -->
    <footer class="p-8 text-center shrink-0">
        <p class="text-[9px] text-slate-700 font-bold uppercase tracking-[0.4em]">Menos fricção • Mais execução</p>
    </footer>

</body>

</html>