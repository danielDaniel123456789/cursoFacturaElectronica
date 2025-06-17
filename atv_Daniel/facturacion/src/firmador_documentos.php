<?php
// src/firmador_documentos.php - Firma digital de XML para facturación electrónica
require_once __DIR__ . '/configuracion.php';

/**
 * Firma un documento XML para factura electrónica
 * @param string $xmlPath Ruta del archivo XML a firmar
 * @return string Ruta del archivo XML firmado
 */
function firmarFactura(string $xmlPath): string {
    $config = include __DIR__ . '/configuracion.php';
    
    // 1. Validar existencia del archivo XML
    if (!file_exists($xmlPath)) {
        throw new Exception('Archivo XML no encontrado: ' . $xmlPath);
    }

    // 2. Validar existencia del certificado
    if (!file_exists($config['certificado']['ruta'])) {
        throw new Exception('Certificado digital no encontrado: ' . $config['certificado']['ruta']);
    }

    // 3. Cargar el XML
    $xml = new DOMDocument();
    $xml->load($xmlPath);

    // 4. Crear nodo para la firma digital
    $signature = crearNodoFirma($xml);
    $xml->documentElement->appendChild($signature);

    // 5. Configurar la firma digital
    $certificado = file_get_contents($config['certificado']['ruta']);
    $claveCertificado = $config['certificado']['clave'] ?? '';

    $pkey = openssl_pkey_get_private($certificado, $claveCertificado);
    if ($pkey === false) {
        throw new Exception('Error al cargar el certificado: ' . openssl_error_string());
    }

    $keyData = openssl_pkey_get_details($pkey);
    $publicKey = $keyData['key'];

    // 6. Calcular digest values y firmar
    $digestValue = base64_encode(sha1($xml->C14N(), true));
    $signatureValue = '';
    $signedInfo = crearSignedInfo($xml, $digestValue);
    
    openssl_sign($signedInfo->C14N(), $signatureValue, $pkey, OPENSSL_ALGO_SHA1);
    $signatureValue = base64_encode($signatureValue);

    // 7. Completar la firma en el XML
    $xpath = new DOMXPath($xml);
    $xpath->registerNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');

    // Insertar valores calculados
    $xpath->query('//ds:Signature/ds:SignedInfo/ds:Reference/ds:DigestValue')[0]->nodeValue = $digestValue;
    $xpath->query('//ds:Signature/ds:SignedInfo/ds:SignatureMethod/ds:SignatureValue')[0]->nodeValue = $signatureValue;
    
    // Insertar certificado y clave pública
    $x509Certificate = $xpath->query('//ds:Signature/ds:KeyInfo/ds:X509Data/ds:X509Certificate')[0];
    $x509Certificate->nodeValue = trim(str_replace(
        ['-----BEGIN CERTIFICATE-----', '-----END CERTIFICATE-----', "\n", "\r"],
        '',
        $publicKey
    ));

    // 8. Guardar el XML firmado
    $signedPath = str_replace('.xml', '_firmado.xml', $xmlPath);
    $xml->save($signedPath);

    // 9. Validar la firma (opcional pero recomendado)
    if (!validarFirma($signedPath)) {
        throw new Exception('La firma digital no es válida');
    }

    return $signedPath;
}

/**
 * Crea el nodo de firma básico con la estructura requerida
 */
function crearNodoFirma(DOMDocument $xml): DOMElement {
    $signature = $xml->createElementNS('http://www.w3.org/2000/09/xmldsig#', 'ds:Signature');
    
    // SignedInfo
    $signedInfo = $xml->createElement('ds:SignedInfo');
    
    // CanonicalizationMethod
    $canonicalizationMethod = $xml->createElement('ds:CanonicalizationMethod');
    $canonicalizationMethod->setAttribute('Algorithm', 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315');
    $signedInfo->appendChild($canonicalizationMethod);
    
    // SignatureMethod
    $signatureMethod = $xml->createElement('ds:SignatureMethod');
    $signatureMethod->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#rsa-sha1');
    $signedInfo->appendChild($signatureMethod);
    
    // Reference
    $reference = $xml->createElement('ds:Reference');
    $reference->setAttribute('URI', '');
    
    // Transforms
    $transforms = $xml->createElement('ds:Transforms');
    $transform1 = $xml->createElement('ds:Transform');
    $transform1->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#enveloped-signature');
    $transforms->appendChild($transform1);
    $reference->appendChild($transforms);
    
    // DigestMethod
    $digestMethod = $xml->createElement('ds:DigestMethod');
    $digestMethod->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#sha1');
    $reference->appendChild($digestMethod);
    
    // DigestValue (se completará después)
    $reference->appendChild($xml->createElement('ds:DigestValue'));
    $signedInfo->appendChild($reference);
    $signature->appendChild($signedInfo);
    
    // SignatureValue (se completará después)
    $signature->appendChild($xml->createElement('ds:SignatureValue'));
    
    // KeyInfo
    $keyInfo = $xml->createElement('ds:KeyInfo');
    $x509Data = $xml->createElement('ds:X509Data');
    $x509Data->appendChild($xml->createElement('ds:X509Certificate'));
    $keyInfo->appendChild($x509Data);
    $signature->appendChild($keyInfo);
    
    return $signature;
}

/**
 * Crea el SignedInfo para calcular la firma
 */
function crearSignedInfo(DOMDocument $xml, string $digestValue): DOMElement {
    $signedInfo = $xml->createElement('ds:SignedInfo');
    
    // CanonicalizationMethod
    $canonicalizationMethod = $xml->createElement('ds:CanonicalizationMethod');
    $canonicalizationMethod->setAttribute('Algorithm', 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315');
    $signedInfo->appendChild($canonicalizationMethod);
    
    // SignatureMethod
    $signatureMethod = $xml->createElement('ds:SignatureMethod');
    $signatureMethod->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#rsa-sha1');
    $signedInfo->appendChild($signatureMethod);
    
    // Reference
    $reference = $xml->createElement('ds:Reference');
    $reference->setAttribute('URI', '');
    
    // Transforms
    $transforms = $xml->createElement('ds:Transforms');
    $transform1 = $xml->createElement('ds:Transform');
    $transform1->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#enveloped-signature');
    $transforms->appendChild($transform1);
    $reference->appendChild($transforms);
    
    // DigestMethod
    $digestMethod = $xml->createElement('ds:DigestMethod');
    $digestMethod->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#sha1');
    $reference->appendChild($digestMethod);
    
    // DigestValue
    $reference->appendChild($xml->createElement('ds:DigestValue', $digestValue));
    $signedInfo->appendChild($reference);
    
    return $signedInfo;
}

/**
 * Valida la firma de un XML firmado
 */
function validarFirma(string $xmlPath): bool {
    $xml = new DOMDocument();
    $xml->load($xmlPath);
    
    $xpath = new DOMXPath($xml);
    $xpath->registerNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');
    
    $signatureNode = $xpath->query('//ds:Signature')[0];
    if (!$signatureNode) {
        return false;
    }
    
    $certificate = $xpath->query('//ds:X509Certificate')[0]->nodeValue;
    $certificate = "-----BEGIN CERTIFICATE-----\n" . chunk_split($certificate, 64, "\n") . "-----END CERTIFICATE-----\n";
    
    $publicKey = openssl_pkey_get_public($certificate);
    if ($publicKey === false) {
        return false;
    }
    
    $signedInfo = $xpath->query('//ds:SignedInfo')[0];
    $signatureValue = $xpath->query('//ds:SignatureValue')[0]->nodeValue;
    $signatureValue = base64_decode($signatureValue);
    
    $canonicalizedSignedInfo = $signedInfo->C14N();
    
    return openssl_verify($canonicalizedSignedInfo, $signatureValue, $publicKey, OPENSSL_ALGO_SHA1) === 1;
}