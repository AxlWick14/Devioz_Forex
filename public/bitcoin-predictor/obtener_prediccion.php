<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$curl = curl_init('http://127.0.0.1:5000/predict');
curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_TIMEOUT => 30]);
$response = curl_exec($curl);
$status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
curl_close($curl);
if ($response === false || $status === 0) {
    http_response_code(503);
    echo json_encode(['ok' => false, 'error' => 'El motor Python no está iniciado. Ejecuta btc_model.py serve después de entrenarlo.'], JSON_UNESCAPED_UNICODE);
    exit;
}
$data = json_decode($response, true);
if (!is_array($data) || !isset($data['ok'])) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'error' => 'El motor devolvió una respuesta inválida.']);
    exit;
}
http_response_code($status);
echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
