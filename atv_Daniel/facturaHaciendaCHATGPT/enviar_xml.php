<?php
header('Content-Type: text/plain');

$config = require 'config.php';

// Recibir datos desde POST
$xmlFirmado = $_POST['xmlFirmado'] ?? '';
$token = $_POST['token'] ?? '';
$clave = $_POST['clave'] ?? '';
$consecutivo = $_POST['consecutivo'] ?? '';
$emisor_cedula = $config['emisor']['cedula'];
$emisor_tipo = '01'; // Cédula física, por defecto
$receptor_cedula = $_POST['receptor_cedula'] ?? '';
$receptor_tipo = '01'; // Puedes ajustar

if (empty($xmlFirmado) || empty($token) || empty($clave) || empty($consecutivo)) {
    die("Faltan datos necesarios (xmlFirmado, token, clave o consecutivo).");
}

// Convertir a Base64
$xmlBase64 = base64_encode($xmlFirmado);

// Crear el JSON a enviar
$data = [
    "clave"      => $clave,
    "fecha"      => date('c'), // ISO 8601
    "emisor"     => [
        "tipoIdentificacion" => $emisor_tipo,
        "numeroIdentificacion" => $emisor_cedula,
    ],
    "comprobanteXml" => $xmlBase64,
];

// Solo agregar receptor si se incluye
if (!empty($receptor_cedula)) {
    $data["receptor"] = [
        "tipoIdentificacion" => $receptor_tipo,
        "numeroIdentificacion" => $receptor_cedula,
    ];
}

// URL de recepción (producción o pruebas)
$urlRecepcion = $config['hacienda']['url_recepcion'] ?? 'https://api.comprobanteselectronicos.go.cr/recepcion/v1/recepcion';

// Enviar con cURL
$ch = curl_init($urlRecepcion);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer {$token}",
    "Content-Type: application/json",
]);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

$respuesta = curl_exec($ch);

if (curl_errno($ch)) {
    die("Error cURL: " . curl_error($ch));
}

$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Mostrar resultado
echo "✅ HTTP Status: $httpCode\n";
echo "📨 Respuesta de Hacienda:\n$respuesta";
