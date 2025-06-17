<?php
// src/helpers.php - Versión corregida

/**
 * Calcula totales de factura
 */
function calcularTotales(array $detalles): array {
    $subtotal = array_sum(array_column($detalles, 'monto'));
    $impuestos = array_sum(array_map(
        function($item) {
            return $item['monto'] * ($item['impuesto'] / 100);
        }, 
        $detalles
    ));
    return [
        'subtotal' => $subtotal,
        'impuestos' => $impuestos,
        'total' => $subtotal + $impuestos
    ];
}

/**
 * Extrae clave de un XML
 */
function extraerClaveFromXML(string $xmlContent): string {
    $xml = new DOMDocument();
    $xml->loadXML($xmlContent);
    $claveNode = $xml->getElementsByTagName('Clave')->item(0);
    if (!$claveNode) {
        throw new Exception('No se encontró la clave en el XML');
    }
    return $claveNode->nodeValue;
}