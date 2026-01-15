<?php
require_once __DIR__ . '/../src/Auth/Auth.php';
require_once __DIR__ . '/../src/Models/Context.php';
require_once __DIR__ . '/../src/Models/Task.php';
require_once __DIR__ . '/../src/Models/Note.php';

Auth::check();
date_default_timezone_set('America/Recife');

$userId = $_SESSION['user_id'];
$userName = $_SESSION['user_name'];
$userPlan = $_SESSION['user_plan'] ?? 'free';
$isPremium = ($userPlan === 'premium');

// --- PROCESSAMENTO DE AÇÕES (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Contextos
    if (isset($_POST['add_context']))
        Context::create($userId, $_POST['context_name'], $_POST['context_icon'] ?: '📍');
    if (isset($_POST['delete_context']))
        Context::delete($userId, $_POST['context_id']);

    // Tarefas
    if (isset($_POST['add_task']))
        Task::create($userId, $_POST);
    if (isset($_POST['edit_task']))
        Task::update($userId, $_POST['task_id'], $_POST);
    if (isset($_POST['delete_task']))
        Task::softDelete($userId, $_POST['task_id']);
    if (isset($_POST['toggle_task']))
        Task::toggleStatus($userId, $_POST['task_id']);

    // Notas (Lembretes Fixos)
    if (isset($_POST['add_note']) && !empty($_POST['note_content']))
        Note::save($userId, $_POST['note_content']);
    if (isset($_POST['delete_note']))
        Note::delete($userId, $_POST['note_id']);

    // Senha
    if (isset($_POST['change_password']) && !empty($_POST['new_password']))
        Auth::updatePassword($userId, $_POST['new_password']);

    header("Location: index.php" . (isset($_GET['context']) ? "?context=" . $_GET['context'] : ""));
    exit;
}

// --- BUSCA DE DADOS ---
$contexts = Context::getAllByUser($userId);
$selectedContextId = $_GET['context'] ?? null;
$tasks = Task::getByUser($userId, $selectedContextId);
$notes = Note::getByUser($userId);

// Limites SaaS
$db = getConnection();
$stmtCount = $db->prepare("SELECT COUNT(*) FROM todo_tasks WHERE user_id = ? AND status = 'pending' AND deleted_at IS NULL");
$stmtCount->execute([$userId]);
$totalPendingTasks = $stmtCount->fetchColumn();
$taskLimitReached = (!$isPremium && $totalPendingTasks >= 20);
$contextLimitReached = (!$isPremium && count($contexts) >= 3);

// Contexto Atual para UI
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
    <div class="bg-slate-100 dark:bg-white/5 p-5 rounded-[2rem] space-y-3 border border-brand-orange/10 mt-4">
        <p class="text-[10px] font-black text-brand-orange uppercase tracking-widest mb-1">💡 Guia de Energia Samsantos</p>
        <div class="flex items-start gap-3 text-xs dark:text-slate-300">
            <span class="text-[#73937e]">🌱</span>
            <p><b>Baixa:</b> Tarefas rápidas/mecânicas.</p>
        </div>
        <div class="flex items-start gap-3 text-xs dark:text-slate-300">
            <span class="text-[#254e70]">⚡</span>
            <p><b>Média:</b> Exige atenção, mas não exaustão.</p>
        </div>
        <div class="flex items-start gap-3 text-xs dark:text-slate-300">
            <span class="text-[#D25B2E]">🧠</span>
            <p><b>Alta:</b> Foco total (Deep Work).</p>
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
                    colors: { brand: { white: '#F0F0F0', orange: '#D25B2E', black: '#1C1E24', green: '#73937e', blue: '#254e70' } },
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

        .custom-scrollbar::-webkit-scrollbar {
            width: 4px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #D25B2E;
            border-radius: 10px;
        }

        .drawer-closed {
            transform: translateX(100%);
        }

        @media (max-width: 768px) {
            .drawer-closed {
                transform: translateY(100%);
            }

            .drawer-open {
                transform: translateY(0);
            }

            .sidebar-mobile {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }

            .sidebar-mobile.active {
                transform: translateX(0);
            }
        }

        .drawer-open {
            transform: translateX(0);
        }
    </style>
</head>

<body
    class="bg-brand-white dark:bg-[#111216] text-brand-black dark:text-brand-white flex h-screen overflow-hidden transition-colors duration-500">

    <div id="overlay" onclick="closeAll()" class="fixed inset-0 bg-brand-black/70 backdrop-blur-md z-30 hidden"></div>

    <!-- SIDEBAR -->
    <aside id="sidebar"
        class="sidebar-mobile fixed md:relative z-40 w-72 bg-white dark:bg-brand-black border-r border-slate-200 dark:border-slate-800 h-full flex flex-col shadow-sm md:translate-x-0">
        <div class="p-8 flex flex-col gap-2">
            <div class="flex items-center gap-3 text-brand-orange mb-2">
                <div
                    class="bg-brand-orange p-2 rounded-xl text-white font-bold italic shadow-lg shadow-brand-orange/30">
                    S</div>
                <h1 class="text-xl font-black uppercase italic tracking-tighter">Smart Todo</h1>
            </div>
            <?php if ($isPremium): ?>
                <span
                    class="text-[9px] bg-brand-orange/10 text-brand-orange px-2 py-0.5 rounded-full font-black uppercase tracking-widest border border-brand-orange/20 w-fit">Premium</span>
            <?php else: ?>
                <button onclick="document.getElementById('modal-upgrade').classList.remove('hidden')"
                    class="text-[9px] bg-slate-100 dark:bg-white/5 text-slate-500 px-2 py-0.5 rounded-full font-black uppercase hover:bg-brand-orange hover:text-white transition-all w-fit italic">Free
                    • Upgrade</button>
            <?php endif; ?>
        </div>

        <nav class="flex-1 overflow-y-auto px-4 space-y-1 custom-scrollbar">
            <a href="index.php"
                class="flex items-center gap-3 p-3 rounded-2xl transition-all <?= !$selectedContextId ? 'bg-brand-orange text-white font-bold shadow-lg' : 'hover:bg-slate-100 dark:hover:bg-white/5' ?>">
                <span class="text-lg">🏠</span> Todas as Tarefas
            </a>
            <button onclick="toggleNotes()"
                class="flex items-center gap-3 p-3 w-full rounded-2xl hover:bg-slate-100 dark:hover:bg-white/5 transition-all text-left">
                <span class="text-lg">📝</span> Lembretes Fixos
            </button>
            <div
                class="pt-6 px-4 py-2 text-[10px] font-bold text-slate-400 uppercase tracking-widest flex justify-between">
                <span>Contextos</span>
                <span class="opacity-50"><?= count($contexts) ?>/<?= $isPremium ? '∞' : '3' ?></span>
            </div>
            <?php foreach ($contexts as $ctx): ?>
                <div
                    class="group flex items-center justify-between rounded-2xl transition-all <?= $selectedContextId == $ctx['id'] ? 'bg-brand-orange/10 text-brand-orange font-bold' : 'hover:bg-slate-100 dark:hover:bg-white/5 text-slate-500' ?>">
                    <a href="?context=<?= $ctx['id'] ?>" class="flex-1 p-3 flex items-center gap-3 truncate">
                        <span><?= $ctx['icon'] ?></span> <?= htmlspecialchars($ctx['name']) ?>
                    </a>
                </div>
            <?php endforeach; ?>
        </nav>

        <div class="p-6 border-t border-slate-100 dark:border-slate-800 space-y-2">
            <button onclick="openSettings()"
                class="flex items-center gap-3 p-4 text-sm font-bold w-full rounded-2xl bg-slate-50 dark:bg-white/5 hover:bg-brand-orange/10 hover:text-brand-orange transition-all uppercase italic text-xs tracking-widest">⚙️
                Configurações</button>
            <a href="logout.php"
                class="block p-4 text-[10px] font-black text-red-400 uppercase tracking-[0.3em] text-center opacity-60 hover:opacity-100 transition-opacity">Sair
                do Sistema</a>
        </div>
    </aside>

    <!-- MAIN -->
    <main class="flex-1 flex flex-col min-w-0 relative">
        <header
            class="h-24 bg-white/80 dark:bg-brand-black/80 backdrop-blur-md border-b border-slate-200 dark:border-slate-800 flex items-center justify-between px-6 md:px-10 shrink-0 z-10">
            <div class="flex items-center gap-4">
                <button onclick="toggleMenu()" class="md:hidden p-3 bg-brand-orange text-white rounded-2xl shadow-lg">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path d="M4 6h16M4 12h16M4 18h16"></path>
                    </svg>
                </button>
                <div>
                    <h2 class="text-xl md:text-3xl font-black italic tracking-tighter uppercase">
                        <?= htmlspecialchars($currentContextName) ?></h2>
                    <p class="text-[10px] text-slate-400 font-bold uppercase tracking-[0.2em] mt-1">
                        <?= $totalPendingTasks ?> Ativas / <?= $isPremium ? '∞' : '20' ?></p>
                </div>
            </div>
            <div class="hidden md:flex items-center gap-4">
                <a href="focus.php?context=<?= $selectedContextId ?>"
                    class="bg-brand-orange/10 text-brand-orange px-8 py-4 rounded-[1.5rem] text-sm font-black border border-brand-orange/20 hover:bg-brand-orange hover:text-white transition-all italic uppercase tracking-tighter">⚡
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
                    class="group bg-white dark:bg-brand-black p-6 rounded-[2rem] border <?= $overdue ? 'border-red-500/50 bg-red-500/5' : 'border-slate-200 dark:border-slate-800 shadow-sm' ?> flex items-start justify-between hover:border-brand-orange transition-all task-appear">
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
                                <?= htmlspecialchars($task['title']) ?></h3>
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
                                        class="px-2.5 py-1 rounded-lg <?= $overdue ? 'bg-red-500 text-white' : 'bg-brand-blue/10 text-brand-blue' ?>">📅
                                        <?= date('d/m/Y', strtotime($task['due_date'])) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-1 md:opacity-0 group-hover:opacity-100 transition-all ml-4">
                        <button
                            onclick="openEditModal(<?= $task['id'] ?>, '<?= $task['context_id'] ?>', '<?= $task['energy_level'] ?>', '<?= $task['due_date'] ?>')"
                            class="p-3 text-slate-400 hover:text-brand-orange">✏️</button>
                        <form method="POST" onsubmit="return confirm('Excluir?')"><input type="hidden" name="delete_task"
                                value="1"><input type="hidden" name="task_id" value="<?= $task['id'] ?>"><button
                                type="submit" class="p-3 text-slate-400 hover:text-red-500">🗑️</button></form>
                    </div>
                </div>
            <?php endforeach; ?>
        </section>

        <!-- TAB BAR MOBILE -->
        <div
            class="md:hidden fixed bottom-0 left-0 right-0 p-4 bg-white/90 dark:bg-brand-black/90 backdrop-blur-xl border-t border-slate-200 dark:border-slate-800 flex items-center justify-around z-40 shadow-2xl">
            <a href="focus.php?context=<?= $selectedContextId ?>"
                class="flex flex-col items-center gap-1 text-brand-orange">
                <span class="text-xl">⚡</span><span class="text-[9px] font-bold uppercase tracking-tighter">Foco</span>
            </a>
            <button onclick="toggleNotes()" class="flex flex-col items-center gap-1 text-slate-400">
                <span class="text-xl">📝</span><span
                    class="text-[9px] font-bold uppercase tracking-tighter">Notas</span>
            </button>
            <button
                onclick="<?= $taskLimitReached ? "document.getElementById('modal-upgrade').classList.remove('hidden')" : "document.getElementById('modal-task').classList.remove('hidden')" ?>"
                class="bg-brand-orange text-white p-4 rounded-2xl shadow-lg shadow-brand-orange/40 -mt-8 border-4 border-brand-white dark:border-[#111216]">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
            </button>
            <a href="history.php" class="flex flex-col items-center gap-1 text-slate-400">
                <span class="text-xl">📊</span><span
                    class="text-[9px] font-bold uppercase tracking-tighter">Insights</span>
            </a>
            <button onclick="openSettings()" class="flex flex-col items-center gap-1 text-slate-400">
                <span class="text-xl">⚙️</span><span
                    class="text-[9px] font-bold uppercase tracking-tighter">Conta</span>
            </button>
        </div>
    </main>

    <!-- DRAWER: LEMBRETES FIXOS -->
    <div id="notes-drawer"
        class="fixed inset-y-0 right-0 md:w-96 w-full bg-white dark:bg-brand-black z-[100] shadow-2xl transition-transform duration-500 drawer-closed flex flex-col border-l border-slate-200 dark:border-slate-800">
        <div class="p-8 border-b border-slate-100 dark:border-slate-800 flex justify-between items-center shrink-0">
            <h3 class="text-xl font-black italic tracking-tighter uppercase text-brand-orange">Lembretes Fixos</h3>
            <button onclick="toggleNotes()" class="text-slate-400 text-3xl">&times;</button>
        </div>
        <div class="flex-1 overflow-y-auto p-6 space-y-4 custom-scrollbar">
            <?php foreach ($notes as $note): ?>
                <div
                    class="bg-slate-50 dark:bg-white/5 p-5 rounded-[2rem] relative group border border-slate-100 dark:border-white/5">
                    <form method="POST" class="absolute top-4 right-4 opacity-0 group-hover:opacity-100 transition-all">
                        <input type="hidden" name="delete_note" value="1"><input type="hidden" name="note_id"
                            value="<?= $note['id'] ?>">
                        <button type="submit" class="text-red-400 hover:text-red-600">✕</button>
                    </form>
                    <p class="text-sm dark:text-slate-300 leading-relaxed"><?= nl2br(htmlspecialchars($note['content'])) ?>
                    </p>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="p-6 border-t border-slate-100 dark:border-slate-800">
            <form method="POST" class="space-y-3">
                <input type="hidden" name="add_note" value="1">
                <textarea name="note_content" placeholder="Anotação rápida..." required rows="3"
                    class="w-full bg-slate-100 dark:bg-white/5 p-5 rounded-2xl text-sm outline-none focus:ring-2 focus:ring-brand-orange dark:text-white resize-none"></textarea>
                <button type="submit"
                    class="w-full bg-brand-orange text-white py-4 rounded-2xl font-black italic uppercase text-xs tracking-widest shadow-xl shadow-brand-orange/20">Fixar
                    Nota</button>
            </form>
        </div>
    </div>

    <!-- MODAL SETTINGS -->
    <div id="modal-settings"
        class="hidden fixed inset-0 bg-brand-black/80 backdrop-blur-sm z-[60] flex items-center justify-center p-4">
        <div
            class="bg-white dark:bg-brand-black w-full max-w-3xl rounded-[3rem] shadow-2xl overflow-hidden flex flex-col md:flex-row h-[85vh] md:h-auto border border-white/10">
            <div
                class="w-full md:w-56 bg-slate-50 dark:bg-white/5 p-8 space-y-2 shrink-0 border-r border-slate-100 dark:border-slate-800">
                <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-6">Configurações</h3>
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
                    class="w-full text-center p-4 mt-8 text-[10px] font-black text-slate-400 uppercase tracking-widest border border-dashed rounded-2xl">Fechar</button>
            </div>
            <div class="flex-1 p-10 overflow-y-auto custom-scrollbar">
                <div id="tab-account" class="tab-content space-y-6">
                    <h4 class="text-2xl font-black italic italic tracking-tighter text-brand-orange">SEGURANÇA</h4>
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="change_password" value="1">
                        <input type="password" name="new_password" placeholder="Nova senha segura" required
                            class="w-full bg-slate-100 dark:bg-white/5 p-5 rounded-[1.5rem] outline-none">
                        <button type="submit"
                            class="w-full bg-brand-orange text-white py-5 rounded-[1.5rem] font-black italic uppercase text-xs tracking-widest">Atualizar
                            Senha</button>
                    </form>
                </div>
                <div id="tab-appearance" class="tab-content hidden space-y-6">
                    <h4 class="text-2xl font-black italic tracking-tighter text-brand-orange">APARÊNCIA</h4>
                    <div class="flex items-center justify-between p-6 bg-slate-100 dark:bg-white/5 rounded-[2rem]">
                        <div>
                            <p class="font-black text-lg">Modo Escuro</p>
                            <p class="text-xs text-slate-400 italic uppercase tracking-widest">Cuidado Visual</p>
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
                    <h4 class="text-2xl font-black italic tracking-tighter text-brand-orange">GUIA RÁPIDO</h4>
                    <div class="space-y-6 text-sm text-slate-500">
                        <p><b>📍 Contextos:</b> Filtre o que importa agora.</p>
                        <p><b>🧠 Energia:</b> Combine tarefas com seu cansaço mental.</p>
                        <p><b>📝 Notas:</b> Use a aba lateral para o que não é tarefa.</p>
                        <?php renderEnergyLegend(); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL UPGRADE -->
    <div id="modal-upgrade"
        class="hidden fixed inset-0 bg-brand-black/95 backdrop-blur-xl z-[100] flex items-center justify-center p-4">
        <div
            class="bg-white dark:bg-brand-black rounded-[4rem] p-10 md:p-16 w-full max-w-lg shadow-2xl text-center border border-white/10 relative">
            <button onclick="document.getElementById('modal-upgrade').classList.add('hidden')"
                class="absolute top-10 right-10 text-slate-400 text-3xl">&times;</button>
            <div class="text-7xl mb-10 animate-bounce">🚀</div>
            <h3 class="text-4xl font-black text-brand-orange mb-4 italic tracking-tighter uppercase">Evolua sua Gestão
            </h3>
            <p class="text-slate-500 dark:text-slate-400 mb-10 leading-relaxed font-bold">Tarefas ilimitadas, relatórios
                avançados e IA de produtividade.</p>
            <a href="checkout.php"
                class="block w-full bg-brand-orange text-white py-6 rounded-[2rem] font-black text-xl shadow-xl shadow-brand-orange/40 hover:scale-105 transition-all italic uppercase italic">ASSINAR
                PREMIUM</a>
        </div>
    </div>

    <!-- MODAL ADICIONAR -->
    <div id="modal-task"
        class="hidden fixed inset-0 bg-brand-black/60 backdrop-blur-sm flex items-center justify-center p-4 z-50 overflow-y-auto">
        <div class="bg-white dark:bg-brand-black rounded-[3rem] p-8 md:p-12 w-full max-w-xl shadow-2xl my-auto">
            <h3 class="text-2xl font-black mb-8 text-brand-orange italic uppercase tracking-tighter">Nova Tarefa</h3>
            <form method="POST" class="space-y-6">
                <input type="hidden" name="add_task" value="1">
                <input type="text" name="title" placeholder="O que vamos fazer?" required
                    class="w-full bg-slate-100 dark:bg-white/5 rounded-2xl p-5 text-lg outline-none focus:ring-2 focus:ring-brand-orange dark:text-white font-bold">
                <textarea name="description" placeholder="Notas (Markdown)..." rows="3"
                    class="w-full bg-slate-100 dark:bg-white/5 rounded-2xl p-5 text-sm outline-none resize-none dark:text-white"></textarea>
                <div class="grid grid-cols-2 gap-4">
                    <select name="context_id"
                        class="bg-slate-100 dark:bg-white/5 rounded-2xl p-4 text-sm outline-none dark:text-white">
                        <option value="">🏠 Geral</option><?php foreach ($contexts as $ctx): ?>
                            <option value="<?= $ctx['id'] ?>" <?= $selectedContextId == $ctx['id'] ? 'selected' : '' ?>>
                                <?= $ctx['icon'] ?>     <?= htmlspecialchars($ctx['name']) ?></option><?php endforeach; ?>
                    </select>
                    <select name="energy_level"
                        class="bg-slate-100 dark:bg-white/5 rounded-2xl p-4 text-sm outline-none dark:text-white">
                        <option value="low">🌱 Baixa</option>
                        <option value="medium" selected>⚡ Média</option>
                        <option value="high">🧠 Alta</option>
                    </select>
                </div>
                <input type="date" name="due_date"
                    class="w-full bg-slate-100 dark:bg-white/5 rounded-2xl p-4 text-sm dark:text-white outline-none">
                <button type="submit"
                    class="w-full bg-brand-orange text-white py-6 rounded-[2rem] font-black italic shadow-xl">Agendar
                    Execução</button>
            </form>
        </div>
    </div>

    <!-- MODAL EDITAR -->
    <div id="modal-edit-task"
        class="hidden fixed inset-0 bg-brand-black/60 backdrop-blur-sm flex items-center justify-center p-4 z-50 overflow-y-auto">
        <div class="bg-white dark:bg-brand-black rounded-[3rem] p-8 md:p-12 w-full max-w-xl shadow-2xl my-auto">
            <h3 class="text-2xl font-black mb-8 text-brand-orange italic uppercase tracking-tighter">Editar Plano</h3>
            <form method="POST" class="space-y-6">
                <input type="hidden" name="edit_task" value="1"><input type="hidden" name="task_id" id="edit-task-id">
                <input type="text" name="title" id="edit-task-title" required
                    class="w-full bg-slate-100 dark:bg-white/5 rounded-2xl p-5 text-lg outline-none font-bold dark:text-white">
                <textarea name="description" id="edit-task-desc" rows="4"
                    class="w-full bg-slate-100 dark:bg-white/5 rounded-2xl p-5 text-sm outline-none resize-none dark:text-white"></textarea>
                <div class="grid grid-cols-2 gap-4">
                    <select name="context_id" id="edit-task-context"
                        class="bg-slate-100 dark:bg-white/5 rounded-2xl p-4 text-sm dark:text-white">
                        <option value="">🏠 Geral</option><?php foreach ($contexts as $ctx): ?>
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
                    class="w-full bg-slate-100 dark:bg-white/5 rounded-2xl p-4 text-sm dark:text-white outline-none">
                <div class="flex gap-4"><button type="button"
                        onclick="document.getElementById('modal-edit-task').classList.add('hidden')"
                        class="flex-1 bg-slate-100 dark:bg-white/10 py-5 rounded-[2rem] font-bold text-slate-400">Voltar</button><button
                        type="submit"
                        class="flex-[2] bg-brand-orange text-white py-5 rounded-[2rem] font-black italic uppercase shadow-xl">Salvar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- SCRIPTS -->
    <script>
        // DARK MODE
        if (localStorage.getItem('darkMode') === 'enabled' || (!localStorage.getItem('darkMode') && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        }
        function toggleDarkMode() {
            const isDark = document.documentElement.classList.toggle('dark');
            localStorage.setItem('darkMode', isDark ? 'enabled' : 'disabled');
        }

        // MARKDOWN
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
        function toggleMenu() { document.getElementById('sidebar').classList.toggle('active'); document.getElementById('overlay').classList.toggle('hidden'); }
        function toggleNotes() { document.getElementById('notes-drawer').classList.toggle('drawer-closed'); document.getElementById('notes-drawer').classList.toggle('drawer-open'); document.getElementById('overlay').classList.toggle('hidden'); }
        function closeAll() { ['notes-drawer', 'sidebar', 'overlay'].forEach(id => { document.getElementById(id).classList.remove('drawer-open', 'active'); if (id === 'notes-drawer') document.getElementById(id).classList.add('drawer-closed'); if (id === 'overlay') document.getElementById(id).classList.add('hidden'); }); }
        function toggleDesc(id, event) { if (event.target.tagName === 'A' || event.target.tagName === 'INPUT') return; const el = document.getElementById('desc-' + id); el.classList.toggle('desc-truncate'); el.classList.toggle('desc-full'); }

        // CONFIGS TABS
        function openSettings() { document.getElementById('modal-settings').classList.remove('hidden'); }
        function closeSettings() { document.getElementById('modal-settings').classList.add('hidden'); }
        function switchTab(tabId) {
            document.querySelectorAll('.tab-content').forEach(c => c.classList.add('hidden'));
            document.getElementById(tabId).classList.remove('hidden');
            document.querySelectorAll('.tab-btn').forEach(b => { b.classList.remove('bg-brand-orange/10', 'text-brand-orange'); b.classList.add('text-slate-500'); });
            const activeBtn = document.querySelector(`[data-tab="${tabId}"]`);
            activeBtn.classList.remove('text-slate-500'); activeBtn.classList.add('bg-brand-orange/10', 'text-brand-orange');
        }

        // EDIÇÃO
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