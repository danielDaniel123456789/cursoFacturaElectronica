<?php
// src/generador_pdf.php - Generador de PDF para facturas electrónicas
require_once __DIR__ . '/configuracion.php';

/**
 * Genera un PDF a partir de los datos de la factura
 * @param array $datosFactura Datos de la factura
 * @param string $consecutivo Número consecutivo
 * @param string $clave Clave numérica de Hacienda
 * @return string Ruta del archivo PDF generado
 */
function generarPDFFactura(array $datosFactura, string $consecutivo, string $clave): string {
    $config = include __DIR__ . '/configuracion.php';
    
    // 1. Cargar plantilla HTML
    $html = cargarPlantillaPDF($datosFactura, $consecutivo, $clave);
    
    // 2. Configurar generador PDF
    $options = new Dompdf\Options();
    $options->set('isRemoteEnabled', true);
    $options->set('isHtml5ParserEnabled', true);
    
    $dompdf = new Dompdf\Dompdf($options);
    
    // 3. Generar PDF
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    
    // 4. Guardar archivo
    $outputPath = sys_get_temp_dir() . '/FE_' . $consecutivo . '.pdf';
    file_put_contents($outputPath, $dompdf->output());
    
    return $outputPath;
}

/**
 * Carga y completa la plantilla HTML para el PDF
 */
function cargarPlantillaPDF(array $datos, string $consecutivo, string $clave): string {
    // Datos para la plantilla
    $data = [
        'numero' => $consecutivo,
        'clave' => $clave,
        'fecha' => date('d/m/Y H:i:s'),
        'emisor' => [
            'nombre' => 'CONSULTORÍA INFORMÁTICA', // Nombre de tu empresa
            'identificacion' => $datos['emisor']['identificacion'],
            'ubicacion' => 'San José, Costa Rica'
        ],
        'receptor' => $datos['receptor'],
        'detalles' => $datos['detalle'],
        'totales' => calcularTotales($datos['detalle']),
        'qr' => generarCodigoQR($clave, $datos['emisor']['identificacion'], $datos['receptor']['identificacion'], $totales['total'])
    ];
    
    // Cargar plantilla desde archivo
    ob_start();
    include __DIR__ . '/../templates/factura_pdf.html';
    return ob_get_clean();
}

/**
 * Calcula totales de la factura
 */
function calcularTotales(array $detalles): array {
    $subtotal = array_sum(array_column($detalles, 'monto'));
    $impuestos = array_sum(array_map(fn($item) => $item['monto'] * ($item['impuesto'] / 100), $detalles));
    
    return [
        'subtotal' => $subtotal,
        'impuestos' => $impuestos,
        'total' => $subtotal + $impuestos
    ];
}

/**
 * Genera código QR para validación
 */
function generarCodigoQR(string $clave, string $emisorId, string $receptorId, float $total): string {
    $qrData = implode('|', [
        date('d/m/Y'),
        $emisorId,
        $receptorId,
        $total,
        $clave
    ]);
    
    // Usar librería como chillerlan/php-qrcode
    $qrCode = (new chillerlan\QRCode\QRCode())->render($qrData);
    return 'data:image/png;base64,' . base64_encode($qrCode);
}