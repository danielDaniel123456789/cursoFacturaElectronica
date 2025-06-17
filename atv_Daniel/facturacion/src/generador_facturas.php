<?php
// src/generador_facturas.php - Generador de XML para facturas electrónicas
require_once __DIR__ . '/configuracion.php';

/**
 * Genera el XML de la factura electrónica
 * @param array $datos Datos de la factura
 * @param string $consecutivo Número de factura generado
 * @return string Ruta del archivo XML temporal
 */
function generarFacturaXML(array $datos, string $consecutivo): string {
    $config = include __DIR__ . '/configuracion.php';
    
    // 1. Validar datos básicos
    if (empty($datos['emisor']) || empty($datos['receptor']) || empty($datos['detalle'])) {
        throw new Exception('Datos incompletos para generar la factura');
    }

    // 2. Crear estructura base del XML
    $xml = new DOMDocument('1.0', 'UTF-8');
    $xml->formatOutput = true;

    // 3. Crear elemento raíz con namespaces
    $facturaElectronica = $xml->createElementNS('https://cdn.comprobanteselectronicos.go.cr/xml-schemas/v4.3/facturaElectronica', 
                                              'ns:FacturaElectronica');
    $facturaElectronica->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:ds', 'http://www.w3.org/2000/09/xmldsig#');
    $facturaElectronica->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsd', 'http://www.w3.org/2001/XMLSchema');
    $facturaElectronica->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
    $xml->appendChild($facturaElectronica);

    // 4. Agregar Clave numérica (formato: 506 + Fecha + Número consecutivo)
    $clave = generarClave($consecutivo, $config['empresa']['identificacion']);
    $facturaElectronica->appendChild($xml->createElement('Clave', $clave));

    // 5. Agregar sección de Emisor
    $emisor = $xml->createElement('Emisor');
    agregarNodo($emisor, 'Nombre', 'CONSULTORÍA INFORMÁTICA'); // Nombre de tu empresa
    agregarNodo($emisor, 'Identificacion', $xml->createElement('Tipo', $datos['emisor']['tipo_identificacion']));
    agregarNodo($emisor['Identificacion'], 'Numero', $datos['emisor']['identificacion']);
    agregarNodo($emisor, 'NombreComercial', 'CONSULTORES INFORMÁTICOS');
    agregarNodo($emisor, 'Ubicacion', $xml->createElement('Provincia', '1')); // 1 = San José
    agregarNodo($emisor['Ubicacion'], 'Canton', '01'); // 01 = Central
    agregarNodo($emisor['Ubicacion'], 'Distrito', '01'); // 01 = Carmen
    agregarNodo($emisor['Ubicacion'], 'OtrasSenas', 'Oficina Virtual');
    agregarNodo($emisor, 'Telefono', $xml->createElement('CodigoPais', '506'));
    agregarNodo($emisor['Telefono'], 'NumTelefono', '00000000');
    agregarNodo($emisor, 'CorreoElectronico', 'info@consultoria.cr');
    $facturaElectronica->appendChild($emisor);

    // 6. Agregar sección de Receptor
    $receptor = $xml->createElement('Receptor');
    agregarNodo($receptor, 'Nombre', $datos['receptor']['nombre']);
    agregarNodo($receptor, 'Identificacion', $xml->createElement('Tipo', '01')); // 01 = Cédula física
    agregarNodo($receptor['Identificacion'], 'Numero', $datos['receptor']['identificacion']);
    agregarNodo($receptor, 'CorreoElectronico', $datos['receptor']['correo']);
    $facturaElectronica->appendChild($receptor);

    // 7. Agregar detalles de la factura (servicios)
    $detalleServicio = $xml->createElement('DetalleServicio');
    foreach ($datos['detalle'] as $item) {
        $lineaDetalle = $xml->createElement('LineaDetalle');
        agregarNodo($lineaDetalle, 'CodigoComercial', $xml->createElement('Tipo', '04')); // 04 = CABYS
        agregarNodo($lineaDetalle['CodigoComercial'], 'Codigo', $item['codigo']);
        agregarNodo($lineaDetalle, 'Cantidad', '1');
        agregarNodo($lineaDetalle, 'UnidadMedida', 'Sp'); // Servicio profesional
        agregarNodo($lineaDetalle, 'Detalle', $item['descripcion']);
        agregarNodo($lineaDetalle, 'PrecioUnitario', number_format($item['monto'], 5, '.', ''));
        agregarNodo($lineaDetalle, 'MontoTotal', number_format($item['monto'], 5, '.', ''));
        
        // Impuesto
        $impuesto = $xml->createElement('Impuesto');
        agregarNodo($impuesto, 'Codigo', '01'); // 01 = IVA
        agregarNodo($impuesto, 'CodigoTarifa', '08'); // 08 = Tarifa general 13%
        agregarNodo($impuesto, 'Tarifa', number_format($item['impuesto'], 2, '.', ''));
        agregarNodo($impuesto, 'Monto', number_format($item['monto'] * ($item['impuesto'] / 100), 5, '.', ''));
        $lineaDetalle->appendChild($impuesto);
        
        agregarNodo($lineaDetalle, 'SubTotal', number_format($item['monto'], 5, '.', ''));
        $detalleServicio->appendChild($lineaDetalle);
    }
    $facturaElectronica->appendChild($detalleServicio);

    // 8. Agregar resumen de la factura
    $resumenFactura = $xml->createElement('ResumenFactura');
    $totalVenta = array_sum(array_column($datos['detalle'], 'monto'));
    $totalImpuestos = array_sum(array_map(fn($item) => $item['monto'] * ($item['impuesto'] / 100), $datos['detalle']));
    
    agregarNodo($resumenFactura, 'CodigoTipoMoneda', $xml->createElement('CodigoMoneda', 'CRC'));
    agregarNodo($resumenFactura['CodigoTipoMoneda'], 'TipoCambio', '1');
    agregarNodo($resumenFactura, 'TotalServGravados', number_format($totalVenta, 5, '.', ''));
    agregarNodo($resumenFactura, 'TotalServExentos', '0.00000');
    agregarNodo($resumenFactura, 'TotalVenta', number_format($totalVenta, 5, '.', ''));
    agregarNodo($resumenFactura, 'TotalVentaNeta', number_format($totalVenta, 5, '.', ''));
    agregarNodo($resumenFactura, 'TotalImpuestos', number_format($totalImpuestos, 5, '.', ''));
    agregarNodo($resumenFactura, 'TotalComprobante', number_format($totalVenta + $totalImpuestos, 5, '.', ''));
    $facturaElectronica->appendChild($resumenFactura);

    // 9. Agregar información adicional
    $otros = $xml->createElement('Otros');
    agregarNodo($otros, 'OtroTexto', 'Factura generada automáticamente');
    $facturaElectronica->appendChild($otros);

    // 10. Guardar XML temporal
    $tempPath = sys_get_temp_dir() . '/FE_' . $consecutivo . '.xml';
    $xml->save($tempPath);

    return $tempPath;
}

/**
 * Genera la clave numérica requerida por Hacienda
 * Formato: 506 + Fecha DDHHMMSS + Cédula Emisor + Consecutivo
 */
function generarClave(string $consecutivo, string $identificacion): string {
    $fecha = date('dmHis'); // Día, mes, hora, minuto, segundo
    $partes = explode('-', $consecutivo);
    $numeroConsecutivo = end($partes);
    
    return '506' . $fecha . str_pad($identificacion, 12, '0', STR_PAD_LEFT) . $numeroConsecutivo;
}

/**
 * Helper para agregar nodos con validación
 */
function agregarNodo(DOMElement $parent, string $name, $value): void {
    if (is_string($value)) {
        $parent->appendChild($parent->ownerDocument->createElement($name, $value));
    } elseif ($value instanceof DOMElement) {
        $parent->appendChild($value);
    }
}