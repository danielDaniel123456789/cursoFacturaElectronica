
<?php
header('Content-Type: text/plain');

$config = require 'config.php';

$clave = $_GET['clave'] ?? '';
$token = $_GET['token'] ?? '';

if (empty($clave) || empty($token)) {
    die("Faltan la clave o el token para consultar el estado.");
}

// URL base (pruebas o producción)
$urlBase = $config['hacienda']['url_consulta'] ?? 'https://api.comprobanteselectronicos.go.cr/recepcion/v1/recepcion';

// Agregar clave a la URL
$urlConsulta = $urlBase . '/' . $clave;

// Ejecutar cURL GET
$ch = curl_init($urlConsulta);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer {$token}",
    "Content-Type: application/json",
]);

$respuesta = curl_exec($ch);

if (curl_errno($ch)) {
    die("Error cURL: " . curl_error($ch));
}

$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "🔎 HTTP Status: $httpCode\n\n";
echo "📥 Respuesta de Hacienda:\n";

// Mostrar en formato legible
print_r(json_decode($respuesta, true));
