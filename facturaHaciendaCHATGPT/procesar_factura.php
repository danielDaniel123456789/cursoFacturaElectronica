<?php
header('Content-Type: text/plain');
date_default_timezone_set('America/Costa_Rica');

$config = require 'config.php';
require 'consecutivo.php';
require 'generar_xml.php';
require 'firmar_xml.php';
require 'enviar_xml.php';

// Función para obtener token de Hacienda
function obtenerToken(array $config): string {
    $datos = [
        'grant_type'    => 'password',
        'client_id'     => $config['hacienda']['client_id'],
        'client_secret' => $config['hacienda']['client_secret'],
        'username'      => $config['hacienda']['usuario'],
        'password'      => $config['hacienda']['contrasena'],
        // 'scope'      => '', // No enviar si está vacío
    ];

    $ch = curl_init($config['hacienda']['url_token']);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($datos),
    ]);

    $respuesta = curl_exec($ch);

    if (curl_errno($ch)) {
        die('Error CURL al obtener token: ' . curl_error($ch));
    }
    curl_close($ch);

    $json = json_decode($respuesta, true);

    if (!isset($json['access_token'])) {
        die("Error al obtener token: $respuesta");
    }

    return $json['access_token'];
}

// Función para generar clave numérica para la factura
function generarClaveNumerica(string $cedulaEmisor, string $consecutivo): string {
    $codigoPais = '506';
    $fecha = date('dmY');
    $tipoCedula = str_pad($cedulaEmisor, 12, '0', STR_PAD_LEFT);
    $consecutivo = str_pad($consecutivo, 20, '0', STR_PAD_LEFT);
    $situacion = '1';
    $codigoSeguridad = str_pad(strval(rand(10000000, 99999999)), 8, '0', STR_PAD_LEFT);

    return $codigoPais . $fecha . $tipoCedula . $consecutivo . $situacion . $codigoSeguridad;
}

// Recibir y validar datos del cliente
$cliente = [
    'nombre'  => trim($_POST['nombre'] ?? ''),
    'cedula'  => trim($_POST['cedula'] ?? ''),
    'correo'  => trim($_POST['correo'] ?? ''),
    'detalle' => trim($_POST['detalle'] ?? ''),
    'cabys'   => trim($_POST['cabys'] ?? ''),
    'monto'   => floatval($_POST['monto'] ?? 0),
];

// Validar campos obligatorios y formato
foreach ($cliente as $campo => $valor) {
    if ($campo === 'monto') {
        if ($valor <= 0) {
            die("El monto debe ser mayor a cero.");
        }
    } elseif (empty($valor)) {
        die("Falta el campo: $campo");
    }
}

if (!preg_match('/^\d{9}$/', $cliente['cedula'])) {
    die("Cédula inválida. Debe tener 9 dígitos.");
}

if (!preg_match('/^\d{13}$/', $cliente['cabys'])) {
    die("Código CABYS inválido. Debe tener 13 dígitos.");
}

if (!filter_var($cliente['correo'], FILTER_VALIDATE_EMAIL)) {
    die("Correo electrónico inválido.");
}

// Obtener token de Hacienda
$token = obtenerToken($config);

// Obtener consecutivo
$consecutivo = obtenerConsecutivo();
if (!$consecutivo) {
    die("Error al obtener consecutivo.");
}

// Generar clave numérica para la factura
$clave = generarClaveNumerica($config['emisor']['cedula'], $consecutivo);

// Fecha emisión en formato ISO 8601
$fecha_emision = date('c');

// Generar XML de la factura
$xmlFactura = generarXMLFactura(
    $config['emisor'],
    $cliente,
    [
        'detalle' => $cliente['detalle'],
        'cabys'   => $cliente['cabys'],
        'monto'   => $cliente['monto'],
    ],
    $consecutivo,
    $clave,
    $fecha_emision
);

if (!$xmlFactura) {
    die("Error al generar XML de la factura.");
}

// Firmar XML
list($exitoFirma, $xmlFirmado) = firmarXML(
    $xmlFactura,
    $config['certificado']['ruta'],
    $config['certificado']['clave']
);

if (!$exitoFirma) {
    die("Error al firmar el XML: $xmlFirmado");
}

// Enviar XML firmado a Hacienda
$respuesta = enviarXML($xmlFirmado, $token);

echo "\n✅ Token obtenido correctamente\n";
echo "\n📦 Datos del cliente recibidos:\n";
foreach ($cliente as $campo => $valor) {
    echo ucfirst($campo) . ": $valor\n";
}
echo "Monto sin IVA: ₡" . number_format($cliente['monto'], 2) . "\n";
echo "IVA (13%): ₡" . number_format($cliente['monto'] * 0.13, 2) . "\n";
echo "Total: ₡" . number_format($cliente['monto'] * 1.13, 2) . "\n";
echo "\n🔢 Consecutivo: $consecutivo\n";
echo "🔑 Clave generada: $clave\n";
echo "\n🚀 Factura enviada a Hacienda. Respuesta:\n$respuesta\n";
