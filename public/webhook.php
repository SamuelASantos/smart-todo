<?php
require_once __DIR__ . '/../config/database.php';

$json = file_get_contents('php://input');
$data = json_decode($json, true);

// O Checkout Pro avisa como 'payment'
if (isset($data['type']) && $data['type'] === 'payment') {
    $id = $data['data']['id'];
    $access_token = "APP_USR-1501169359427193-010721-a0cc54b52f88e76375c638c4d7181f7f-182745599";

    $ch = curl_init("https://api.mercadopago.com/v1/payments/$id");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $access_token"]);
    $payment = json_decode(curl_exec($ch), true);
    curl_close($ch);

    if (isset($payment['status']) && $payment['status'] === 'approved') {
        $userId = $payment['external_reference'];
        $db = getConnection();

        $expiresAt = date('Y-m-d H:i:s', strtotime('+31 days'));
        $stmt = $db->prepare("UPDATE todo_users SET subscription_plan = 'premium', expires_at = ? WHERE id = ?");
        $stmt->execute([$expiresAt, $userId]);
    }
}
http_response_code(200);