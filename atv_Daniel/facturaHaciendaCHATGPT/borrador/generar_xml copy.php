<?php
function generarXMLFactura($emisor, $cliente, $detalle, $consecutivo, $clave, $fecha_emision) {
    // Calcular impuestos y total
    $total_iva = round($detalle['monto'] * 0.13, 2);
    $total = round($detalle['monto'] + $total_iva, 2);

    // Crear objeto XML
    $xml = new DOMDocument('1.0', 'utf-8');
    $xml->formatOutput = true;

    // Elemento raíz
    $factura = $xml->createElement('FacturaElectronica');
    $factura->setAttribute('xmlns', 'https://cdn.comprobanteselectronicos.go.cr/xml-schemas/v4.3/facturaElectronica');

    // Datos principales
    $factura->appendChild($xml->createElement('Clave', $clave));
    $factura->appendChild($xml->createElement('CodigoActividad', $emisor['codigo_actividad']));
    $factura->appendChild($xml->createElement('NumeroConsecutivo', $consecutivo));
    $factura->appendChild($xml->createElement('FechaEmision', $fecha_emision));

    // Emisor
    $emisorElem = $xml->createElement('Emisor');
    $emisorElem->appendChild($xml->createElement('Nombre', $emisor['nombre']));
    $identificacion = $xml->createElement('Identificacion');
    $identificacion->appendChild($xml->createElement('Tipo', '01')); // Tipo de identificación (01 = cédula)
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

    // Detalle
    $detalles = $xml->createElement('DetalleServicio');
    $linea = $xml->createElement('LineaDetalle');
    $linea->appendChild($xml->createElement('NumeroLinea', '1'));
    $linea->appendChild($xml->createElement('Codigo', $detalle['cabys']));
    $linea->appendChild($xml->createElement('Cantidad', '1'));
    $linea->appendChild($xml->createElement('UnidadMedida', 'Sp'));
    $linea->appendChild($xml->createElement('Detalle', $detalle['detalle']));
    $linea->appendChild($xml->createElement('PrecioUnitario', number_format($detalle['monto'], 5, '.', '')));
    $linea->appendChild($xml->createElement('MontoTotal', number_format($detalle['monto'], 5, '.', '')));
    $linea->appendChild($xml->createElement('SubTotal', number_format($detalle['monto'], 5, '.', '')));

    $impuesto = $xml->createElement('Impuesto');
    $impuesto->appendChild($xml->createElement('Codigo', '01'));
    $impuesto->appendChild($xml->createElement('Tarifa', '13.00'));
    $impuesto->appendChild($xml->createElement('Monto', number_format($total_iva, 5, '.', '')));
    $linea->appendChild($impuesto);

    $linea->appendChild($xml->createElement('MontoTotalLinea', number_format($total, 5, '.', '')));
    $detalles->appendChild($linea);
    $factura->appendChild($detalles);

    // Resumen
    $resumen = $xml->createElement('ResumenFactura');
    $resumen->appendChild($xml->createElement('CodigoTipoMoneda', 'CRC'));
    $resumen->appendChild($xml->createElement('TotalServGravados', number_format($detalle['monto'], 5, '.', '')));
    $resumen->appendChild($xml->createElement('TotalGravado', number_format($detalle['monto'], 5, '.', '')));
    $resumen->appendChild($xml->createElement('TotalVenta', number_format($detalle['monto'], 5, '.', '')));
    $resumen->appendChild($xml->createElement('TotalVentaNeta', number_format($detalle['monto'], 5, '.', '')));
    $resumen->appendChild($xml->createElement('TotalImpuesto', number_format($total_iva, 5, '.', '')));
    $resumen->appendChild($xml->createElement('TotalComprobante', number_format($total, 5, '.', '')));
    $factura->appendChild($resumen);

    // Añadir a documento y devolver XML string
    $xml->appendChild($factura);
    return $xml->saveXML();
}
