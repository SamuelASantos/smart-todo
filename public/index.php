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

// --- PROCESSAMENTO DE AÇÕES ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_context']))
        Context::create($userId, $_POST['context_name'], $_POST['context_icon'] ?: '📍');
    if (isset($_POST['delete_context']))
        Context::delete($userId, $_POST['context_id']);
    if (isset($_POST['add_task']))
        Task::create($userId, $_POST);
    if (isset($_POST['edit_task']))
        Task::update($userId, $_POST['task_id'], $_POST);
    if (isset($_POST['delete_task']))
        Task::softDelete($userId, $_POST['task_id']);
    if (isset($_POST['toggle_task']))
        Task::toggleStatus($userId, $_POST['task_id']);
    if (isset($_POST['add_note']) && !empty($_POST['note_content']))
        Note::save($userId, $_POST['note_content']);
    if (isset($_POST['delete_note']))
        Note::delete($userId, $_POST['note_id']);
    if (isset($_POST['change_password']) && !empty($_POST['new_password']))
        Auth::updatePassword($userId, $_POST['new_password']);

    header("Location: index.php" . (isset($_GET['context']) ? "?context=" . $_GET['context'] : ""));
    exit;
}

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
?>

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
            theme: { extend: { colors: { brand: { white: '#F0F0F0', orange: '#D25B2E', black: '#1C1E24', green: '#73937e', blue: '#254e70' } } } }
        }
    </script>
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #F8FAFC;
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

        .custom-scrollbar::-webkit-scrollbar {
            width: 4px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #D25B2E;
            border-radius: 10px;
        }

        /* Ajuste do Overlay: Somente visível no mobile quando ativo */
        #overlay {
            display: none;
        }

        @media (max-width: 768px) {
            #overlay.active {
                display: block;
            }

            .sidebar-mobile {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }

            .sidebar-mobile.active {
                transform: translateX(0);
            }
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
        }

        .drawer-open {
            transform: translateX(0);
        }
    </style>
</head>

<body
    class="bg-brand-white dark:bg-[#111216] text-brand-black dark:text-brand-white flex h-screen overflow-hidden transition-colors duration-500">

    <!-- Overlay (Corrigido para não aparecer no Desktop) -->
    <div id="overlay" onclick="closeAll()" class="fixed inset-0 bg-brand-black/70 backdrop-blur-md z-30 transition-all">
    </div>

    <!-- SIDEBAR -->
    <aside id="sidebar"
        class="sidebar-mobile fixed md:relative z-40 w-72 bg-white dark:bg-brand-black border-r border-slate-200 dark:border-slate-800 h-full flex flex-col shadow-sm md:translate-x-0 shrink-0">
        <div class="p-8 flex flex-col gap-2">
            <div class="flex items-center gap-3 text-brand-orange mb-2">
                <div
                    class="bg-brand-orange p-2 rounded-xl text-white font-bold italic shadow-lg shadow-brand-orange/30 leading-none">
                    S</div>
                <h1 class="text-xl font-black uppercase italic tracking-tighter">Smart Todo</h1>
            </div>
            <div class="flex items-center gap-2">
                <?php if ($isPremium): ?>
                    <span
                        class="text-[9px] bg-brand-orange/10 text-brand-orange px-2 py-0.5 rounded-full font-black uppercase tracking-widest border border-brand-orange/20">Premium</span>
                <?php else: ?>
                    <button onclick="document.getElementById('modal-upgrade').classList.remove('hidden')"
                        class="text-[9px] bg-slate-100 dark:bg-white/5 text-slate-500 px-2 py-0.5 rounded-full font-black uppercase tracking-widest hover:bg-brand-orange hover:text-white transition-all italic">Free
                        • Upgrade</button>
                <?php endif; ?>
            </div>
        </div>

        <nav class="flex-1 overflow-y-auto px-4 space-y-1 custom-scrollbar">
            <a href="index.php"
                class="flex items-center gap-3 p-3 rounded-2xl transition-all <?= !$selectedContextId ? 'bg-brand-orange text-white font-bold shadow-lg shadow-brand-orange/30' : 'hover:bg-slate-100 dark:hover:bg-white/5' ?>">
                <span class="text-lg">🏠</span> Todas as Tarefas
            </a>
            <button onclick="toggleNotes()"
                class="flex items-center gap-3 p-3 w-full rounded-2xl hover:bg-slate-100 dark:hover:bg-white/5 transition-all text-left">
                <span class="text-lg">📝</span> Lembretes Fixos
            </button>
            <div class="pt-6 px-4 py-2 text-[10px] font-bold text-slate-400 uppercase tracking-widest">Contextos</div>
            <?php foreach ($contexts as $ctx): ?>
                <a href="?context=<?= $ctx['id'] ?>"
                    class="group flex items-center justify-between p-3 rounded-2xl transition-all <?= $selectedContextId == $ctx['id'] ? 'bg-brand-orange/10 text-brand-orange font-bold' : 'hover:bg-slate-100 dark:hover:bg-white/5 text-slate-500' ?>">
                    <span class="flex items-center gap-3 truncate"><span><?= $ctx['icon'] ?></span>
                        <?= htmlspecialchars($ctx['name']) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="p-6 border-t border-slate-100 dark:border-slate-800 space-y-2">
            <button onclick="openSettings()"
                class="flex items-center gap-3 p-4 text-sm font-bold w-full rounded-2xl bg-slate-50 dark:bg-white/5 hover:bg-brand-orange/10 hover:text-brand-orange transition-all uppercase italic text-[10px] tracking-widest">⚙️
                Configurações</button>
            <a href="logout.php"
                class="block p-4 text-[10px] font-black text-red-400 uppercase tracking-[0.3em] text-center opacity-60 hover:opacity-100">Sair</a>
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
                    <h2
                        class="text-xl md:text-3xl font-black italic tracking-tighter uppercase truncate max-w-[180px] md:max-w-none text-brand-black dark:text-white">
                        <?= htmlspecialchars($currentContextName) ?>
                    </h2>
                    <p class="text-[10px] text-slate-400 font-bold uppercase tracking-[0.2em] mt-1"><?= count($tasks) ?>
                        Ativas / <?= $isPremium ? '∞' : '20' ?></p>
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
                                    <?= htmlspecialchars($task['description']) ?>
                                </div>
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
                class="flex flex-col items-center gap-1 text-brand-orange"><span class="text-xl">⚡</span><span
                    class="text-[9px] font-bold uppercase tracking-tighter">Foco</span></a>
            <button onclick="toggleNotes()" class="flex flex-col items-center gap-1 text-slate-400"><span
                    class="text-xl">📝</span><span
                    class="text-[9px] font-bold uppercase tracking-tighter">Notas</span></button>
            <button
                onclick="<?= $taskLimitReached ? "document.getElementById('modal-upgrade').classList.remove('hidden')" : "document.getElementById('modal-task').classList.remove('hidden')" ?>"
                class="bg-brand-orange text-white p-4 rounded-2xl shadow-lg -mt-8 border-4 border-brand-white dark:border-[#111216]"><svg
                    class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path d="M12 4v16m8-8H4"></path>
                </svg></button>
            <a href="history.php" class="flex flex-col items-center gap-1 text-slate-400"><span
                    class="text-xl">📊</span><span
                    class="text-[9px] font-bold uppercase tracking-tighter">Insights</span></a>
            <button onclick="openSettings()" class="flex flex-col items-center gap-1 text-slate-400"><span
                    class="text-xl">⚙️</span><span
                    class="text-[9px] font-bold uppercase tracking-tighter">Conta</span></button>
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
                    class="w-full bg-brand-orange text-white py-4 rounded-2xl font-black italic uppercase text-xs shadow-xl shadow-brand-orange/20">Fixar
                    Nota</button>
            </form>
        </div>
    </div>

    <!-- MODAL SETTINGS (CORRIGIDO COM ROLAGEM) -->
    <div id="modal-settings"
        class="hidden fixed inset-0 bg-brand-black/80 backdrop-blur-sm z-[60] flex items-center justify-center p-4">
        <!-- Definimos h-[85vh] para o modal ter uma altura fixa em relação à tela -->
        <div
            class="bg-white dark:bg-brand-black w-full max-w-4xl rounded-[3rem] shadow-2xl overflow-hidden flex flex-col md:flex-row h-[85vh] border border-white/10 relative">

            <!-- Menu do Modal (Fixo na esquerda) -->
            <div
                class="w-full md:w-64 bg-slate-50 dark:bg-white/5 p-8 space-y-2 shrink-0 border-r border-slate-100 dark:border-slate-800 flex flex-col">
                <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-6 ml-2">Configurações</h3>
                <button onclick="switchTab('tab-account')"
                    class="tab-btn w-full text-left p-4 rounded-2xl text-sm font-bold text-brand-orange bg-brand-orange/10"
                    data-tab="tab-account">👤 Minha Conta</button>
                <button onclick="switchTab('tab-contexts')"
                    class="tab-btn w-full text-left p-4 rounded-2xl text-sm font-bold text-slate-500"
                    data-tab="tab-contexts">📍 Contextos</button>
                <button onclick="switchTab('tab-appearance')"
                    class="tab-btn w-full text-left p-4 rounded-2xl text-sm font-bold text-slate-500"
                    data-tab="tab-appearance">🎨 Aparência</button>
                <button onclick="switchTab('tab-help')"
                    class="tab-btn w-full text-left p-4 rounded-2xl text-sm font-bold text-slate-500"
                    data-tab="tab-help">❓ Ajuda & Guia</button>

                <div class="mt-auto pt-6">
                    <button onclick="closeSettings()"
                        class="w-full text-center p-4 text-[10px] font-black text-slate-400 border border-dashed border-slate-200 dark:border-slate-700 rounded-2xl hover:border-brand-orange hover:text-brand-orange transition-all">FECHAR
                        JANELA</button>
                </div>
            </div>

            <!-- Conteúdo das Abas (Área com Rolagem Independente) -->
            <div class="flex-1 overflow-y-auto custom-scrollbar bg-white dark:bg-brand-black">
                <div class="p-8 md:p-12">

                    <!-- Aba Conta -->
                    <div id="tab-account" class="tab-content space-y-6">
                        <h4 class="text-2xl font-black italic tracking-tighter text-brand-orange uppercase">Segurança
                        </h4>
                        <form method="POST" class="space-y-4">
                            <input type="hidden" name="change_password" value="1">
                            <div class="space-y-2">
                                <label class="text-[10px] font-bold text-slate-400 uppercase tracking-widest ml-1">Mudar
                                    Senha</label>
                                <input type="password" name="new_password" placeholder="Nova senha segura" required
                                    class="w-full bg-slate-100 dark:bg-white/5 p-5 rounded-[1.5rem] outline-none focus:ring-2 focus:ring-brand-orange dark:text-white">
                            </div>
                            <button type="submit"
                                class="w-full bg-brand-orange text-white py-5 rounded-[1.5rem] font-black italic uppercase text-xs tracking-widest shadow-lg shadow-brand-orange/20 transition-all hover:scale-[1.01]">Atualizar
                                Senha</button>
                        </form>
                    </div>

                    <!-- Aba Contextos -->
                    <div id="tab-contexts" class="tab-content hidden space-y-6">
                        <h4 class="text-2xl font-black italic tracking-tighter text-brand-orange uppercase italic">
                            Contextos</h4>
                        <?php if (!$contextLimitReached): ?>
                            <form method="POST"
                                class="bg-slate-50 dark:bg-white/5 p-6 rounded-[2rem] space-y-4 border border-brand-orange/10">
                                <input type="hidden" name="add_context" value="1">
                                <div class="grid grid-cols-4 gap-2">
                                    <input type="text" name="context_icon" placeholder="📍" value="📍"
                                        class="bg-white dark:bg-brand-black p-4 rounded-xl text-center text-lg outline-none dark:text-white border border-slate-100 dark:border-white/5">
                                    <input type="text" name="context_name" placeholder="Nome do contexto" required
                                        class="col-span-3 bg-white dark:bg-brand-black p-4 rounded-xl text-sm outline-none focus:ring-1 focus:ring-brand-orange dark:text-white border border-slate-100 dark:border-white/5">
                                </div>
                                <button type="submit"
                                    class="w-full bg-brand-orange text-white py-4 rounded-xl font-black text-xs uppercase shadow-lg italic tracking-widest">ADICIONAR</button>
                            </form>
                        <?php endif; ?>
                        <div class="space-y-2 mt-4">
                            <?php foreach ($contexts as $ctx): ?>
                                <div
                                    class="flex items-center justify-between p-4 bg-slate-50 dark:bg-white/5 rounded-2xl border border-slate-100 dark:border-slate-800">
                                    <div class="flex items-center gap-3"><span><?= $ctx['icon'] ?></span> <span
                                            class="font-bold text-sm"><?= htmlspecialchars($ctx['name']) ?></span></div>
                                    <form method="POST" onsubmit="return confirm('Excluir?')"><input type="hidden"
                                            name="delete_context" value="1"><input type="hidden" name="context_id"
                                            value="<?= $ctx['id'] ?>"><button type="submit"
                                            class="text-red-400 p-2 hover:bg-red-50 dark:hover:bg-red-500/10 rounded-lg transition-all">✕</button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Aba Tema -->
                    <div id="tab-appearance" class="tab-content hidden space-y-6">
                        <h4 class="text-2xl font-black italic tracking-tighter text-brand-orange uppercase italic">
                            Aparência</h4>
                        <div
                            class="flex items-center justify-between p-8 bg-slate-50 dark:bg-white/5 rounded-[2.5rem] border border-slate-100 dark:border-white/5">
                            <div>
                                <p class="font-black text-lg">Modo Escuro</p>
                                <p class="text-xs text-slate-400 italic uppercase tracking-widest">Conforto visual para
                                    a noite</p>
                            </div>
                            <button onclick="toggleDarkMode()"
                                class="bg-slate-300 dark:bg-brand-orange p-1.5 rounded-full w-16 transition-all shadow-inner">
                                <div
                                    class="w-7 h-7 bg-white rounded-full shadow-lg transform dark:translate-x-7 transition-transform">
                                </div>
                            </button>
                        </div>
                    </div>

                    <!-- Aba Ajuda (O Guia Extenso) -->
                    <div id="tab-help" class="tab-content hidden space-y-8 animate-task-appear">
                        <div class="border-b border-slate-100 dark:border-white/5 pb-6">
                            <h4 class="text-2xl font-black italic tracking-tighter text-brand-orange uppercase">Guia de
                                Uso</h4>
                            <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest mt-1">Como dominar
                                o seu dia com o SmartTodo</p>
                        </div>

                        <div class="space-y-10">
                            <section class="space-y-3">
                                <h5
                                    class="flex items-center gap-2 font-black text-slate-800 dark:text-white uppercase text-xs tracking-widest">
                                    <span
                                        class="bg-brand-orange/10 p-2 rounded-lg text-brand-orange text-sm italic">1.</span>
                                    📍 Lugares (Contextos)
                                </h5>
                                <p class="text-sm text-slate-500 dark:text-slate-400 leading-relaxed italic px-2">
                                    Se você está na <b>Rua</b>, não quer ver tarefas de <b>Casa</b>. Use os Contextos
                                    para filtrar sua visão e focar no que é possível realizar agora.
                                </p>
                            </section>

                            <section class="space-y-4">
                                <h5
                                    class="flex items-center gap-2 font-black text-slate-800 dark:text-white uppercase text-xs tracking-widest">
                                    <span
                                        class="bg-brand-orange/10 p-2 rounded-lg text-brand-orange text-sm italic">2.</span>
                                    🧠 Energia Biológica
                                </h5>
                                <div class="grid gap-3">
                                    <div
                                        class="bg-emerald-50 dark:bg-emerald-500/5 p-4 rounded-2xl border border-emerald-100 dark:border-emerald-500/10">
                                        <p class="text-xs text-emerald-700 dark:text-emerald-400 leading-relaxed"><b>🌱
                                                Baixa Energia:</b> Use para tarefas automáticas (ex: lavar louça,
                                            organizar mesa) quando estiver cansado.</p>
                                    </div>
                                    <div
                                        class="bg-amber-50 dark:bg-amber-500/5 p-4 rounded-2xl border border-amber-100 dark:border-amber-500/10">
                                        <p class="text-xs text-amber-700 dark:text-amber-400 leading-relaxed"><b>⚡ Média
                                                Energia:</b> Exige atenção, mas não exaustão (ex: responder e-mails,
                                            pagar contas).</p>
                                    </div>
                                    <div
                                        class="bg-rose-50 dark:bg-rose-500/5 p-4 rounded-2xl border border-rose-100 dark:border-rose-500/10">
                                        <p class="text-xs text-rose-700 dark:text-rose-400 leading-relaxed"><b>🧠 Alta
                                                Concentração:</b> Suas tarefas de "Deep Work" (ex: programar, estudar).
                                            Faça-as quando estiver descansado.</p>
                                    </div>
                                </div>
                            </section>

                            <section class="space-y-3">
                                <h5
                                    class="flex items-center gap-2 font-black text-slate-800 dark:text-white uppercase text-xs tracking-widest">
                                    <span
                                        class="bg-brand-orange/10 p-2 rounded-lg text-brand-orange text-sm italic">3.</span>
                                    ⚡ Modo Foco
                                </h5>
                                <p class="text-sm text-slate-500 dark:text-slate-400 leading-relaxed italic px-2">
                                    Está indeciso? Clique em <b>Modo Foco</b>. O sistema escolherá a tarefa mais urgente
                                    para você. Sua missão: apenas concluir o que está na tela!
                                </p>
                            </section>

                            <section class="space-y-3">
                                <h5
                                    class="flex items-center gap-2 font-black text-slate-800 dark:text-white uppercase text-xs tracking-widest">
                                    <span
                                        class="bg-brand-orange/10 p-2 rounded-lg text-brand-orange text-sm italic">4.</span>
                                    📝 Notas Fixas
                                </h5>
                                <p class="text-sm text-slate-500 dark:text-slate-400 leading-relaxed italic px-2">
                                    Use os <b>Lembretes Fixos</b> para informações rápidas (links, CPFs, lembretes) que
                                    não são tarefas para "concluir".
                                </p>
                            </section>

                            <div
                                class="bg-brand-orange/5 p-6 rounded-[2rem] border border-dashed border-brand-orange/20 text-center">
                                <p class="text-xs font-bold text-brand-orange italic">"Menos fricção, mais execução." —
                                    Samsantos</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts de Interação -->
    <script>
        // Tema e Markdown
        if (localStorage.getItem('darkMode') === 'enabled' || (!localStorage.getItem('darkMode') && window.matchMedia('(prefers-color-scheme: dark)').matches)) document.documentElement.classList.add('dark');
        function toggleDarkMode() { const isDark = document.documentElement.classList.toggle('dark'); localStorage.setItem('darkMode', isDark ? 'enabled' : 'disabled'); }

        const renderer = new marked.Renderer();
        renderer.link = (href, title, text) => `<a href="${href}" target="_blank" rel="nofollow noopener noreferrer">${text}</a>`;
        marked.setOptions({ gfm: true, breaks: true, renderer: renderer });
        function renderAllMarkdown() { document.querySelectorAll('.markdown-body').forEach(container => { const id = container.id.split('-')[1]; const raw = document.getElementById('raw-desc-' + id); if (raw) container.innerHTML = marked.parse(raw.textContent.trim()); }); }
        window.addEventListener('DOMContentLoaded', renderAllMarkdown);

        // Sidebar e Overlay (Corrigido para não quebrar Desktop)
        function toggleMenu() {
            if (window.innerWidth <= 768) {
                document.getElementById('sidebar').classList.toggle('active');
                document.getElementById('overlay').classList.toggle('active');
            }
        }
        function toggleNotes() {
            document.getElementById('notes-drawer').classList.toggle('drawer-closed');
            document.getElementById('notes-drawer').classList.toggle('drawer-open');
            if (window.innerWidth <= 768) document.getElementById('overlay').classList.toggle('active');
        }
        function closeAll() {
            document.getElementById('notes-drawer').classList.add('drawer-closed');
            document.getElementById('notes-drawer').classList.remove('drawer-open');
            document.getElementById('sidebar').classList.remove('active');
            document.getElementById('overlay').classList.remove('active');
            ['modal-task', 'modal-edit-task', 'modal-settings', 'modal-upgrade'].forEach(id => document.getElementById(id).classList.add('hidden'));
        }
        function toggleDesc(id, event) { if (event.target.tagName === 'A' || event.target.tagName === 'INPUT') return; const el = document.getElementById('desc-' + id); el.classList.toggle('desc-truncate'); el.classList.toggle('desc-full'); }

        // Settings
        function openSettings() { document.getElementById('modal-settings').classList.remove('hidden'); }
        function closeSettings() { document.getElementById('modal-settings').classList.add('hidden'); }
        function switchTab(tabId) {
            document.querySelectorAll('.tab-content').forEach(c => c.classList.add('hidden'));
            document.getElementById(tabId).classList.remove('hidden');
            document.querySelectorAll('.tab-btn').forEach(b => { b.classList.remove('bg-brand-orange/10', 'text-brand-orange'); b.classList.add('text-slate-500'); });
            document.querySelector(`[data-tab="${tabId}"]`).classList.add('bg-brand-orange/10', 'text-brand-orange');
        }

        // Edição
        function openEditModal(id, contextId, energy, dueDate) {
            document.getElementById('modal-edit-task').classList.remove('hidden');
            document.getElementById('edit-task-id').value = id;
            document.getElementById('edit-task-title').value = document.getElementById('task-title-' + id).textContent.trim();
            document.getElementById('edit-task-desc').value = document.getElementById('raw-desc-' + id) ? document.getElementById('raw-desc-' + id).textContent.trim() : '';
            document.getElementById('edit-task-context').value = contextId;
            document.getElementById('edit-task-energy').value = energy;
            document.getElementById('edit-task-date').value = dueDate ? dueDate.split(' ')[0] : '';
        }

        window.onclick = function (e) { if (e.target.classList.contains('fixed') && !e.target.closest('#sidebar') && !e.target.closest('#notes-drawer')) closeAll(); }
    </script>
</body>

</html>