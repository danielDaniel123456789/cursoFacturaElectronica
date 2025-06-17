<?php
// app.php - Punto de entrada principal para facturación electrónica
declare(strict_types=1);

require_once __DIR__ . '/src/configuracion.php';
require_once __DIR__ . '/src/gestion_consecutivos.php';
require_once __DIR__ . '/src/generador_facturas.php';
require_once __DIR__ . '/src/firmador_documentos.php';
require_once __DIR__ . '/src/cliente_api_hacienda.php';
require_once __DIR__ . '/src/helpers.php'; // Para funciones auxiliares

// 1. Validar método de solicitud y datos de entrada
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    header('Location: templates/index.html');
    exit;
}

// Validar campos obligatorios
$camposRequeridos = [
    'cliente_identificacion',
    'cliente_nombre',
    'cliente_email',
    'codigo_cabys',
    'servicio_descripcion',
    'monto'
];

foreach ($camposRequeridos as $campo) {
    if (empty($_POST[$campo])) {
        responderError(400, "El campo $campo es requerido");
    }
}

// 2. Sanitizar y validar datos de entrada
try {
    $datosFactura = [
        'emisor' => [
            'identificacion' => $config['empresa']['identificacion'],
            'tipo_identificacion' => $config['empresa']['tipo_identificacion'],
            'codigo_actividad' => '721001', // Consultores informáticos
        ],
        'receptor' => [
            'identificacion' => filter_var($_POST['cliente_identificacion'], FILTER_SANITIZE_STRING),
            'nombre' => filter_var($_POST['cliente_nombre'], FILTER_SANITIZE_STRING),
            'correo' => filter_var($_POST['cliente_email'], FILTER_VALIDATE_EMAIL),
        ],
        'detalle' => [
            [
                'codigo' => filter_var($_POST['codigo_cabys'], FILTER_SANITIZE_STRING),
                'descripcion' => filter_var($_POST['servicio_descripcion'], FILTER_SANITIZE_STRING),
                'monto' => filter_var($_POST['monto'], FILTER_VALIDATE_FLOAT),
                'impuesto' => 13 // 13%
            ]
        ]
    ];

    if ($datosFactura['receptor']['correo'] === false) {
        throw new InvalidArgumentException("El correo electrónico no es válido");
    }

    if ($datosFactura['detalle'][0]['monto'] === false || $datosFactura['detalle'][0]['monto'] <= 0) {
        throw new InvalidArgumentException("El monto debe ser un número positivo");
    }

    // 3. Generar consecutivo
    $consecutivo = obtenerConsecutivo();
    
    // 4. Generar XML
    $xmlPath = generarFacturaXML($datosFactura, $consecutivo);
    
    // 5. Firmar XML
    $xmlFirmadoPath = firmarFactura($xmlPath);
    
    // 6. Enviar a Hacienda
    $respuestaHacienda = enviarFacturaHacienda($xmlFirmadoPath);
    
    // 7. Registrar en base de datos (ejemplo)
    registrarFacturaEnBD($consecutivo, $datosFactura, $respuestaHacienda);
    
    // 8. Responder con éxito
    responderExito([
        'success' => true,
        'consecutivo' => $consecutivo,
        'respuesta' => $respuestaHacienda,
        'clave' => $respuestaHacienda['clave'] ?? null
    ]);

} catch (InvalidArgumentException $e) {
    responderError(400, $e->getMessage());
} catch (Exception $e) {
    // Registrar error en logs
    error_log("Error en facturación: " . $e->getMessage());
    responderError(500, "Ocurrió un error al procesar la factura");
}

/**
 * Funciones auxiliares (podrían ir en helpers.php)
 */
function responderExito(array $datos): void {
    header('Content-Type: application/json');
    http_response_code(200);
    echo json_encode($datos);
    exit;
}

function responderError(int $codigo, string $mensaje): void {
    header('Content-Type: application/json');
    http_response_code($codigo);
    echo json_encode([
        'success' => false,
        'error' => $mensaje
    ]);
    exit;
}

function registrarFacturaEnBD(string $consecutivo, array $datos, array $respuestaHacienda): void {
    // Implementación real dependería de tu sistema de base de datos
    $fecha = date('Y-m-d H:i:s');
    $clave = $respuestaHacienda['clave'] ?? '';
    
    // Ejemplo con PDO (deberías configurar tu conexión)
    try {
        $pdo = new PDO('mysql:host=localhost;dbname=facturacion', 'usuario', 'password');
        $stmt = $pdo->prepare("INSERT INTO facturas 
            (consecutivo, clave, fecha, cliente_identificacion, cliente_nombre, cliente_email, monto, respuesta_hacienda) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->execute([
            $consecutivo,
            $clave,
            $fecha,
            $datos['receptor']['identificacion'],
            $datos['receptor']['nombre'],
            $datos['receptor']['correo'],
            $datos['detalle'][0]['monto'],
            json_encode($respuestaHacienda)
        ]);
    } catch (PDOException $e) {
        error_log("Error al registrar factura en BD: " . $e->getMessage());
    }
}