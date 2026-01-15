<?php
require_once __DIR__ . '/../src/Auth/Auth.php';
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = Auth::login($_POST['email'], $_POST['password']);
    if ($result === true) {
        header("Location: index.php");
        exit;
    }
    $error = $result;
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Smart Todo</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;700;800&display=swap"
        rel="stylesheet">
    <script>
        tailwind.config = { darkMode: 'class', theme: { extend: { colors: { brand: { white: '#F0F0F0', orange: '#D25B2E', black: '#1C1E24' } } } } }
    </script>
</head>

<body
    class="bg-brand-white dark:bg-[#111216] h-screen flex items-center justify-center p-6 font-['Plus_Jakarta_Sans'] transition-colors duration-500">
    <div
        class="bg-white dark:bg-brand-black p-8 md:p-12 rounded-[3rem] shadow-2xl w-full max-w-md border border-slate-100 dark:border-white/5">
        <div class="text-center mb-10">
            <div
                class="bg-brand-orange w-16 h-16 rounded-2xl mx-auto flex items-center justify-center text-white text-3xl font-black italic shadow-lg shadow-brand-orange/30 mb-4">
                S</div>
            <h2 class="text-3xl font-black text-brand-black dark:text-white italic tracking-tighter">SMART TODO</h2>
            <p class="text-slate-400 text-sm mt-2 font-medium uppercase tracking-widest">Acesse sua conta</p>
        </div>

        <?php if ($error): ?>
            <div
                class="bg-rose-50 dark:bg-rose-500/10 text-rose-500 p-4 rounded-2xl mb-6 text-sm font-bold text-center border border-rose-100 dark:border-rose-500/20">
                <?= $error ?></div>
        <?php endif; ?>

        <form method="POST" class="space-y-5">
            <input type="email" name="email" placeholder="E-mail" required
                class="w-full bg-slate-100 dark:bg-white/5 p-5 rounded-2xl outline-none focus:ring-2 focus:ring-brand-orange dark:text-white transition-all">
            <input type="password" name="password" placeholder="Senha" required
                class="w-full bg-slate-100 dark:bg-white/5 p-5 rounded-2xl outline-none focus:ring-2 focus:ring-brand-orange dark:text-white transition-all">
            <button type="submit"
                class="w-full bg-brand-orange text-white py-5 rounded-2xl font-black text-lg shadow-xl shadow-brand-orange/30 hover:scale-[1.02] transition-all uppercase italic">Entrar
                agora</button>
        </form>

        <div class="mt-8 text-center space-y-3">
            <p class="text-slate-400 text-sm">Novo por aqui? <a href="register.php"
                    class="text-brand-orange font-bold hover:underline">Criar conta</a></p>
            <a href="forgot-password.php"
                class="block text-[10px] font-bold text-slate-300 uppercase tracking-widest hover:text-brand-orange transition-colors">Esqueci
                minha senha</a>
        </div>
    </div>

    <script>
        if (localStorage.getItem('darkMode') === 'enabled') document.documentElement.classList.add('dark');
    </script>
</body>

</html>