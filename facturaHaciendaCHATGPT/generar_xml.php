<?php
function generarXMLFactura($emisor, $cliente, $detalle, $consecutivo, $clave, $fecha_emision) {
    $total_iva = round($detalle['monto'] * 0.13, 5);
    $total = round($detalle['monto'] + $total_iva, 5);

    $xml = new DOMDocument('1.0', 'utf-8');
    $xml->formatOutput = true;

    $factura = $xml->createElement('FacturaElectronica');
    $factura->setAttribute('xmlns', 'https://cdn.comprobanteselectronicos.go.cr/xml-schemas/v4.3/facturaElectronica');

    $factura->appendChild($xml->createElement('Clave', $clave));
    $factura->appendChild($xml->createElement('CodigoActividad', $emisor['codigo_actividad']));
    $factura->appendChild($xml->createElement('NumeroConsecutivo', $consecutivo));
    $factura->appendChild($xml->createElement('FechaEmision', $fecha_emision));

    // Emisor
    $emisorElem = $xml->createElement('Emisor');
    $emisorElem->appendChild($xml->createElement('Nombre', $emisor['nombre']));
    $identificacion = $xml->createElement('Identificacion');
    $identificacion->appendChild($xml->createElement('Tipo', '01'));
    $identificacion->appendChild($xml->createElement('Numero', $emisor['cedula']));
    $emisorElem->appendChild($identificacion);
    $factura->appendChild($emisorElem);

    // Receptor
    $receptor = $xml->createElement('Receptor');
    $receptor->appendChild($xml->createElement('Nombre', $cliente['nombre']));
    $idReceptor = $xml->createElement('Identificacion');
    $idReceptor->appendChild($xml->createElement('Tipo', '01'));
    $idReceptor->appendChild($xml->createElement('Numero', $cliente['cedula']));
    $receptor->appendChild($idReceptor);
    $receptor->appendChild($xml->createElement('CorreoElectronico', $cliente['correo']));
    $factura->appendChild($receptor);

    // Condición de venta, plazo y medio de pago
    $factura->appendChild($xml->createElement('CondicionVenta', '01')); // 01 = Contado
    $factura->appendChild($xml->createElement('PlazoCredito', '0'));
    $factura->appendChild($xml->createElement('MedioPago', '01')); // 01 = Efectivo

    // Detalle de servicio
    $detalles = $xml->createElement('DetalleServicio');
    $linea = $xml->createElement('LineaDetalle');
    $linea->appendChild($xml->createElement('NumeroLinea', '1'));

    $codigoComercial = $xml->createElement('CodigoComercial');
    $codigoComercial->appendChild($xml->createElement('Tipo', '01')); // CABYS
    $codigoComercial->appendChild($xml->createElement('Codigo', $detalle['cabys']));
    $linea->appendChild($codigoComercial);

    $linea->appendChild($xml->createElement('Cantidad', '1'));
    $linea->appendChild($xml->createElement('UnidadMedida', 'Sp'));
    $linea->appendChild($xml->createElement('Detalle', $detalle['detalle']));
    $linea->appendChild($xml->createElement('PrecioUnitario', number_format($detalle['monto'], 5, '.', '')));
    $linea->appendChild($xml->createElement('MontoTotal', number_format($detalle['monto'], 5, '.', '')));
    $linea->appendChild($xml->createElement('SubTotal', number_format($detalle['monto'], 5, '.', '')));

    // Impuesto
    $impuesto = $xml->createElement('Impuesto');
    $impuesto->appendChild($xml->createElement('Codigo', '01')); // IVA
    $impuesto->appendChild($xml->createElement('Tarifa', '13.00'));
    $impuesto->appendChild($xml->createElement('Monto', number_format($total_iva, 5, '.', '')));
    $linea->appendChild($impuesto);

    $linea->appendChild($xml->createElement('MontoTotalLinea', number_format($total, 5, '.', '')));
    $detalles->appendChild($linea);
    $factura->appendChild($detalles);

    // Resumen
    $resumen = $xml->createElement('ResumenFactura');

    $moneda = $xml->createElement('CodigoTipoMoneda');
    $moneda->appendChild($xml->createElement('Codigo', 'CRC'));
    $moneda->appendChild($xml->createElement('CodigoPais', 'CRI'));
    $resumen->appendChild($moneda);

    $resumen->appendChild($xml->createElement('TotalServGravados', number_format($detalle['monto'], 5, '.', '')));
    $resumen->appendChild($xml->createElement('TotalGravado', number_format($detalle['monto'], 5, '.', '')));
    $resumen->appendChild($xml->createElement('TotalVenta', number_format($detalle['monto'], 5, '.', '')));
    $resumen->appendChild($xml->createElement('TotalVentaNeta', number_format($detalle['monto'], 5, '.', '')));
    $resumen->appendChild($xml->createElement('TotalImpuesto', number_format($total_iva, 5, '.', '')));
    $resumen->appendChild($xml->createElement('TotalComprobante', number_format($total, 5, '.', '')));
    $factura->appendChild($resumen);

    $xml->appendChild($factura);
    return $xml->saveXML();
}
