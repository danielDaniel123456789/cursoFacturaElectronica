<?php
function firmarXML($xmlString, $rutaCertificado, $claveCertificado) {
    $tempDir = sys_get_temp_dir();
    $archivoXML = $tempDir . '/factura.xml';
    $archivoFirmado = $tempDir . '/factura_firmada.xml';

    // Guardar el XML original
    file_put_contents($archivoXML, $xmlString);

    // Comando para firmar con xmlsec1
    $comando = escapeshellcmd("xmlsec1 --sign --output " . escapeshellarg($archivoFirmado) . " " .
        "--pkcs12 " . escapeshellarg($rutaCertificado) . " " .
        "--pwd " . escapeshellarg($claveCertificado) . " " .
        "--node-xpath \"//*[local-name()='FacturaElectronica']\" " .
        "--enabled-reference-uris empty,same-doc " .
        escapeshellarg($archivoXML));

    exec($comando, $output, $returnCode);

    if ($returnCode !== 0) {
        return [false, "❌ Error al firmar XML:\n" . implode("\n", $output)];
    }

    $xmlFirmado = file_get_contents($archivoFirmado);

    // Limpiar archivos temporales
    @unlink($archivoXML);
    @unlink($archivoFirmado);

    return [true, $xmlFirmado];
}
