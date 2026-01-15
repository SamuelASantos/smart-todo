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
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;700;800&display=swap"
        rel="stylesheet">
    <script>
        tailwind.config = { darkMode: 'class', theme: { extend: { colors: { brand: { white: '#F0F0F0', orange: '#D25B2E', black: '#1C1E24' } } } } }
    </script>
    <style>
        @media (max-width: 768px) {
            .sidebar-mobile {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }

            .sidebar-mobile.active {
                transform: translateX(0);
            }
        }
    </style>
</head>

<body
    class="bg-brand-white dark:bg-[#111216] flex h-screen overflow-hidden font-['Plus_Jakarta_Sans'] transition-colors duration-500">

    <div id="overlay" onclick="toggleMenu()" class="fixed inset-0 bg-brand-black/60 backdrop-blur-sm z-30 hidden"></div>

    <!-- SIDEBAR (Mesma lógica do index) -->
    <aside id="sidebar"
        class="sidebar-mobile fixed md:relative z-40 w-72 bg-white dark:bg-brand-black border-r border-slate-200 dark:border-slate-800 h-full flex flex-col shadow-sm md:translate-x-0">
        <div class="p-8 flex justify-between items-center">
            <h1 class="text-xl font-black text-brand-orange italic uppercase tracking-tighter">Smart Todo</h1>
            <button onclick="toggleMenu()" class="md:hidden text-slate-400 font-bold">✕</button>
        </div>
        <nav class="flex-1 px-4 space-y-1">
            <a href="index.php"
                class="flex items-center gap-3 p-4 rounded-xl text-slate-500 hover:bg-slate-100 dark:hover:bg-white/5 font-bold">🏠
                Voltar ao App</a>
            <div class="p-4 bg-brand-orange text-white rounded-xl shadow-lg shadow-brand-orange/30 font-black italic">📊
                Relatórios</div>
        </nav>
    </aside>

    <!-- MAIN -->
    <main class="flex-1 flex flex-col overflow-y-auto p-6 md:p-12 min-w-0">
        <header class="flex items-center gap-4 mb-10">
            <button onclick="toggleMenu()" class="md:hidden p-3 bg-brand-orange text-white rounded-xl shadow-lg">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path d="M4 6h16M4 12h16M4 18h16"></path>
                </svg>
            </button>
            <h1 class="text-3xl font-black text-brand-black dark:text-white italic tracking-tighter uppercase">
                Produtividade</h1>
        </header>

        <!-- INSIGHT (Mobile Responsive) -->
        <div
            class="mb-10 bg-<?= $insight['color'] ?>-600 rounded-[2.5rem] p-0.5 shadow-xl shadow-<?= $insight['color'] ?>-500/10">
            <div class="bg-white dark:bg-brand-black rounded-[2.4rem] p-8 flex flex-col md:flex-row items-center gap-8">
                <div class="text-6xl"><?= $insight['icon'] ?></div>
                <div class="flex-1 text-center md:text-left">
                    <h4
                        class="text-<?= $insight['color'] ?>-500 font-black uppercase text-[10px] tracking-widest mb-1 italic">
                        Inteligência Samsantos</h4>
                    <h3 class="text-2xl font-black dark:text-white"><?= $insight['title'] ?></h3>
                    <p class="text-slate-500 dark:text-slate-400 mt-2 text-lg"><?= $insight['message'] ?></p>
                </div>
                <a href="focus.php"
                    class="w-full md:w-auto bg-brand-orange text-white px-10 py-5 rounded-2xl font-black italic shadow-lg shadow-brand-orange/30 uppercase text-xs">AGIR
                    AGORA</a>
            </div>
        </div>

        <!-- METRICS GRID -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 mb-10">
            <div
                class="bg-white dark:bg-brand-black p-8 rounded-[2.5rem] border border-slate-100 dark:border-white/5 shadow-sm text-center">
                <p class="text-slate-400 text-[10px] font-black uppercase tracking-widest mb-2">Semana</p>
                <p class="text-5xl font-black dark:text-white italic tracking-tighter"><?= $totalWeek ?></p>
            </div>
            <div
                class="bg-white dark:bg-brand-black p-8 rounded-[2.5rem] border border-slate-100 dark:border-white/5 shadow-sm text-center">
                <p class="text-slate-400 text-[10px] font-black uppercase tracking-widest mb-2">Deep Work 🧠</p>
                <p class="text-5xl font-black text-brand-orange italic tracking-tighter"><?= $energyStats['high'] ?></p>
            </div>
            <div
                class="bg-white dark:bg-brand-black p-8 rounded-[2.5rem] border border-slate-100 dark:border-white/5 shadow-sm text-center">
                <p class="text-slate-400 text-[10px] font-black uppercase tracking-widest mb-2">Consistência 🌱</p>
                <p class="text-5xl font-black text-[#73937e] italic tracking-tighter"><?= $energyStats['low'] ?></p>
            </div>
        </div>

        <!-- TIMELINE -->
        <div
            class="bg-white dark:bg-brand-black rounded-[2.5rem] border border-slate-100 dark:border-white/5 shadow-sm p-8 md:p-12 mb-10">
            <h3 class="text-xl font-black mb-10 italic uppercase tracking-tighter">Histórico de Execução</h3>
            <div class="space-y-8">
                <?php foreach ($logs as $log): ?>
                    <div class="flex items-start gap-6 relative">
                        <div class="w-3 h-3 rounded-full bg-brand-orange mt-1.5 shrink-0 shadow-lg shadow-brand-orange/40">
                        </div>
                        <div class="pb-6 border-l-2 border-slate-100 dark:border-white/5 pl-8 -ml-[31px]">
                            <p class="text-[9px] font-black text-slate-300 dark:text-slate-600 uppercase tracking-widest">
                                <?= date('d M, H:i', strtotime($log['created_at'])) ?></p>
                            <p class="text-slate-700 dark:text-slate-300 font-bold mt-1">
                                <?= htmlspecialchars($log['details']) ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </main>

    <script>
        if (localStorage.getItem('darkMode') === 'enabled') document.documentElement.classList.add('dark');
        function toggleMenu() {
            document.getElementById('sidebar').classList.toggle('active');
            document.getElementById('overlay').classList.toggle('hidden');
        }
    </script>
</body>

</html>