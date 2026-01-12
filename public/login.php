<?php
require_once __DIR__ . '/../src/Auth/Auth.php';

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = Auth::login($_POST['email'], $_POST['password']);
    if ($result === true) {
        header("Location: index.php");
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
    <title>Login - Smart Todo</title>
    <script src="https://cdn.tailwindcss.com"></script>
    
    <link rel="icon" type="image/png" href="/assets/images/favicon-16x16.png?v=1.1">

</head>

<body class="bg-slate-50 flex items-center justify-center h-screen">
    <div class="bg-white p-8 rounded-xl shadow-lg w-full max-w-md">
        <h2 class="text-2xl font-bold text-slate-800 mb-2 text-center">Bem-vindo de volta</h2>
        <p class="text-slate-500 text-sm text-center mb-6">Pronto para executar com inteligência?</p>

        <?php if (isset($_GET['registered'])): ?>
            <div class="bg-green-100 text-green-700 p-3 rounded mb-4 text-sm text-center">Conta criada! Faça login.</div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="bg-red-100 text-red-700 p-3 rounded mb-4 text-sm text-center"><?= $error ?></div>
        <?php endif; ?>

        <form method="POST" class="space-y-4">
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
                class="w-full bg-blue-600 text-white py-2.5 rounded-lg font-semibold hover:bg-blue-700 transition">Entrar</button>
        </form>
        <p class="text-center text-sm text-slate-600 mt-4">Novo por aqui? <a href="register.php"
                class="text-blue-600 hover:underline">Criar conta</a></p>
    </div>
</body>

</html>