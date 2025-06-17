<?php
header('Content-Type: text/plain');

// Cargar la configuración
$config = require 'config.php';

// Incluir función para obtener consecutivo dinámico
require 'consecutivo.php';

// Función para obtener el token de Hacienda
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
        die('Error CURL al obtener token: ' . curl_error($ch));
    }

    curl_close($ch);
    $json = json_decode($respuesta, true);

    if (!isset($json['access_token'])) {
        die('Error al obtener token: ' . $respuesta);
    }

    return $json['access_token'];
}

// Función para generar la clave numérica
function generarClaveNumerica($cedula, $consecutivo) {
    $codigoPais = '506';
    $fecha = date('dmY'); // formato: día, mes, año
    $tipoCedula = str_pad($cedula, 12, '0', STR_PAD_LEFT); // debe tener 12 dígitos
    $situacion = '1'; // Normal
    $codigoSeguridad = str_pad(strval(rand(10000000, 99999999)), 8, '0', STR_PAD_LEFT); // 8 dígitos aleatorios

    // Clave completa
    return $codigoPais . $fecha . $tipoCedula . $consecutivo . $situacion . $codigoSeguridad;
}

// Recibir datos del formulario
$cliente = [
    'nombre'   => $_POST['nombre'] ?? '',
    'cedula'   => $_POST['cedula'] ?? '',
    'correo'   => $_POST['correo'] ?? '',
    'detalle'  => $_POST['detalle'] ?? '',
    'cabys'    => $_POST['cabys'] ?? '',
    'monto'    => floatval($_POST['monto'] ?? 0),
];

// Validar que estén completos
foreach ($cliente as $campo => $valor) {
    if (empty($valor)) {
        die("Falta el campo: $campo");
    }
}

// Obtener el token de Hacienda
$token = obtenerToken($config);
if (str_starts_with($token, 'Error')) {
    die($token); // Si hubo error, lo muestra
}

// Obtener consecutivo dinámico
$consecutivo_num = obtenerConsecutivo();  // debe retornar solo números
$consecutivo = str_pad($consecutivo_num, 20, '0', STR_PAD_LEFT);

// Generar clave numérica con el consecutivo real
$clave = generarClaveNumerica($config['emisor']['cedula'], $consecutivo);

// Mostrar resumen de datos
echo "\n✅ Token obtenido correctamente\n";
echo "\n📦 Datos del cliente recibidos:\n";
echo "Nombre: {$cliente['nombre']}\n";
echo "Cédula: {$cliente['cedula']}\n";
echo "Correo: {$cliente['correo']}\n";
echo "Detalle: {$cliente['detalle']}\n";
echo "CABYS: {$cliente['cabys']}\n";
echo "Monto sin IVA: ₡" . number_format($cliente['monto'], 2) . "\n";
echo "IVA (13%): ₡" . number_format($cliente['monto'] * 0.13, 2) . "\n";
echo "Total: ₡" . number_format($cliente['monto'] * 1.13, 2) . "\n";
echo "\n🔢 Consecutivo: $consecutivo";
echo "\n🔑 Clave generada: $clave\n";
echo "\n🚧 Próximo paso: Generar el XML de la factura y firmarlo con tu certificado .p12";


function obtenerConsecutivo() {
    $rutaArchivo = __DIR__ . '/consecutivo.txt';

    if (!file_exists($rutaArchivo)) {
        $consecutivo = '1';
        file_put_contents($rutaArchivo, $consecutivo);
        return $consecutivo;
    }

    $consecutivo = trim(file_get_contents($rutaArchivo));
    $nuevoConsecutivo = (string)((int)$consecutivo + 1);
    file_put_contents($rutaArchivo, $nuevoConsecutivo);
    return $consecutivo;
}