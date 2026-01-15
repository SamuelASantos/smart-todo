<?php
require_once __DIR__ . '/../src/Auth/Auth.php';
require_once __DIR__ . '/../src/Models/Context.php';
require_once __DIR__ . '/../src/Models/Task.php';

Auth::check();
date_default_timezone_set('America/Recife');

$userId = $_SESSION['user_id'];
$userName = $_SESSION['user_name'];
$userPlan = $_SESSION['user_plan'] ?? 'free';
$isPremium = ($userPlan === 'premium');

// --- LÓGICA DE LIMITES ---
$contexts = Context::getAllByUser($userId);
$selectedContextId = $_GET['context'] ?? null;
$tasks = Task::getByUser($userId, $selectedContextId);

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
    if (isset($_POST['change_password']) && !empty($_POST['new_password']))
        Auth::updatePassword($userId, $_POST['new_password']);

    header("Location: index.php" . (isset($_GET['context']) ? "?context=" . $_GET['context'] : ""));
    exit;
}

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
    <div class="bg-slate-100 dark:bg-white/5 p-5 rounded-[2rem] space-y-3 border border-brand-orange/10">
        <p class="text-[10px] font-black text-brand-orange uppercase tracking-widest mb-1">💡 Guia de Energia Samsantos</p>
        <div class="flex items-start gap-3 text-xs dark:text-slate-300">
            <span class="text-[#73937e]">🌱</span>
            <p><b>Baixa:</b> Tarefas rápidas/mecânicas. Ideal para quando o cansaço bate.</p>
        </div>
        <div class="flex items-start gap-3 text-xs dark:text-slate-300">
            <span class="text-[#254e70]">⚡</span>
            <p><b>Média:</b> Exige atenção, mas não exaustão mental.</p>
        </div>
        <div class="flex items-start gap-3 text-xs dark:text-slate-300">
            <span class="text-[#D25B2E]">🧠</span>
            <p><b>Alta:</b> Foco total. Suas tarefas de maior impacto (Deep Work).</p>
        </div>
    </div>
<?php } ?>

<!DOCTYPE html>
<html lang="pt-br" class="">

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

        .markdown-body ul {
            list-style-type: disc;
            margin-left: 1.25rem;
        }

        .markdown-body a {
            color: #D25B2E;
            text-decoration: underline;
            font-weight: 700;
        }

        .custom-scrollbar::-webkit-scrollbar {
            width: 4px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #D25B2E;
            border-radius: 10px;
        }

        .sidebar-mobile {
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
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

<body
    class="bg-brand-white dark:bg-[#111216] text-brand-black dark:text-brand-white flex h-screen overflow-hidden transition-colors duration-500">

    <div id="overlay" onclick="toggleMenu()"
        class="fixed inset-0 bg-brand-black/70 backdrop-blur-md z-30 hidden transition-all"></div>

    <!-- SIDEBAR -->
    <aside id="sidebar"
        class="sidebar-mobile fixed md:relative z-40 w-72 bg-white dark:bg-brand-black border-r border-slate-200 dark:border-slate-800 h-full flex flex-col shadow-2xl md:shadow-none md:translate-x-0">
        <div class="p-8">
            <div class="flex items-center gap-3 text-brand-orange mb-2">
                <div
                    class="bg-brand-orange p-2 rounded-xl text-white font-bold italic shadow-lg shadow-brand-orange/30">
                    S</div>
                <h1 class="text-xl font-extrabold tracking-tighter uppercase italic">Smart Todo</h1>
            </div>
            <?php if ($isPremium): ?>
                <span
                    class="text-[9px] bg-brand-orange/10 text-brand-orange px-2 py-0.5 rounded-full font-black uppercase tracking-widest border border-brand-orange/20">Premium
                    Account</span>
            <?php else: ?>
                <button onclick="document.getElementById('modal-upgrade').classList.remove('hidden')"
                    class="text-[9px] bg-slate-100 dark:bg-white/5 text-slate-500 px-2 py-0.5 rounded-full font-black uppercase tracking-widest hover:bg-brand-orange hover:text-white transition-all">Free
                    Plan • Upgrade</button>
            <?php endif; ?>
        </div>

        <nav class="flex-1 overflow-y-auto px-4 space-y-1 custom-scrollbar">
            <a href="index.php"
                class="flex items-center gap-3 p-3 rounded-2xl transition-all <?= !$selectedContextId ? 'bg-brand-orange text-white font-bold shadow-lg shadow-brand-orange/30' : 'hover:bg-slate-100 dark:hover:bg-white/5' ?>">
                <span class="text-lg">🏠</span> Todas
            </a>
            <div
                class="pt-6 px-4 py-2 text-[10px] font-bold text-slate-400 uppercase tracking-[0.2em] flex justify-between">
                <span>Contextos</span>
                <span class="opacity-50"><?= count($contexts) ?>/<?= $isPremium ? '∞' : '3' ?></span>
            </div>
            <?php foreach ($contexts as $ctx): ?>
                <div
                    class="group flex items-center justify-between rounded-2xl transition-all <?= $selectedContextId == $ctx['id'] ? 'bg-brand-orange/10 text-brand-orange font-bold' : 'hover:bg-slate-100 dark:hover:bg-white/5 text-slate-500' ?>">
                    <a href="?context=<?= $ctx['id'] ?>" class="flex-1 p-3 flex items-center gap-3 truncate">
                        <span><?= $ctx['icon'] ?></span> <?= htmlspecialchars($ctx['name']) ?>
                    </a>
                    <form method="POST" onsubmit="return confirm('Excluir contexto?')">
                        <input type="hidden" name="delete_context" value="1"><input type="hidden" name="context_id"
                            value="<?= $ctx['id'] ?>">
                        <button type="submit"
                            class="opacity-0 group-hover:opacity-100 pr-3 text-slate-300 hover:text-red-500">✕</button>
                    </form>
                </div>
            <?php endforeach; ?>
        </nav>

        <div class="p-6 border-t border-slate-100 dark:border-slate-800 space-y-2">
            <button onclick="openSettings()"
                class="flex items-center gap-3 p-4 text-sm font-bold w-full rounded-2xl bg-slate-50 dark:bg-white/5 hover:bg-brand-orange/10 hover:text-brand-orange transition-all">
                <span>⚙️</span> Configurações
            </button>
            <a href="<?= $isPremium ? 'history.php' : '#' ?>"
                onclick="<?= $isPremium ? '' : "document.getElementById('modal-upgrade').classList.remove('hidden'); return false;" ?>"
                class="flex items-center gap-3 p-4 text-sm font-bold text-slate-500 hover:text-brand-orange transition-colors">📊
                Relatórios</a>
            <a href="logout.php"
                class="block p-4 text-xs font-bold text-red-400 uppercase tracking-widest opacity-60 hover:opacity-100">Sair
                da Conta</a>
        </div>
    </aside>

    <!-- MAIN -->
    <main class="flex-1 flex flex-col min-w-0 relative">
        <header
            class="h-24 bg-white/80 dark:bg-brand-black/80 backdrop-blur-md border-b border-slate-200 dark:border-slate-800 flex items-center justify-between px-6 md:px-10 shrink-0 z-10">
            <div class="flex items-center gap-4">
                <button onclick="toggleMenu()"
                    class="md:hidden p-3 bg-brand-orange text-white rounded-2xl shadow-lg shadow-brand-orange/30">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path d="M4 6h16M4 12h16M4 18h16"></path>
                    </svg>
                </button>
                <div>
                    <h2 class="text-xl md:text-3xl font-black flex items-center gap-2 italic tracking-tighter">
                        <span class="opacity-50"><?= $currentContextIcon ?></span>
                        <?= htmlspecialchars($currentContextName) ?>
                    </h2>
                    <p class="text-[10px] text-slate-400 font-bold uppercase tracking-[0.2em] mt-1">
                        <?= $totalPendingTasks ?> Pendências / <?= $isPremium ? 'Ilimitado' : 'Limite 20' ?>
                    </p>
                </div>
            </div>

            <div class="hidden md:flex items-center gap-4">
                <a href="focus.php?context=<?= $selectedContextId ?>"
                    class="bg-brand-orange/10 text-brand-orange px-8 py-4 rounded-[1.5rem] text-sm font-black border border-brand-orange/20 hover:bg-brand-orange hover:text-white transition-all shadow-sm italic uppercase tracking-tighter">⚡
                    Modo Foco</a>
                <button
                    onclick="<?= $taskLimitReached ? "document.getElementById('modal-upgrade').classList.remove('hidden')" : "document.getElementById('modal-task').classList.remove('hidden')" ?>"
                    class="bg-brand-orange text-white px-8 py-4 rounded-[1.5rem] text-sm font-black shadow-xl shadow-brand-orange/30 hover:scale-105 transition-all uppercase italic tracking-tighter">+
                    Nova Tarefa</button>
            </div>
        </header>

        <section class="flex-1 overflow-y-auto p-4 md:p-10 space-y-4 pb-32 md:pb-10 custom-scrollbar">
            <?php foreach ($tasks as $task):
                $energy = getEnergyBadge($task['energy_level']);
                $done = $task['status'] === 'completed';
                $overdue = (!empty($task['due_date']) && strtotime($task['due_date']) < strtotime(date('Y-m-d')) && !$done);
                ?>
                <div
                    class="group bg-white dark:bg-brand-black p-6 rounded-[2rem] border <?= $overdue ? 'border-red-500/50 bg-red-500/5' : 'border-slate-200 dark:border-slate-800' ?> flex items-start justify-between hover:border-brand-orange transition-all task-appear shadow-sm">
                    <div class="flex items-start gap-4 md:gap-6 flex-1 min-w-0">
                        <form method="POST" class="mt-1">
                            <input type="hidden" name="toggle_task" value="1"><input type="hidden" name="task_id"
                                value="<?= $task['id'] ?>">
                            <input type="checkbox" onchange="this.form.submit()" <?= $done ? 'checked' : '' ?>
                                class="w-7 h-7 rounded-full border-2 border-slate-300 dark:border-slate-700 text-brand-orange focus:ring-brand-orange cursor-pointer">
                        </form>
                        <div class="flex-1 min-w-0">
                            <h3 id="task-title-<?= $task['id'] ?>"
                                class="font-bold text-lg md:text-xl <?= $done ? 'line-through text-slate-300 opacity-50' : '' ?>">
                                <?= htmlspecialchars($task['title']) ?>
                            </h3>
                            <?php if (!empty($task['description'])): ?>
                                <div id="raw-desc-<?= $task['id'] ?>" class="hidden">
                                    <?= htmlspecialchars($task['description']) ?></div>
                                <div id="desc-<?= $task['id'] ?>" onclick="toggleDesc(<?= $task['id'] ?>, event)"
                                    class="markdown-body desc-truncate text-sm text-slate-500 dark:text-slate-400 mt-2 border-l-2 border-brand-orange/20 pl-4 leading-relaxed">
                                </div>
                            <?php endif; ?>
                            <div
                                class="flex flex-wrap items-center gap-2 mt-4 text-[9px] font-black uppercase tracking-widest">
                                <span
                                    class="px-2.5 py-1 rounded-lg <?= $energy['bg'] ?> <?= $energy['text'] ?>"><?= $energy['label'] ?></span>
                                <?php if (!$selectedContextId && !empty($task['context_name'])): ?>
                                    <span
                                        class="bg-slate-100 dark:bg-white/5 text-slate-400 px-2.5 py-1 rounded-lg italic"><?= $task['context_icon'] ?>
                                        <?= htmlspecialchars($task['context_name']) ?></span>
                                <?php endif; ?>
                                <?php if ($task['due_date']): ?>
                                    <span
                                        class="px-2.5 py-1 rounded-lg <?= $overdue ? 'bg-red-500 text-white shadow-lg' : 'bg-brand-blue/10 text-brand-blue' ?>">📅
                                        <?= date('d/m/Y', strtotime($task['due_date'])) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-1 md:opacity-0 group-hover:opacity-100 transition-all ml-4">
                        <button
                            onclick="openEditModal(<?= $task['id'] ?>, '<?= $task['context_id'] ?>', '<?= $task['energy_level'] ?>', '<?= $task['due_date'] ?>')"
                            class="p-3 text-slate-400 hover:text-brand-orange transition-colors">✏️</button>
                        <form method="POST" onsubmit="return confirm('Excluir?')">
                            <input type="hidden" name="delete_task" value="1"><input type="hidden" name="task_id"
                                value="<?= $task['id'] ?>">
                            <button type="submit"
                                class="p-3 text-slate-400 hover:text-red-500 transition-colors">🗑️</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </section>

        <!-- FLOATING BAR MOBILE (UX Upgrade) -->
        <!-- FLOATING BAR MOBILE (Foco e Nova Tarefa em destaque) -->
        <div
            class="md:hidden fixed bottom-0 left-0 right-0 p-5 bg-white/90 dark:bg-brand-black/90 backdrop-blur-xl border-t border-slate-200 dark:border-slate-800 flex gap-4 z-40 shadow-[0_-10px_30px_rgba(0,0,0,0.1)]">
            <a href="focus.php?context=<?= $selectedContextId ?>"
                class="flex-1 bg-slate-100 dark:bg-white/10 text-brand-orange py-5 rounded-3xl font-black text-center border border-brand-orange/20 text-xs italic tracking-widest">
                ⚡ FOCO
            </a>
            <button
                onclick="<?= $taskLimitReached ? "document.getElementById('modal-upgrade').classList.remove('hidden')" : "document.getElementById('modal-task').classList.remove('hidden')" ?>"
                class="flex-[2] bg-brand-orange text-white py-5 rounded-3xl font-black shadow-2xl shadow-brand-orange/40 text-xs italic uppercase tracking-tighter">
                <?= $taskLimitReached ? "Upgrade Premium" : "+ Nova Tarefa" ?>
            </button>
        </div>
    </main>

    <!-- MODAL SETTINGS (UNIFICADO) -->
    <div id="modal-settings"
        class="hidden fixed inset-0 bg-brand-black/80 backdrop-blur-sm z-[60] flex items-center justify-center p-4">
        <div
            class="bg-white dark:bg-brand-black w-full max-w-3xl rounded-[3rem] shadow-2xl overflow-hidden flex flex-col md:flex-row h-[85vh] md:h-auto border border-white/10">
            <div class="w-full md:w-56 bg-slate-50 dark:bg-white/5 p-8 space-y-2 shrink-0">
                <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-6 ml-2">Preferências</h3>
                <button onclick="switchTab('tab-account')"
                    class="tab-btn w-full text-left p-4 rounded-2xl text-sm font-bold text-brand-orange bg-brand-orange/10"
                    data-tab="tab-account">👤 Conta</button>
                <button onclick="switchTab('tab-appearance')"
                    class="tab-btn w-full text-left p-4 rounded-2xl text-sm font-bold text-slate-500"
                    data-tab="tab-appearance">🎨 Tema</button>
                <button onclick="switchTab('tab-help')"
                    class="tab-btn w-full text-left p-4 rounded-2xl text-sm font-bold text-slate-500"
                    data-tab="tab-help">❓ Ajuda</button>
                <button onclick="closeSettings()"
                    class="w-full text-center p-4 mt-8 text-xs font-bold text-slate-400 border border-dashed rounded-2xl">FECHAR</button>
            </div>

            <div class="flex-1 p-10 overflow-y-auto custom-scrollbar">
                <div id="tab-account" class="tab-content space-y-6">
                    <h4 class="text-2xl font-black italic tracking-tighter">SEGURANÇA</h4>
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="change_password" value="1">
                        <div class="space-y-2">
                            <label class="text-[10px] font-bold text-slate-400 uppercase tracking-widest ml-1">Mudar
                                Senha</label>
                            <input type="password" name="new_password" placeholder="Nova senha segura" required
                                class="w-full bg-slate-100 dark:bg-white/5 p-5 rounded-[1.5rem] outline-none focus:ring-2 focus:ring-brand-orange">
                        </div>
                        <button type="submit"
                            class="w-full bg-brand-orange text-white py-5 rounded-[1.5rem] font-black italic tracking-widest uppercase text-xs">Atualizar
                            Credenciais</button>
                    </form>
                </div>

                <div id="tab-appearance" class="tab-content hidden space-y-6">
                    <h4 class="text-2xl font-black italic tracking-tighter">APARÊNCIA</h4>
                    <div class="flex items-center justify-between p-6 bg-slate-100 dark:bg-white/5 rounded-[2rem]">
                        <div>
                            <p class="font-black text-lg">Modo Escuro</p>
                            <p class="text-xs text-slate-400">Proteja seus olhos durante a noite.</p>
                        </div>
                        <button onclick="toggleDarkMode()" id="dark-toggle-btn"
                            class="bg-slate-300 dark:bg-brand-orange p-1.5 rounded-full w-16 transition-all">
                            <div
                                class="w-7 h-7 bg-white rounded-full shadow-lg transform dark:translate-x-7 transition-transform">
                            </div>
                        </button>
                    </div>
                </div>

                <div id="tab-help" class="tab-content hidden space-y-8">
                    <h4 class="text-2xl font-black italic tracking-tighter">COMO FUNCIONA</h4>
                    <div class="space-y-6">
                        <div class="space-y-2">
                            <p class="font-bold text-brand-orange">📍 O Poder do Contexto</p>
                            <p class="text-sm text-slate-500">Só veja tarefas que você pode fazer agora. Se está na rua,
                                oculte o que é de computador.</p>
                        </div>
                        <?php renderEnergyLegend(); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL UPGRADE (SaaS Paywall) -->
    <div id="modal-upgrade"
        class="hidden fixed inset-0 bg-brand-black/90 backdrop-blur-xl z-[100] flex items-center justify-center p-4">
        <div
            class="bg-white dark:bg-brand-black rounded-[3.5rem] p-10 md:p-14 w-full max-w-lg shadow-2xl text-center border border-white/10 relative">
            <button onclick="document.getElementById('modal-upgrade').classList.add('hidden')"
                class="absolute top-8 right-8 text-slate-300 hover:text-brand-orange text-2xl">&times;</button>
            <div class="text-7xl mb-8">🚀</div>
            <h3 class="text-4xl font-black text-brand-orange mb-4 italic tracking-tighter uppercase">Potencialize sua
                Execução</h3>
            <p class="text-slate-500 dark:text-slate-400 mb-10 leading-relaxed font-medium">Libere tarefas ilimitadas,
                todos os contextos, relatórios avançados e a IA de produtividade.</p>
            <div class="space-y-4 mb-12 text-left bg-slate-50 dark:bg-white/5 p-8 rounded-[2.5rem]">
                <div class="flex items-center gap-4 text-sm font-bold italic"><span
                        class="bg-brand-orange text-white p-1 rounded-lg text-[8px]">✓</span> TAREFAS ILIMITADAS</div>
                <div class="flex items-center gap-4 text-sm font-bold italic"><span
                        class="bg-brand-orange text-white p-1 rounded-lg text-[8px]">✓</span> CONTEXTOS ILIMITADOS</div>
                <div class="flex items-center gap-4 text-sm font-bold italic"><span
                        class="bg-brand-orange text-white p-1 rounded-lg text-[8px]">✓</span> MODO FOCO INTELIGENTE
                </div>
            </div>
            <a href="checkout.php"
                class="block w-full bg-brand-orange text-white py-6 rounded-[2rem] font-black text-xl shadow-2xl shadow-brand-orange/40 hover:scale-105 transition-all italic uppercase">ASSINAR
                AGORA</a>
        </div>
    </div>

    <!-- MODAL ADICIONAR TAREFA -->
    <div id="modal-task"
        class="hidden fixed inset-0 bg-brand-black/60 backdrop-blur-sm flex items-center justify-center p-4 z-50 overflow-y-auto">
        <div
            class="bg-white dark:bg-brand-black rounded-[3rem] p-10 w-full max-w-xl shadow-2xl my-auto border border-white/5">
            <h3 class="text-2xl font-black mb-8 text-brand-orange italic uppercase tracking-tighter">Nova Tarefa
                Inteligente</h3>
            <form method="POST" class="space-y-6">
                <input type="hidden" name="add_task" value="1">
                <input type="text" name="title" placeholder="O que vamos realizar?" required
                    class="w-full bg-slate-100 dark:bg-white/5 rounded-2xl p-5 text-lg outline-none focus:ring-2 focus:ring-brand-orange dark:text-white font-bold">
                <textarea name="description" placeholder="Notas e Detalhes (Markdown)..." rows="3"
                    class="w-full bg-slate-100 dark:bg-white/5 rounded-2xl p-5 text-sm outline-none resize-none dark:text-white custom-scrollbar"></textarea>
                <div class="grid grid-cols-2 gap-4">
                    <select name="context_id"
                        class="bg-slate-100 dark:bg-white/5 rounded-2xl p-4 text-sm outline-none dark:text-white appearance-none border-r-[16px] border-transparent">
                        <option value="">🏠 Geral</option>
                        <?php foreach ($contexts as $ctx): ?>
                            <option value="<?= $ctx['id'] ?>" <?= $selectedContextId == $ctx['id'] ? 'selected' : '' ?>>
                                <?= $ctx['icon'] ?>     <?= htmlspecialchars($ctx['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <select name="energy_level"
                        class="bg-slate-100 dark:bg-white/5 rounded-2xl p-4 text-sm outline-none dark:text-white appearance-none border-r-[16px] border-transparent">
                        <option value="low">🌱 Baixa Energia</option>
                        <option value="medium" selected>⚡ Média</option>
                        <option value="high">🧠 Alta</option>
                    </select>
                </div>
                <div class="space-y-2">
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Prazo de
                        Entrega</label>
                    <input type="date" name="due_date"
                        class="w-full bg-slate-100 dark:bg-white/5 rounded-2xl p-4 text-sm dark:text-white outline-none">
                </div>
                <button type="submit"
                    class="w-full bg-brand-orange text-white py-6 rounded-[2rem] font-black shadow-2xl shadow-brand-orange/30 italic uppercase">Agendar
                    Execução</button>
                <button type="button" onclick="document.getElementById('modal-task').classList.add('hidden')"
                    class="w-full text-slate-400 text-xs font-bold">Cancelar</button>
            </form>
        </div>
    </div>

    <!-- MODAL EDITAR TAREFA -->
    <div id="modal-edit-task"
        class="hidden fixed inset-0 bg-brand-black/60 backdrop-blur-sm flex items-center justify-center p-4 z-50 overflow-y-auto">
        <div
            class="bg-white dark:bg-brand-black rounded-[3rem] p-10 w-full max-w-xl shadow-2xl my-auto border border-white/5">
            <h3 class="text-2xl font-black mb-8 text-brand-orange italic uppercase tracking-tighter">Editar Plano</h3>
            <form method="POST" class="space-y-6">
                <input type="hidden" name="edit_task" value="1"><input type="hidden" name="task_id" id="edit-task-id">
                <input type="text" name="title" id="edit-task-title" required
                    class="w-full bg-slate-100 dark:bg-white/5 rounded-2xl p-5 text-lg outline-none font-bold dark:text-white">
                <textarea name="description" id="edit-task-desc" rows="4"
                    class="w-full bg-slate-100 dark:bg-white/5 rounded-2xl p-5 text-sm outline-none resize-none dark:text-white custom-scrollbar"></textarea>
                <div class="grid grid-cols-2 gap-4">
                    <select name="context_id" id="edit-task-context"
                        class="w-full bg-slate-100 dark:bg-white/5 rounded-2xl p-4 text-sm dark:text-white appearance-none border-r-[16px] border-transparent">
                        <option value="">🏠 Geral</option>
                        <?php foreach ($contexts as $ctx): ?>
                            <option value="<?= $ctx['id'] ?>"><?= $ctx['icon'] ?>     <?= htmlspecialchars($ctx['name']) ?>
                            </option><?php endforeach; ?>
                    </select>
                    <select name="energy_level" id="edit-task-energy"
                        class="w-full bg-slate-100 dark:bg-white/5 rounded-2xl p-4 text-sm dark:text-white appearance-none border-r-[16px] border-transparent">
                        <option value="low">🌱 Baixa</option>
                        <option value="medium">⚡ Média</option>
                        <option value="high">🧠 Alta</option>
                    </select>
                </div>
                <input type="date" name="due_date" id="edit-task-date"
                    class="w-full bg-slate-100 dark:bg-white/5 rounded-2xl p-4 text-sm dark:text-white outline-none">
                <div class="flex gap-4 pt-4">
                    <button type="button" onclick="document.getElementById('modal-edit-task').classList.add('hidden')"
                        class="flex-1 bg-slate-100 dark:bg-white/10 py-5 rounded-[2rem] font-bold text-slate-400">Voltar</button>
                    <button type="submit"
                        class="flex-[2] bg-brand-orange text-white py-5 rounded-[2rem] font-black italic uppercase shadow-xl">Salvar
                        Alterações</button>
                </div>
            </form>
        </div>
    </div>

    <!-- SCRIPTS -->
    <script>
        // TEMA
        if (localStorage.getItem('darkMode') === 'enabled' || (!localStorage.getItem('darkMode') && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        }
        function toggleDarkMode() {
            const isDark = document.documentElement.classList.toggle('dark');
            localStorage.setItem('darkMode', isDark ? 'enabled' : 'disabled');
        }

        // MARKDOWN CONFIG
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

        // INTERFACE
        function toggleMenu() {
            document.getElementById('sidebar').classList.toggle('active');
            document.getElementById('overlay').classList.toggle('hidden');
        }
        function toggleDesc(id, event) {
            if (event.target.tagName === 'A' || event.target.tagName === 'INPUT') return;
            document.getElementById('desc-' + id).classList.toggle('desc-truncate');
            document.getElementById('desc-' + id).classList.toggle('desc-full');
        }

        // CONFIGS TABS
        function openSettings() { document.getElementById('modal-settings').classList.remove('hidden'); }
        function closeSettings() { document.getElementById('modal-settings').classList.add('hidden'); }
        function switchTab(tabId) {
            document.querySelectorAll('.tab-content').forEach(c => c.classList.add('hidden'));
            document.getElementById(tabId).classList.remove('hidden');
            document.querySelectorAll('.tab-btn').forEach(b => {
                b.classList.remove('bg-brand-orange/10', 'text-brand-orange');
                b.classList.add('text-slate-500');
            });
            const activeBtn = document.querySelector(`[data-tab="${tabId}"]`);
            activeBtn.classList.remove('text-slate-500');
            activeBtn.classList.add('bg-brand-orange/10', 'text-brand-orange');
        }

        // EDIÇÃO BLINDADA
        function openEditModal(id, contextId, energy, dueDate) {
            const title = document.getElementById('task-title-' + id).textContent.trim();
            const rawElement = document.getElementById('raw-desc-' + id);
            const desc = rawElement ? rawElement.textContent.trim() : '';

            document.getElementById('modal-edit-task').classList.remove('hidden');
            document.getElementById('edit-task-id').value = id;
            document.getElementById('edit-task-title').value = title;
            document.getElementById('edit-task-desc').value = desc;
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