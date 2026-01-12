<?php
require_once __DIR__ . '/../src/Auth/Auth.php';
require_once __DIR__ . '/../src/Models/Context.php';
require_once __DIR__ . '/../src/Models/Task.php';

Auth::check();
date_default_timezone_set('America/Recife');

$userId = $_SESSION['user_id'];
$userName = $_SESSION['user_name'];
$userPlan = $_SESSION['user_plan']; // 'free' ou 'premium'

// --- LÓGICA DE LIMITES (SaaS) ---
$isPremium = ($userPlan === 'premium');
$contexts = Context::getAllByUser($userId);
$selectedContextId = $_GET['context'] ?? null;
$tasks = Task::getByUser($userId, $selectedContextId);

// Contagem para travas do plano Free
$db = getConnection();
$stmtCount = $db->prepare("SELECT COUNT(*) FROM todo_tasks WHERE user_id = ? AND status = 'pending' AND deleted_at IS NULL");
$stmtCount->execute([$userId]);
$totalPendingTasks = $stmtCount->fetchColumn();

$taskLimitReached = (!$isPremium && $totalPendingTasks >= 20);
$contextLimitReached = (!$isPremium && count($contexts) >= 3);

// --- PROCESSAMENTO DE AÇÕES ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_context']) && !$contextLimitReached)
        Context::create($userId, $_POST['context_name'], $_POST['context_icon'] ?: '📍');
    if (isset($_POST['delete_context']))
        Context::delete($userId, $_POST['context_id']);

    if (isset($_POST['add_task']) && !$taskLimitReached)
        Task::create($userId, $_POST);
    if (isset($_POST['edit_task']))
        Task::update($userId, $_POST['task_id'], $_POST);
    if (isset($_POST['delete_task']))
        Task::softDelete($userId, $_POST['task_id']);
    if (isset($_POST['toggle_task']))
        Task::toggleStatus($userId, $_POST['task_id']);

    if (isset($_POST['change_password']) && !empty($_POST['new_password'])) {
        Auth::updatePassword($userId, $_POST['new_password']);
    }

    header("Location: index.php" . (isset($_GET['context']) ? "?context=" . $_GET['context'] : ""));
    exit;
}

// Identificação do Contexto Atual
$currentContextName = "Todas as Tarefas";
$currentContextIcon = "🏠";
foreach ($contexts as $ctx) {
    if ($ctx['id'] == $selectedContextId) {
        $currentContextName = $ctx['name'];
        $currentContextIcon = $ctx['icon'];
        break;
    }
}

function getEnergyBadge($level)
{
    $map = [
        'low' => ['bg' => 'bg-[#73937e]/20', 'text' => 'text-[#73937e]', 'label' => '🌱 Baixa'],
        'medium' => ['bg' => 'bg-[#254e70]/20', 'text' => 'text-[#254e70]', 'label' => '⚡ Média'],
        'high' => ['bg' => 'bg-[#D25B2E]/20', 'text' => 'text-[#D25B2E]', 'label' => '🧠 Alta'],
    ];
    return $map[$level] ?? $map['medium'];
}

function renderEnergyLegend()
{ ?>
    <div class="bg-slate-100 dark:bg-white/5 p-4 rounded-2xl space-y-2 border border-brand-orange/20 mt-4">
        <p class="text-[10px] font-bold text-brand-orange uppercase tracking-widest mb-1">💡 Guia de Energia</p>
        <div class="flex items-start gap-3 text-xs dark:text-slate-300">
            <span class="text-[#73937e] font-bold">🌱</span>
            <p><b>Baixa:</b> Tarefas automáticas ou rápidas.</p>
        </div>
        <div class="flex items-start gap-3 text-xs dark:text-slate-300">
            <span class="text-[#254e70] font-bold">⚡</span>
            <p><b>Média:</b> Requer atenção moderada.</p>
        </div>
        <div class="flex items-start gap-3 text-xs dark:text-slate-300">
            <span class="text-[#D25B2E] font-bold">🧠</span>
            <p><b>Alta:</b> Foco total e profundidade (Deep Work).</p>
        </div>
    </div>
<?php } ?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Todo - Samsantos</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="icon" type="image/png" href="/assets/images/favicon-16x16.png?v=1.1">

    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        brand: { white: '#F0F0F0', orange: '#D25B2E', black: '#1C1E24', green: '#73937e', blue: '#254e70' }
                    },
                    fontFamily: { sans: ['Plus Jakarta Sans', 'sans-serif'] }
                }
            }
        }
    </script>

    <style>
        body {
            background-color: #F8FAFC;
            transition: background-color 0.3s ease;
        }

        .task-appear {
            animation: fadeIn 0.3s ease-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .desc-truncate {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            cursor: pointer;
        }

        .desc-full {
            display: block;
            cursor: pointer;
        }

        .markdown-body a {
            color: #D25B2E;
            text-decoration: underline;
            font-weight: 600;
        }

        .sidebar-mobile {
            transition: transform 0.3s ease;
        }

        @media (max-width: 768px) {
            .sidebar-mobile {
                transform: translateX(-100%);
            }

            .sidebar-mobile.active {
                transform: translateX(0);
            }
        }
    </style>
</head>

<body class="bg-brand-white dark:bg-[#111216] text-brand-black dark:text-brand-white flex h-screen overflow-hidden">

    <!-- Overlay Mobile -->
    <div id="overlay" onclick="toggleMenu()"
        class="fixed inset-0 bg-brand-black/60 backdrop-blur-sm z-30 hidden transition-opacity"></div>

    <!-- SIDEBAR -->
    <aside id="sidebar"
        class="sidebar-mobile fixed md:relative z-40 w-72 bg-white dark:bg-brand-black border-r border-slate-200 dark:border-slate-800 h-full flex flex-col shadow-sm md:translate-x-0">
        <div class="p-8 flex flex-col gap-2">
            <div class="flex items-center gap-3 text-brand-orange">
                <div class="bg-brand-orange p-2 rounded-xl text-white font-bold shadow-lg shadow-brand-orange/30">S
                </div>
                <h1 class="text-xl font-extrabold tracking-tight">SmartTodo</h1>
            </div>
            <div class="flex items-center gap-2">
                <?php if ($isPremium): ?>
                    <span
                        class="text-[9px] bg-brand-orange/20 text-brand-orange px-2 py-0.5 rounded-full font-black uppercase">Premium
                        Account</span>
                <?php else: ?>
                    <button onclick="document.getElementById('modal-upgrade').classList.remove('hidden')"
                        class="text-[9px] bg-slate-100 dark:bg-white/5 text-slate-500 px-2 py-0.5 rounded-full font-black uppercase hover:bg-brand-orange hover:text-white transition-all">Free
                        Plan • Upgrade</button>
                <?php endif; ?>
            </div>
        </div>

        <nav class="flex-1 overflow-y-auto px-4 space-y-1 custom-scrollbar">
            <a href="index.php"
                class="flex items-center gap-3 p-3 rounded-xl transition-all <?= !$selectedContextId ? 'bg-brand-orange/10 text-brand-orange font-bold shadow-sm' : 'hover:bg-slate-100 dark:hover:bg-white/5' ?>">
                <span class="text-lg">🏠</span> Todas as Tarefas
            </a>
            <div
                class="pt-6 px-4 py-2 text-[10px] font-bold text-slate-400 uppercase tracking-widest flex justify-between items-center">
                <span>Contextos</span>
                <span class="text-[9px]"><?= count($contexts) ?>/<?= $isPremium ? '∞' : '3' ?></span>
            </div>

            <?php foreach ($contexts as $ctx): ?>
                <div
                    class="group flex items-center justify-between rounded-xl transition-all <?= $selectedContextId == $ctx['id'] ? 'bg-brand-orange/10 text-brand-orange font-bold shadow-sm' : 'hover:bg-slate-100 dark:hover:bg-white/5 text-slate-500' ?>">
                    <a href="?context=<?= $ctx['id'] ?>" class="flex-1 p-3 flex items-center gap-3 truncate font-medium">
                        <span><?= $ctx['icon'] ?></span> <?= htmlspecialchars($ctx['name']) ?>
                    </a>
                    <form method="POST" onsubmit="return confirm('Excluir contexto?')">
                        <input type="hidden" name="delete_context" value="1"><input type="hidden" name="context_id"
                            value="<?= $ctx['id'] ?>">
                        <button type="submit"
                            class="opacity-0 group-hover:opacity-100 pr-2 text-slate-300 hover:text-red-500">✕</button>
                    </form>
                </div>
            <?php endforeach; ?>

            <?php if (!$contextLimitReached): ?>
                <form method="POST" class="mt-4 px-2">
                    <input type="hidden" name="add_context" value="1">
                    <input type="text" name="context_name" placeholder="+ Novo contexto" required
                        class="w-full bg-slate-100 dark:bg-white/5 border-none rounded-xl py-2.5 px-4 text-xs focus:ring-2 focus:ring-brand-orange outline-none">
                </form>
            <?php endif; ?>
        </nav>

        <div class="p-6 border-t border-slate-100 dark:border-slate-800 space-y-1">
            <button onclick="toggleDarkMode()"
                class="flex items-center gap-3 p-3 text-sm font-bold w-full rounded-xl bg-slate-100 dark:bg-white/5 hover:bg-brand-orange/10 transition-all">
                <span id="dark-icon">🌙</span> <span id="dark-text">Modo Escuro</span>
            </button>
            <button onclick="document.getElementById('modal-help').classList.remove('hidden')"
                class="flex items-center gap-3 p-3 text-brand-blue dark:text-blue-400 font-bold text-sm w-full rounded-xl hover:bg-brand-blue/10 transition-all">
                <span>❓</span> Guia de Uso
            </button>
            <a href="<?= $isPremium ? 'history.php' : '#' ?>"
                onclick="<?= $isPremium ? '' : "document.getElementById('modal-upgrade').classList.remove('hidden'); return false;" ?>"
                class="flex items-center gap-3 p-3 text-sm font-bold <?= $isPremium ? 'text-slate-600' : 'text-slate-300' ?> rounded-xl hover:bg-slate-100 dark:hover:bg-white/5">
                <span>📊</span> Relatórios
                <?= $isPremium ? '' : '<span class="text-[8px] bg-slate-200 dark:bg-white/10 px-1 rounded ml-auto">PRO</span>' ?>
            </a>
            <button onclick="document.getElementById('modal-settings').classList.remove('hidden')"
                class="w-full text-left p-3 text-[10px] font-bold text-slate-400 uppercase tracking-widest hover:text-brand-orange transition-all">⚙️
                Configurações</button>
            <a href="logout.php" class="block p-3 text-sm font-bold text-red-500 hover:underline">Sair</a>
        </div>
    </aside>

    <!-- MAIN -->
    <main class="flex-1 flex flex-col min-w-0 relative">
        <header
            class="h-20 md:h-24 bg-white/80 dark:bg-brand-black/80 backdrop-blur-md border-b border-slate-200 dark:border-slate-800 flex items-center justify-between px-6 md:px-10 shrink-0 z-10">
            <div class="flex items-center gap-4">
                <button onclick="toggleMenu()" class="md:hidden p-2 bg-brand-orange text-white rounded-lg shadow-lg">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path d="M4 6h16M4 12h16M4 18h16"></path>
                    </svg>
                </button>
                <div>
                    <h2 class="text-lg md:text-2xl font-extrabold flex items-center gap-2">
                        <span><?= $currentContextIcon ?></span> <?= htmlspecialchars($currentContextName) ?>
                    </h2>
                    <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest">Tarefas:
                        <?= $totalPendingTasks ?> / <?= $isPremium ? '∞' : '20' ?></p>
                </div>
            </div>

            <div class="hidden md:flex items-center gap-4">
                <?php if ($isPremium): ?>
                    <a href="focus.php?context=<?= $selectedContextId ?>"
                        class="bg-brand-orange/10 text-brand-orange px-6 py-3 rounded-2xl text-sm font-bold border border-brand-orange/20 hover:bg-brand-orange hover:text-white transition-all shadow-sm">⚡
                        MODO FOCO</a>
                <?php else: ?>
                    <button onclick="document.getElementById('modal-upgrade').classList.remove('hidden')"
                        class="bg-slate-100 dark:bg-white/5 text-slate-300 px-6 py-3 rounded-2xl text-sm font-bold border border-slate-200 dark:border-slate-800">⚡
                        FOCO [PRO]</button>
                <?php endif; ?>

                <button
                    onclick="<?= $taskLimitReached ? "document.getElementById('modal-upgrade').classList.remove('hidden')" : "document.getElementById('modal-task').classList.remove('hidden')" ?>"
                    class="bg-brand-orange text-white px-8 py-3 rounded-2xl text-sm font-bold shadow-xl shadow-brand-orange/30 hover:scale-105 transition-all italic">
                    <?= $taskLimitReached ? "LIMITE ATINGIDO" : "+ NOVA TAREFA" ?>
                </button>
            </div>
        </header>

        <section class="flex-1 overflow-y-auto p-4 md:p-10 space-y-4 pb-32 md:pb-10 custom-scrollbar">
            <?php foreach ($tasks as $task):
                $energy = getEnergyBadge($task['energy_level']);
                $done = $task['status'] === 'completed';
                $overdue = (!empty($task['due_date']) && strtotime($task['due_date']) < strtotime(date('Y-m-d')) && !$done);
                ?>
                <div
                    class="group bg-white dark:bg-brand-black p-5 rounded-3xl border <?= $overdue ? 'border-red-500/50 bg-red-500/5' : 'border-slate-200 dark:border-slate-800' ?> flex items-start justify-between hover:border-brand-orange transition-all task-appear shadow-sm">
                    <div class="flex items-start gap-4 md:gap-6 flex-1 min-w-0">
                        <form method="POST" class="mt-1">
                            <input type="hidden" name="toggle_task" value="1"><input type="hidden" name="task_id"
                                value="<?= $task['id'] ?>">
                            <input type="checkbox" onchange="this.form.submit()" <?= $done ? 'checked' : '' ?>
                                class="w-7 h-7 rounded-full border-2 border-slate-300 dark:border-slate-600 text-brand-orange focus:ring-brand-orange cursor-pointer">
                        </form>
                        <div class="flex-1 min-w-0">
                            <h3 id="task-title-<?= $task['id'] ?>"
                                class="font-bold text-base md:text-lg <?= $done ? 'line-through text-slate-400' : '' ?>">
                                <?= htmlspecialchars($task['title']) ?></h3>
                            <?php if (!empty($task['description'])): ?>
                                <div id="raw-desc-<?= $task['id'] ?>" class="hidden">
                                    <?= htmlspecialchars($task['description']) ?></div>
                                <div id="desc-<?= $task['id'] ?>" onclick="toggleDesc(<?= $task['id'] ?>, event)"
                                    class="markdown-body desc-truncate text-sm text-slate-500 dark:text-slate-400 mt-2 border-l-2 border-brand-orange/20 pl-3">
                                </div>
                            <?php endif; ?>
                            <div
                                class="flex flex-wrap items-center gap-2 mt-3 text-[9px] font-bold uppercase tracking-widest">
                                <span
                                    class="px-2 py-1 rounded-lg <?= $energy['bg'] ?> <?= $energy['text'] ?>"><?= $energy['label'] ?></span>
                                <?php if (!$selectedContextId && !empty($task['context_name'])): ?>
                                    <span
                                        class="bg-slate-100 dark:bg-white/5 text-slate-400 px-2 py-1 rounded-lg italic"><?= $task['context_icon'] ?>
                                        <?= htmlspecialchars($task['context_name']) ?></span>
                                <?php endif; ?>
                                <?php if ($task['due_date']): ?>
                                    <span
                                        class="px-2 py-1 rounded-lg <?= $overdue ? 'bg-red-500 text-white shadow-lg' : 'bg-brand-blue/10 text-brand-blue' ?>">📅
                                        <?= date('d/m/Y', strtotime($task['due_date'])) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-1 md:opacity-0 group-hover:opacity-100 transition-all ml-2">
                        <button
                            onclick="openEditModal(<?= $task['id'] ?>, '<?= $task['context_id'] ?>', '<?= $task['energy_level'] ?>', '<?= $task['due_date'] ?>')"
                            class="p-2 text-slate-400 hover:text-brand-orange">✏️</button>
                        <form method="POST" onsubmit="return confirm('Excluir?')"><input type="hidden" name="delete_task"
                                value="1"><input type="hidden" name="task_id" value="<?= $task['id'] ?>"><button
                                type="submit" class="p-2 text-slate-400 hover:text-red-500">🗑️</button></form>
                    </div>
                </div>
            <?php endforeach; ?>
        </section>

        <!-- FLOATING BAR MOBILE -->
        <div
            class="md:hidden fixed bottom-0 left-0 right-0 p-5 bg-white/90 dark:bg-brand-black/90 backdrop-blur-xl border-t border-slate-200 dark:border-slate-800 flex gap-4 z-40">
            <?php if ($isPremium): ?>
                <a href="focus.php?context=<?= $selectedContextId ?>"
                    class="flex-1 bg-brand-white dark:bg-white/5 text-brand-orange py-4 rounded-2xl font-black text-center border border-brand-orange/20 text-xs">⚡
                    FOCO</a>
            <?php else: ?>
                <button onclick="document.getElementById('modal-upgrade').classList.remove('hidden')"
                    class="flex-1 bg-slate-100 dark:bg-white/5 text-slate-400 py-4 rounded-2xl font-black text-xs opacity-50">⚡
                    FOCO</button>
            <?php endif; ?>

            <button
                onclick="<?= $taskLimitReached ? "document.getElementById('modal-upgrade').classList.remove('hidden')" : "document.getElementById('modal-task').classList.remove('hidden')" ?>"
                class="flex-[1.8] bg-brand-orange text-white py-4 rounded-2xl font-black shadow-lg shadow-brand-orange/30 text-xs italic uppercase">
                <?= $taskLimitReached ? "LIMITE ATINGIDO" : "+ NOVA TAREFA" ?>
            </button>
        </div>
    </main>

    <!-- MODAL UPGRADE (Paywall) -->
    <div id="modal-upgrade"
        class="hidden fixed inset-0 bg-brand-black/80 backdrop-blur-md flex items-center justify-center p-4 z-[100]">
        <div
            class="bg-white dark:bg-brand-black rounded-[3rem] p-10 w-full max-w-lg shadow-2xl text-center border border-white/10">
            <div class="text-6xl mb-6">🚀</div>
            <h3 class="text-3xl font-black text-brand-orange mb-4 italic">Seja Premium</h3>
            <p class="text-slate-500 dark:text-slate-400 mb-8 leading-relaxed">Libere todo o poder da produtividade
                inteligente e remova todas as barreiras.</p>
            <div class="space-y-4 mb-10 text-left bg-slate-50 dark:bg-white/5 p-6 rounded-3xl">
                <div class="flex items-center gap-3 text-sm font-bold italic"><span class="text-brand-orange">✓</span>
                    Tarefas Ilimitadas</div>
                <div class="flex items-center gap-3 text-sm font-bold italic"><span class="text-brand-orange">✓</span>
                    Contextos Ilimitados</div>
                <div class="flex items-center gap-3 text-sm font-bold italic"><span class="text-brand-orange">✓</span>
                    Algoritmo de Modo Foco</div>
                <div class="flex items-center gap-3 text-sm font-bold italic"><span class="text-brand-orange">✓</span>
                    Smart Insights e Relatórios</div>
            </div>
            <a href="checkout.php"
                class="block w-full bg-brand-orange text-white py-5 rounded-2xl font-black text-xl shadow-xl mb-4 transition-all hover:scale-105 text-center italic uppercase">ASSINAR
                AGORA</a>
            <button onclick="document.getElementById('modal-upgrade').classList.add('hidden')"
                class="text-slate-400 font-bold text-xs uppercase tracking-widest">Agora não</button>
        </div>
    </div>

    <!-- MODAL ADICIONAR -->
    <div id="modal-task"
        class="hidden fixed inset-0 bg-brand-black/60 backdrop-blur-sm flex items-center justify-center p-4 z-50 overflow-y-auto">
        <div class="bg-white dark:bg-brand-black rounded-[2.5rem] p-8 md:p-10 w-full max-w-lg shadow-2xl my-auto">
            <h3 class="text-xl md:text-2xl font-extrabold mb-6 text-brand-orange italic">Nova Tarefa Inteligente</h3>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="add_task" value="1">
                <input type="text" name="title" placeholder="O que vamos fazer hoje?" required
                    class="w-full bg-slate-100 dark:bg-white/5 rounded-2xl p-4 text-lg outline-none focus:ring-2 focus:ring-brand-orange dark:text-white font-medium">
                <textarea name="description" placeholder="Notas e Detalhes (Markdown)..." rows="3"
                    class="w-full bg-slate-100 dark:bg-white/5 rounded-2xl p-4 text-sm outline-none resize-none dark:text-white"></textarea>
                <div class="grid grid-cols-2 gap-4">
                    <select name="context_id"
                        class="bg-slate-100 dark:bg-white/5 rounded-2xl p-4 text-sm outline-none dark:text-white">
                        <option value="">🏠 Geral</option>
                        <?php foreach ($contexts as $ctx): ?>
                            <option value="<?= $ctx['id'] ?>" <?= $selectedContextId == $ctx['id'] ? 'selected' : '' ?>>
                                <?= $ctx['icon'] ?>     <?= htmlspecialchars($ctx['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="energy_level"
                        class="bg-slate-100 dark:bg-white/5 rounded-2xl p-4 text-sm outline-none dark:text-white">
                        <option value="low">🌱 Baixa</option>
                        <option value="medium" selected>⚡ Média</option>
                        <option value="high">🧠 Alta</option>
                    </select>
                </div>
                <input type="date" name="due_date"
                    class="w-full bg-slate-100 dark:bg-white/5 rounded-2xl p-4 text-sm dark:text-white">
                <?php renderEnergyLegend(); ?>
                <button type="submit"
                    class="w-full bg-brand-orange text-white py-5 rounded-2xl font-black shadow-xl mt-4">SALVAR
                    TAREFA</button>
            </form>
        </div>
    </div>

    <!-- MODAL EDITAR -->
    <div id="modal-edit-task"
        class="hidden fixed inset-0 bg-brand-black/60 backdrop-blur-sm flex items-center justify-center p-4 z-50 overflow-y-auto">
        <div class="bg-white dark:bg-brand-black rounded-[2.5rem] p-8 md:p-10 w-full max-w-lg shadow-2xl my-auto">
            <h3 class="text-xl md:text-2xl font-extrabold mb-6 text-brand-orange italic">Editar Tarefa</h3>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="edit_task" value="1"><input type="hidden" name="task_id" id="edit-task-id">
                <input type="text" name="title" id="edit-task-title" required
                    class="w-full bg-slate-100 dark:bg-white/5 rounded-2xl p-4 text-lg outline-none font-bold dark:text-white">
                <textarea name="description" id="edit-task-desc" rows="4"
                    class="w-full bg-slate-100 dark:bg-white/5 rounded-2xl p-4 text-sm outline-none resize-none dark:text-white"></textarea>
                <div class="grid grid-cols-2 gap-4">
                    <select name="context_id" id="edit-task-context"
                        class="bg-slate-100 dark:bg-white/5 rounded-2xl p-4 text-sm dark:text-white">
                        <option value="">🏠 Geral</option>
                        <?php foreach ($contexts as $ctx): ?>
                            <option value="<?= $ctx['id'] ?>"><?= $ctx['icon'] ?>     <?= htmlspecialchars($ctx['name']) ?>
                            </option><?php endforeach; ?>
                    </select>
                    <select name="energy_level" id="edit-task-energy"
                        class="bg-slate-100 dark:bg-white/5 rounded-2xl p-4 text-sm dark:text-white">
                        <option value="low">🌱 Baixa</option>
                        <option value="medium">⚡ Média</option>
                        <option value="high">🧠 Alta</option>
                    </select>
                </div>
                <input type="date" name="due_date" id="edit-task-date"
                    class="w-full bg-slate-100 dark:bg-white/5 rounded-2xl p-4 text-sm dark:text-white">
                <div class="flex gap-4 pt-4">
                    <button type="button" onclick="document.getElementById('modal-edit-task').classList.add('hidden')"
                        class="flex-1 bg-slate-100 dark:bg-white/10 py-4 rounded-2xl font-bold">Voltar</button>
                    <button type="submit"
                        class="flex-[2] bg-brand-orange text-white py-4 rounded-2xl font-black shadow-lg">ATUALIZAR</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL CONFIGS -->
    <div id="modal-settings"
        class="hidden fixed inset-0 bg-brand-black/60 backdrop-blur-sm flex items-center justify-center p-4 z-50">
        <div class="bg-white dark:bg-brand-black rounded-[2.5rem] p-8 w-full max-w-md shadow-2xl">
            <h3 class="text-xl font-extrabold mb-6 text-brand-orange italic px-2">Configurações</h3>
            <form method="POST" class="space-y-6">
                <input type="hidden" name="change_password" value="1">
                <input type="password" name="new_password" placeholder="Nova Senha" required
                    class="w-full bg-slate-100 dark:bg-white/5 rounded-2xl p-4 outline-none dark:text-white font-medium">
                <button type="submit"
                    class="w-full bg-brand-orange text-white py-4 rounded-2xl font-black shadow-lg italic">MUDAR SENHA
                    AGORA</button>
                <button type="button" onclick="document.getElementById('modal-settings').classList.add('hidden')"
                    class="w-full text-slate-400 text-xs font-bold uppercase tracking-widest">Fechar</button>
            </form>
        </div>
    </div>

    <!-- MODAL AJUDA -->
    <div id="modal-help"
        class="hidden fixed inset-0 bg-brand-black/60 backdrop-blur-sm flex items-center justify-center p-4 z-50">
        <div
            class="bg-white dark:bg-brand-black rounded-[2.5rem] p-8 md:p-10 w-full max-w-2xl shadow-2xl overflow-y-auto max-h-[90vh] custom-scrollbar">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-2xl font-extrabold text-brand-orange italic">📖 Guia do Usuário</h3>
                <button onclick="document.getElementById('modal-help').classList.add('hidden')"
                    class="text-slate-400 text-3xl">&times;</button>
            </div>
            <div class="space-y-6 text-sm leading-relaxed dark:text-slate-300">
                <p><b>O Poder do Contexto 📍:</b> Filtre tarefas pelo lugar onde você está.</p>
                <p><b>Energia Biológica 🧠:</b> Use os níveis de energia para combinar tarefas com seu estado mental.
                </p>
                <p><b>Markdown 📝:</b> Use **negrito**, listas (- item) e [links] nas notas.</p>
                <p><b>Modo Foco ⚡:</b> Deixe o algoritmo escolher a tarefa mais urgente para você.</p>
                <?php renderEnergyLegend(); ?>
            </div>
            <button onclick="document.getElementById('modal-help').classList.add('hidden')"
                class="w-full mt-8 bg-brand-orange text-white py-4 rounded-2xl font-black uppercase italic shadow-lg shadow-brand-orange/20">Vamos
                lá!</button>
        </div>
    </div>

    <script>
        // --- TEMA (Dark Mode) ---
        if (localStorage.getItem('darkMode') === 'enabled' || (!localStorage.getItem('darkMode') && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
            updateDarkUI(true);
        }
        function toggleDarkMode() {
            const isDark = document.documentElement.classList.toggle('dark');
            localStorage.setItem('darkMode', isDark ? 'enabled' : 'disabled');
            updateDarkUI(isDark);
        }
        function updateDarkUI(isDark) {
            document.getElementById('dark-icon').textContent = isDark ? '☀️' : '🌙';
            document.getElementById('dark-text').textContent = isDark ? 'Modo Claro' : 'Modo Escuro';
        }

        // --- MARKDOWN CONFIG ---
        const renderer = new marked.Renderer();
        renderer.link = (href, title, text) => `<a href="${href}" target="_blank" rel="nofollow noopener noreferrer">${text}</a>`;
        marked.setOptions({ gfm: true, breaks: true, renderer: renderer });

        function renderAllMarkdown() {
            document.querySelectorAll('.markdown-body').forEach(container => {
                const id = container.id.split('-')[1];
                const raw = document.getElementById('raw-desc-' + id);
                if (raw) container.innerHTML = marked.parse(raw.textContent.trim());
            });
        }
        window.addEventListener('DOMContentLoaded', renderAllMarkdown);

        // --- INTERFACE ---
        function toggleMenu() {
            document.getElementById('sidebar').classList.toggle('active');
            document.getElementById('overlay').classList.toggle('hidden');
        }

        function toggleDesc(id, event) {
            if (event.target.tagName === 'A' || event.target.tagName === 'INPUT') return;
            document.getElementById('desc-' + id).classList.toggle('desc-truncate');
            document.getElementById('desc-' + id).classList.toggle('desc-full');
        }

        function openEditModal(id, contextId, energy, dueDate) {
            const title = document.getElementById('task-title-' + id).textContent.trim();
            const desc = document.getElementById('raw-desc-' + id) ? document.getElementById('raw-desc-' + id).textContent.trim() : '';

            document.getElementById('modal-edit-task').classList.remove('hidden');
            document.getElementById('edit-task-id').value = id;
            document.getElementById('edit-task-title').value = title;
            document.getElementById('edit-task-desc').value = desc; // Sem duplicação de /n
            document.getElementById('edit-task-context').value = contextId;
            document.getElementById('edit-task-energy').value = energy;
            document.getElementById('edit-task-date').value = dueDate ? dueDate.split(' ')[0] : '';
        }

        window.onclick = function (e) {
            ['modal-task', 'modal-edit-task', 'modal-settings', 'modal-help', 'modal-upgrade'].forEach(m => {
                const modal = document.getElementById(m);
                if (e.target == modal) modal.classList.add('hidden');
            });
        }
    </script>
</body>

</html>