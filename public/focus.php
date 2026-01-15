<?php
require_once __DIR__ . '/../src/Auth/Auth.php';
require_once __DIR__ . '/../src/Models/Task.php';

// Proteção de acesso e fuso horário
Auth::check();
date_default_timezone_set('America/Recife');

$userId = $_SESSION['user_id'];
$contextId = $_GET['context'] ?? null;

// Busca a tarefa sugerida pelo algoritmo
$task = Task::getSmartSuggestion($userId, $contextId);

function getEnergyLabel($level)
{
    $map = [
        'low' => '🌱 Baixa Energia',
        'medium' => '⚡ Média Energia',
        'high' => '🧠 Alta Concentração'
    ];
    return $map[$level] ?? '⚡ Média Energia';
}
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modo Foco - Smart Todo</title>

    <!-- Scripts Externos -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="icon" type="image/png" href="/assets/images/favicon-16x16.png?v=1.1">

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            orange: '#D25B2E',
                            black: '#1C1E24',
                            dark: '#0A0B0D'
                        }
                    },
                    fontFamily: { sans: ['Plus Jakarta Sans', 'sans-serif'] }
                }
            }
        }
    </script>

    <style>
        body {
            background-color: #0A0B0D;
        }

        .animate-focus-in {
            animation: focusScale 0.6s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes focusScale {
            from {
                opacity: 0;
                transform: scale(0.95) translateY(20px);
            }

            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }

        /* Estilização para Markdown no Modo Foco */
        .markdown-focus {
            color: #94a3b8;
            line-height: 1.6;
        }

        .markdown-focus strong {
            color: #f1f5f9;
        }

        .markdown-focus ul {
            list-style-type: disc;
            margin-left: 1.5rem;
            text-align: left;
            display: inline-block;
        }
    </style>
</head>

<body class="text-white h-screen flex flex-col overflow-hidden">

    <!-- HEADER: Minimalista para não distrair -->
    <header class="p-6 flex justify-between items-center shrink-0 z-10">
        <a href="index.php" class="group flex items-center gap-2 text-slate-500 hover:text-brand-orange transition-all">
            <div
                class="w-10 h-10 rounded-full bg-white/5 flex items-center justify-center group-hover:bg-brand-orange/10">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                    </path>
                </svg>
            </div>
            <span class="text-xs font-bold uppercase tracking-widest hidden sm:inline">Encerrar Foco</span>
        </a>

        <div class="px-4 py-1.5 bg-brand-orange/10 border border-brand-orange/20 rounded-full">
            <p class="text-[10px] font-black text-brand-orange uppercase tracking-[0.3em] animate-pulse">Modo Foco Ativo
            </p>
        </div>
    </header>

    <!-- ÁREA CENTRAL: Foco total na tarefa -->
    <main class="flex-1 flex flex-col items-center justify-center px-6 pb-12 overflow-y-auto custom-scrollbar">

        <?php if ($task): ?>
            <div class="w-full max-w-2xl flex flex-col items-center text-center animate-focus-in space-y-10">

                <!-- IDENTIFICAÇÃO: Contexto e Energia -->
                <div class="flex flex-col items-center gap-4">
                    <!-- TAG DE CONTEXTO (NOVO) -->
                    <div class="flex items-center gap-3 bg-white/5 px-6 py-2 rounded-full border border-white/10 shadow-xl">
                        <span class="text-2xl"><?= $task['context_icon'] ?></span>
                        <span class="text-xs font-black uppercase tracking-[0.2em] text-slate-300">
                            <?= htmlspecialchars($task['context_name']) ?>
                        </span>
                    </div>

                    <div
                        class="px-4 py-1 bg-slate-800/50 rounded-lg text-[10px] font-bold text-slate-500 uppercase tracking-widest">
                        <?= getEnergyLabel($task['energy_level']) ?>
                    </div>
                </div>

                <!-- TÍTULO -->
                <h1 class="text-4xl md:text-7xl font-extrabold tracking-tighter leading-[1.1] text-white">
                    <?= htmlspecialchars($task['title']) ?>
                </h1>

                <!-- DESCRIÇÃO EM MARKDOWN -->
                <?php if ($task['description']): ?>
                    <div id="raw-desc" class="hidden"><?= htmlspecialchars($task['description']) ?></div>
                    <div id="markdown-dest"
                        class="markdown-focus text-lg md:text-xl max-w-lg mx-auto bg-white/5 p-8 rounded-[2rem] border border-white/5">
                        <!-- Renderizado via JS -->
                    </div>
                <?php endif; ?>

                <!-- PRAZO (Se houver) -->
                <?php if ($task['due_date']):
                    $overdue = (strtotime($task['due_date']) < strtotime(date('Y-m-d')));
                    ?>
                    <div class="flex items-center gap-2 <?= $overdue ? 'text-rose-500' : 'text-amber-500' ?> font-bold">
                        <span class="text-xl">📅</span>
                        <span class="text-sm uppercase tracking-widest italic">Prazo:
                            <?= date('d/m/Y', strtotime($task['due_date'])) ?></span>
                    </div>
                <?php endif; ?>

                <!-- AÇÕES -->
                <div class="w-full max-w-sm space-y-4 pt-6">
                    <form method="POST" action="index.php">
                        <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                        <input type="hidden" name="toggle_task" value="1">
                        <button type="submit"
                            class="w-full bg-brand-orange hover:bg-orange-700 active:scale-95 text-white py-6 rounded-[2rem] font-black text-2xl shadow-2xl shadow-brand-orange/20 transition-all uppercase italic tracking-tighter">
                            Concluir agora
                        </button>
                    </form>

                    <a href="index.php"
                        class="block text-slate-600 hover:text-white font-bold text-xs uppercase tracking-[0.3em] transition-colors py-2">
                        Pular esta tarefa
                    </a>
                </div>

            </div>
        <?php else: ?>
            <!-- ESTADO VAZIO: Parabéns -->
            <div class="flex flex-col items-center space-y-8 animate-focus-in">
                <div class="relative">
                    <div class="absolute inset-0 bg-brand-orange/20 blur-3xl rounded-full"></div>
                    <span class="relative text-9xl">🎉</span>
                </div>
                <div class="space-y-2">
                    <h2 class="text-4xl font-black italic tracking-tighter">TUDO LIMPO!</h2>
                    <p class="text-slate-500 max-w-xs mx-auto leading-relaxed">Você completou todas as tarefas pendentes
                        para este contexto.</p>
                </div>
                <a href="index.php"
                    class="bg-white text-brand-black px-12 py-4 rounded-full font-black uppercase tracking-widest hover:scale-105 transition-all">Voltar
                    ao Início</a>
            </div>
        <?php endif; ?>

    </main>

    <!-- FOOTER: Branding Samsantos -->
    <footer class="p-8 text-center shrink-0">
        <p class="text-[9px] text-slate-800 font-bold uppercase tracking-[0.5em]">Todo • Samsantos</p>
    </footer>

    <script>
        // Renderizador de Markdown para o Modo Foco
        window.addEventListener('DOMContentLoaded', () => {
            const raw = document.getElementById('raw-desc');
            const dest = document.getElementById('markdown-dest');
            if (raw && dest) {
                marked.setOptions({ gfm: true, breaks: true });
                dest.innerHTML = marked.parse(raw.textContent.trim());
            }
        });
    </script>

</body>

</html>