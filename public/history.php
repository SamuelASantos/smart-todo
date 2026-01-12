<?php
require_once __DIR__ . '/../src/Auth/Auth.php';
require_once __DIR__ . '/../src/Models/Activity.php';
require_once __DIR__ . '/../src/Helpers/SmartAI.php';

Auth::check();
$userId = $_SESSION['user_id'];
$userName = $_SESSION['user_name'];

$stats = Activity::getWeeklyStats($userId);
$logs = Activity::getRecentLog($userId);
$insight = SmartAI::generateInsight($userId);

$energyStats = ['low' => 0, 'medium' => 0, 'high' => 0];
foreach ($stats as $s) {
    $energyStats[$s['energy_level']] = $s['total'];
}
$totalWeek = array_sum($energyStats);
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatórios - Smart Todo</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
        
    <link rel="icon" type="image/png" href="/assets/images/favicon-16x16.png?v=1.1">
    
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #F8FAFC;
        }

        @media (max-width: 768px) {
            .sidebar-mobile {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }

            .sidebar-mobile.active {
                transform: translateX(0);
            }

            .overlay {
                display: none;
            }

            .overlay.active {
                display: block;
            }
        }
    </style>
</head>

<body class="flex h-screen overflow-hidden">

    <!-- OVERLAY MOBILE -->
    <div id="overlay" onclick="toggleMenu()"
        class="overlay fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-30 md:hidden"></div>

    <!-- SIDEBAR -->
    <aside id="sidebar"
        class="sidebar-mobile fixed md:relative z-40 w-72 bg-white border-r border-slate-200 h-full flex flex-col shadow-sm md:translate-x-0">
        <div class="p-8 flex justify-between items-center">
            <div class="flex items-center gap-3 text-blue-600">
                <div class="bg-blue-600 p-2 rounded-xl text-white font-bold italic">S</div>
                <h1 class="text-xl font-extrabold text-slate-800 tracking-tight">SmartTodo</h1>
            </div>
            <button onclick="toggleMenu()" class="md:hidden text-slate-400">✕</button>
        </div>

        <nav class="flex-1 overflow-y-auto px-4 space-y-1">
            <a href="index.php"
                class="flex items-center gap-3 p-4 rounded-xl text-slate-500 hover:bg-slate-50 transition-all font-semibold">
                <span>🏠</span> Voltar ao App
            </a>
            <div class="p-4 bg-blue-50 text-blue-700 rounded-xl flex items-center gap-3 shadow-sm font-bold italic">
                <span>📊</span> Relatórios
            </div>
        </nav>

        <div class="p-6 border-t bg-slate-50/50">
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1 px-3">Usuário</p>
            <p class="px-3 text-sm font-bold text-slate-700 truncate"><?= htmlspecialchars($userName) ?></p>
        </div>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="flex-1 flex flex-col overflow-y-auto p-6 md:p-12">
        <header class="mb-8 flex items-center gap-4">
            <button onclick="toggleMenu()" class="md:hidden text-slate-500 p-2 bg-white border rounded-lg shadow-sm">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path d="M4 6h16M4 12h16M4 18h16"></path>
                </svg>
            </button>
            <div>
                <h1 class="text-2xl md:text-3xl font-extrabold text-slate-800 tracking-tight">Produtividade</h1>
            </div>
        </header>

        <!-- INSIGHT DA IA (Adaptado para mobile) -->
        <div
            class="mb-10 bg-<?= $insight['color'] ?>-600 rounded-[2rem] p-0.5 shadow-xl shadow-<?= $insight['color'] ?>-100">
            <div class="bg-white rounded-[1.9rem] p-6 md:p-8 flex flex-col md:flex-row items-center gap-6">
                <div
                    class="bg-<?= $insight['color'] ?>-50 w-16 h-16 md:w-20 md:h-20 rounded-2xl flex items-center justify-center text-3xl shrink-0">
                    <?= $insight['icon'] ?>
                </div>
                <div class="flex-1 text-center md:text-left">
                    <h4 class="text-<?= $insight['color'] ?>-600 font-bold uppercase text-[10px] tracking-widest mb-1">
                        IA Insight</h4>
                    <h3 class="text-xl md:text-2xl font-bold text-slate-800"><?= $insight['title'] ?></h3>
                    <p class="text-slate-500 mt-1 text-sm md:text-lg"><?= $insight['message'] ?></p>
                </div>
                <a href="focus.php"
                    class="w-full md:w-auto bg-<?= $insight['color'] ?>-600 text-white px-8 py-4 rounded-2xl font-bold shadow-lg text-center">Agir</a>
            </div>
        </div>

        <!-- CARDS DE MÉTRICAS (Grid responsivo) -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 md:gap-8 mb-10">
            <div class="bg-white p-6 md:p-8 rounded-3xl border border-slate-100 shadow-sm">
                <p class="text-slate-400 text-[10px] font-bold uppercase tracking-widest">Semana</p>
                <p class="text-4xl md:text-5xl font-black text-slate-800 mt-1"><?= $totalWeek ?></p>
            </div>
            <div class="bg-white p-6 md:p-8 rounded-3xl border border-slate-100 shadow-sm">
                <p class="text-slate-400 text-[10px] font-bold uppercase tracking-widest">Foco 🧠</p>
                <p class="text-4xl md:text-5xl font-black text-rose-600 mt-1"><?= $energyStats['high'] ?></p>
            </div>
            <div class="bg-white p-6 md:p-8 rounded-3xl border border-slate-100 shadow-sm">
                <p class="text-slate-400 text-[10px] font-bold uppercase tracking-widest">Leves 🌱</p>
                <p class="text-4xl md:text-5xl font-black text-emerald-600 mt-1"><?= $energyStats['low'] ?></p>
            </div>
        </div>

        <!-- TIMELINE (Timeline vertical adaptada) -->
        <div class="bg-white rounded-[2rem] border border-slate-100 shadow-sm p-6 md:p-10">
            <h3 class="text-lg font-bold text-slate-800 mb-8 flex items-center gap-3">
                <span class="w-1.5 h-6 bg-blue-600 rounded-full"></span> Histórico
            </h3>
            <div class="space-y-8">
                <?php foreach ($logs as $log): ?>
                    <div class="flex items-start gap-4 md:gap-6 relative">
                        <div class="flex flex-col items-center shrink-0">
                            <div class="w-3 h-3 rounded-full bg-blue-500 z-10"></div>
                            <div class="w-0.5 h-full bg-slate-100 absolute left-[5px] top-3"></div>
                        </div>
                        <div class="pb-2">
                            <p class="text-[9px] font-bold text-slate-300 uppercase">
                                <?= date('d/m, H:i', strtotime($log['created_at'])) ?></p>
                            <p class="text-slate-700 font-semibold text-sm md:text-base">
                                <?= htmlspecialchars($log['details']) ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </main>

    <script>
        function toggleMenu() {
            document.getElementById('sidebar').classList.toggle('active');
            document.getElementById('overlay').classList.toggle('active');
        }
    </script>
</body>

</html>