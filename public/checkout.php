<?php
require_once __DIR__ . '/../src/Auth/Auth.php';
Auth::check();

$userId = $_SESSION['user_id'];
$userEmail = $_SESSION['user_email'];
$userName = $_SESSION['user_name'];

// --- CONFIGURAÇÃO ---
$access_token = "APP_USR-1501169359427193-010721-a0cc54b52f88e76375c638c4d7181f7f-182745599";

// 1. Preparamos os dados para a API de Preferências
$url = "https://api.mercadopago.com/checkout/preferences";

$data = [
    "items" => [
        [
            "title" => "Smart Todo Premium - Plano Mensal",
            "quantity" => 1,
            "unit_price" => 9.90, // Valor formatado corretamente
            "currency_id" => "BRL",
            "picture_url" => "https://samsantos.com.br/assets/images/favicon-16x16.png" // Seu logo
        ]
    ],
    "payer" => [
        "name" => $userName,
        "email" => $userEmail
    ],
    "external_reference" => (string) $userId,
    "back_urls" => [
        "success" => "https://todo.samsantos.com.br/index.php?pay=success",
        "failure" => "https://todo.samsantos.com.br/index.php?pay=error",
        "pending" => "https://todo.samsantos.com.br/index.php?pay=pending"
    ],
    "auto_return" => "approved",
    "notification_url" => "https://todo.samsantos.com.br/webhook.php"
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer $access_token",
    "Content-Type: application/json"
]);

$response = json_decode(curl_exec($ch), true);
curl_close($ch);

// Link oficial do Checkout Pro (Bonito e Brandeado)
$init_point = $response['init_point'] ?? null;

if (!$init_point) {
    die("Erro ao gerar checkout. Verifique suas credenciais.");
}
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Processando Pagamento...</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            background-color: #1C1E24;
            font-family: sans-serif;
        }

        .loader {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #D25B2E;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }
    </style>
</head>

<body class="flex items-center justify-center h-screen text-white">
    <div class="text-center">
        <div class="loader mx-auto mb-4"></div>
        <h2 class="text-xl font-bold mb-2">Conectando ao Mercado Pago</h2>
        <p class="text-slate-400 text-sm">Você será redirecionado para concluir sua assinatura com segurança.</p>
    </div>

    <script>
        // Redireciona automaticamente após 1.5 segundos para o Checkout Profissional
        setTimeout(() => {
            window.location.href = "<?= $init_point ?>";
        }, 1500);
    </script>
</body>

</html>