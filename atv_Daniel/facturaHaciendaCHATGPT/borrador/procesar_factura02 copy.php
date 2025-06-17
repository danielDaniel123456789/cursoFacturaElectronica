<?php
header('Content-Type: text/plain');

date_default_timezone_set('America/Costa_Rica');

$config = require 'config.php';
require 'consecutivo.php';

function obtenerToken($config) {
    $datos = [
        'grant_type'    => 'password',
        'client_id'     => $config['hacienda']['client_id'],
        'client_secret' => $config['hacienda']['client_secret'],
        'username'      => $config['hacienda']['usuario'],
        'password'      => $config['hacienda']['contrasena'],
        'scope'         => '',
    ];

    $ch = curl_init($config['hacienda']['url_token']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($datos));
    curl_setopt($ch, CURLOPT_POST, true);

    $respuesta = curl_exec($ch);
    if (curl_errno($ch)) {
        return 'Error CURL al obtener token: ' . curl_error($ch);
    }
    curl_close($ch);

    $json = json_decode($respuesta, true);
    if (!isset($json['access_token'])) {
        return 'Error al obtener token: ' . $respuesta;
    }
    return $json['access_token'];
}

function generarClaveNumerica($cedula, $consecutivo) {
    $codigoPais = '506';
    $fecha = date('dmY');
    $tipoCedula = str_pad($cedula, 12, '0', STR_PAD_LEFT);
    $consecutivo = str_pad($consecutivo, 20, '0', STR_PAD_LEFT);
    $situacion = '1';
    $codigoSeguridad = str_pad(strval(rand(10000000, 99999999)), 8, '0', STR_PAD_LEFT);

    return $codigoPais . $fecha . $tipoCedula . $consecutivo . $situacion . $codigoSeguridad;
}

// Recibir y validar datos
$cliente = [
    'nombre'  => trim($_POST['nombre'] ?? ''),
    'cedula'  => trim($_POST['cedula'] ?? ''),
    'correo'  => trim($_POST['correo'] ?? ''),
    'detalle' => trim($_POST['detalle'] ?? ''),
    'cabys'   => trim($_POST['cabys'] ?? ''),
    'monto'   => floatval($_POST['monto'] ?? 0),
];

// Validar campos vacíos y monto
foreach ($cliente as $campo => $valor) {
    if ($campo === 'monto' && $valor <= 0) {
        die("El monto debe ser mayor a cero.");
    } elseif ($campo !== 'monto' && $valor === '') {
        die("Falta el campo: $campo");
    }
}

// Validar formatos
if (!preg_match('/^\d{9}$/', $cliente['cedula'])) {
    die("Cédula inválida. Debe tener 9 dígitos.");
}
if (!preg_match('/^\d{13}$/', $cliente['cabys'])) {
    die("Código CABYS inválido. Debe tener 13 dígitos.");
}
if (!filter_var($cliente['correo'], FILTER_VALIDATE_EMAIL)) {
    die("Correo electrónico inválido.");
}

// Obtener token
$token = obtenerToken($config);
if (str_starts_with($token, 'Error')) {
    die($token);
}

// Obtener consecutivo real
$consecutivo = obtenerConsecutivo();

$clave = generarClaveNumerica($config['emisor']['cedula'], $consecutivo);

// Mostrar resultado parcial
echo "\n✅ Token obtenido correctamente\n";
echo "\n📦 Datos del cliente recibidos:\n";
foreach ($cliente as $campo => $valor) {
    echo ucfirst($campo) . ": $valor\n";
}
echo "Monto sin IVA: ₡" . number_format($cliente['monto'], 2) . "\n";
echo "IVA (13%): ₡" . number_format($cliente['monto'] * 0.13, 2) . "\n";
echo "Total: ₡" . number_format($cliente['monto'] * 1.13, 2) . "\n";
echo "\n🔢 Consecutivo: $consecutivo";
echo "\n🔑 Clave generada: $clave\n";
echo "\n🚧 Próximo paso: Generar el XML y firmar la factura con el certificado .p12";
