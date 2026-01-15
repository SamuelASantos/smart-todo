<?php
require_once __DIR__ . '/../src/Auth/Auth.php';

$message = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = Auth::generateResetToken($_POST['email']);
    if ($token) {
        // Simulação do envio de e-mail (Em produção, aqui usaria mail() ou PHPMailer)
        $resetLink = "https://todo.samsantos.com.br/reset-password.php?token=$token";
        $message = "Link de recuperação gerado: <br><a href='$resetLink' class='underline font-bold text-brand-orange break-all'>$resetLink</a>";
    } else {
        $error = "Se este e-mail estiver cadastrado, você receberá um link de recuperação em instantes.";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Senha - Smart Todo</title>
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
                class="bg-brand-orange w-14 h-14 rounded-2xl mx-auto flex items-center justify-center text-white text-2xl font-black italic shadow-lg shadow-brand-orange/30 mb-6">
                S</div>
            <h2 class="text-2xl font-black text-brand-black dark:text-white italic tracking-tighter uppercase">Recuperar
                Acesso</h2>
        </div>

        <?php if ($message): ?>
            <div
                class="bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 p-5 rounded-2xl mb-8 text-sm border border-emerald-100 dark:border-emerald-500/20">
                <?= $message ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div
                class="bg-blue-50 dark:bg-blue-500/10 text-blue-600 dark:text-blue-400 p-5 rounded-2xl mb-8 text-sm border border-blue-100 dark:border-blue-500/20 text-center font-medium">
                <?= $error ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-5">
            <div class="space-y-1">
                <label class="text-[10px] font-bold text-slate-400 uppercase tracking-widest ml-4">Seu E-mail</label>
                <input type="email" name="email" placeholder="email@exemplo.com" required
                    class="w-full bg-slate-50 dark:bg-white/5 p-5 rounded-2xl outline-none focus:ring-2 focus:ring-brand-orange dark:text-white transition-all">
            </div>
            <button type="submit"
                class="w-full bg-brand-orange text-white py-5 rounded-2xl font-black shadow-xl shadow-brand-orange/30 hover:scale-[1.02] transition-all uppercase italic italic tracking-tighter">Enviar
                Instruções</button>
        </form>

        <div class="mt-10 text-center">
            <a href="login.php"
                class="text-slate-400 text-xs font-bold uppercase tracking-widest hover:text-brand-orange transition-colors">Voltar
                para o Login</a>
        </div>
    </div>
    <script>if (localStorage.getItem('darkMode') === 'enabled') document.documentElement.classList.add('dark');</script>
</body>

</html>