<?php
require_once __DIR__ . '/../src/Auth/Auth.php';

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = Auth::register($_POST['name'], $_POST['email'], $_POST['password']);
    if ($result === true) {
        header("Location: login.php?registered=1");
        exit;
    } else {
        $error = $result;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro - Smart Todo</title>
    <script src="https://cdn.tailwindcss.com"></script>
    
    <link rel="icon" type="image/png" href="/assets/images/favicon-16x16.png?v=1.1">
    
</head>

<body class="bg-slate-50 flex items-center justify-center h-screen">
    <div class="bg-white p-8 rounded-xl shadow-lg w-full max-w-md">
        <h2 class="text-2xl font-bold text-slate-800 mb-6 text-center">Criar Conta Inteligente</h2>

        <?php if ($error): ?>
            <div class="bg-red-100 text-red-700 p-3 rounded mb-4 text-sm"><?= $error ?></div>
        <?php endif; ?>

        <form method="POST" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-slate-700">Nome</label>
                <input type="text" name="name" required
                    class="w-full border rounded-lg p-2.5 mt-1 focus:ring-2 focus:ring-blue-500 outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">E-mail</label>
                <input type="email" name="email" required
                    class="w-full border rounded-lg p-2.5 mt-1 focus:ring-2 focus:ring-blue-500 outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">Senha</label>
                <input type="password" name="password" required
                    class="w-full border rounded-lg p-2.5 mt-1 focus:ring-2 focus:ring-blue-500 outline-none">
            </div>
            <button type="submit"
                class="w-full bg-blue-600 text-white py-2.5 rounded-lg font-semibold hover:bg-blue-700 transition">Começar
                Agora</button>
        </form>
        <p class="text-center text-sm text-slate-600 mt-4">Já tem conta? <a href="login.php"
                class="text-blue-600 hover:underline">Entrar</a></p>
    </div>
</body>

</html>