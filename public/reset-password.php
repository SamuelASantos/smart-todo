<?php
require_once __DIR__ . '/../src/Auth/Auth.php';

$token = $_GET['token'] ?? null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token'];
    $newPassword = $_POST['password'];

    if (Auth::resetPasswordWithToken($token, $newPassword)) {
        header("Location: login.php?reset=success");
        exit;
    } else {
        $error = "O link de recuperação é inválido ou já expirou.";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nova Senha - Smart Todo</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;700;800&display=swap"
        rel="stylesheet">
    <link rel="icon" type="image/png" href="/assets/images/favicon-16x16.png?v=1.1">
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: { extend: { colors: { brand: { orange: '#D25B2E', black: '#1C1E24', white: '#F0F0F0' } } } }
        }
    </script>
</head>

<body
    class="bg-brand-white dark:bg-[#111216] flex items-center justify-center min-h-screen p-6 font-['Plus_Jakarta_Sans'] transition-colors duration-500">
    <div
        class="bg-white dark:bg-brand-black p-8 md:p-12 rounded-[3rem] shadow-2xl w-full max-w-md border border-slate-100 dark:border-white/5">
        <div class="text-center mb-10">
            <div
                class="bg-brand-orange w-14 h-14 rounded-2xl mx-auto flex items-center justify-center text-white text-2xl font-black italic shadow-lg mb-6">
                S</div>
            <h2 class="text-2xl font-black text-brand-black dark:text-white italic tracking-tighter uppercase">Nova
                Senha</h2>
            <p class="text-slate-400 text-xs mt-2 font-bold tracking-widest uppercase">Crie sua nova credencial de
                acesso</p>
        </div>

        <?php if ($error): ?>
            <div
                class="bg-rose-50 dark:bg-rose-500/10 text-rose-500 p-5 rounded-2xl mb-8 text-sm border border-rose-100 dark:border-rose-500/20 text-center font-bold">
                <?= $error ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-6">
            <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

            <div class="space-y-1">
                <label class="text-[10px] font-bold text-slate-400 uppercase tracking-widest ml-4">Digite a Nova
                    Senha</label>
                <input type="password" name="password" placeholder="••••••••" required autofocus
                    class="w-full bg-slate-50 dark:bg-white/5 p-5 rounded-2xl outline-none focus:ring-2 focus:ring-brand-orange dark:text-white transition-all text-lg">
            </div>

            <button type="submit"
                class="w-full bg-brand-orange text-white py-5 rounded-2xl font-black shadow-xl shadow-brand-orange/30 hover:scale-[1.02] transition-all uppercase italic tracking-tighter">Redefinir
                Agora</button>
        </form>

        <div class="mt-8 text-center">
            <p class="text-slate-400 text-[10px] uppercase font-bold tracking-widest italic">Segurança Samsantos</p>
        </div>
    </div>
    <script>if (localStorage.getItem('darkMode') === 'enabled') document.documentElement.classList.add('dark');</script>
</body>

</html>