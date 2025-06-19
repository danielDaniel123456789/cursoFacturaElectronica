<?php
// consulta_facturas.php
header('Content-Type: application/json');
$config = require 'config.php';

$token = $_GET['token'] ?? '';
if (empty($token)) {
    http_response_code(400);
    echo json_encode(['error' => 'Falta token de autorización']);
    exit;
}

// URL para consultar facturas (ejemplo, verifica en documentación oficial)
$urlConsulta = $config['hacienda']['url_consulta'] ?? 'https://api.comprobanteselectronicos.go.cr/recepcion/v1/consulta';

$ch = curl_init($urlConsulta);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer {$token}",
    "Content-Type: application/json",
]);
curl_setopt($ch, CURLOPT_POST, true);

// Si el endpoint requiere un cuerpo, lo agregas aquí. Por ejemplo, filtrar por fechas.
// Si no, puedes hacer GET y ajustar cURL

// Aquí ejemplo sin cuerpo, cambiar si necesario
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([])); 

$respuesta = curl_exec($ch);
if (curl_errno($ch)) {
    http_response_code(500);
    echo json_encode(['error' => curl_error($ch)]);
    exit;
}

$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    http_response_code($httpCode);
    echo json_encode(['error' => "HTTP code $httpCode", 'respuesta' => $respuesta]);
    exit;
}

echo $respuesta;
