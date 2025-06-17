<?php
// src/cliente_api_hacienda.php - Cliente para API de Hacienda Costa Rica
require_once __DIR__ . '/configuracion.php';

/**
 * Envía una factura firmada a la API de Hacienda
 * @param string $xmlFirmadoPath Ruta del archivo XML firmado
 * @return array Respuesta de Hacienda
 */
function enviarFacturaHacienda(string $xmlFirmadoPath): array {
    $config = include __DIR__ . '/configuracion.php';

    // 1. Validar existencia del archivo
    if (!file_exists($xmlFirmadoPath)) {
        throw new Exception('Archivo XML firmado no encontrado: ' . $xmlFirmadoPath);
    }

    // 2. Obtener token de acceso
    $token = obtenerTokenHacienda($config);
    
    // 3. Preparar datos para el envío
    $xmlContent = file_get_contents($xmlFirmadoPath);
    $clave = extraerClaveFromXML($xmlContent);
    
    // 4. Enviar a API de recepción
    $response = enviarAPI(
        $config['api']['recepcion_url'] . 'recepcion',
        $xmlContent,
        $token,
        $clave
    );

    // 5. Procesar respuesta
    return procesarRespuestaAPI($response);
}

/**
 * Obtiene token de acceso del IDP de Hacienda
 */
function obtenerTokenHacienda(array $config): string {
    $authData = [
        'client_id' => $config['credenciales']['client_id'],
        'grant_type' => 'password',
        'username' => $config['credenciales']['usuario'],
        'password' => $config['credenciales']['password']
    ];

    $response = hacerRequest(
        $config['api']['token_url'],
        'POST',
        $authData,
        ['Content-Type: application/x-www-form-urlencoded']
    );

    if (!isset($response['access_token'])) {
        registrarError('Error obteniendo token: ' . json_encode($response));
        throw new Exception('Error de autenticación con Hacienda');
    }

    return $response['access_token'];
}

/**
 * Envía el XML a la API de Hacienda
 */
function enviarAPI(string $url, string $xmlContent, string $token, string $clave): array {
    $headers = [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/xml',
        'clave: ' . $clave
    ];

    return hacerRequest($url, 'POST', $xmlContent, $headers);
}

/**
 * Ejecuta una petición HTTP
 */
function hacerRequest(string $url, string $method, $data, array $headers = []) {
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($data) ? http_build_query($data) : $data);
    }
    
    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new Exception('Error en la petición: ' . $error);
    }
    
    curl_close($ch);
    
    $decodedResponse = json_decode($response, true) ?? $response;
    
    return [
        'status' => $httpCode,
        'response' => $decodedResponse
    ];
}

/**
 * Procesa la respuesta de la API
 */
function procesarRespuestaAPI(array $apiResponse): array {
    $httpCode = $apiResponse['status'];
    $response = $apiResponse['response'];
    
    if ($httpCode === 202) {
        return [
            'estado' => 'aceptada',
            'respuesta' => $response
        ];
    }
    
    if ($httpCode === 200) {
        return [
            'estado' => 'procesada',
            'respuesta' => $response
        ];
    }
    
    if (is_array($response) && isset($response['error_description'])) {
        registrarError('Error API Hacienda: ' . $response['error_description']);
        throw new Exception($response['error_description']);
    }
    
    registrarError('Error desconocido de API: ' . json_encode($apiResponse));
    throw new Exception('Error desconocido al comunicarse con Hacienda');
}

/**
 * Extrae la clave del documento XML
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

/**
 * Registra errores en el log
 */
function registrarError(string $mensaje): void {
    $logDir = __DIR__ . '/../logs';
    if (!file_exists($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $logFile = $logDir . '/errores_hacienda_' . date('Y-m-d') . '.log';
    $mensaje = '[' . date('Y-m-d H:i:s') . '] ' . $mensaje . PHP_EOL;
    
    file_put_contents($logFile, $mensaje, FILE_APPEND);
}