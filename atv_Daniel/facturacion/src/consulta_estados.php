<?php
// src/consulta_estados.php - Consulta estado de facturas electrónicas
require_once __DIR__ . '/configuracion.php';

/**
 * Consulta el estado de una factura electrónica en Hacienda
 * @param string $clave Clave numérica de la factura (506DDMMHHMMSSCedulaConsecutivo)
 * @return array Respuesta de Hacienda con el estado
 */
function consultarEstadoFactura(string $clave): array {
    $config = include __DIR__ . '/configuracion.php';
    
    // 1. Validar formato de la clave
    if (!preg_match('/^506\d{26}$/', $clave)) {
        throw new InvalidArgumentException('Formato de clave inválido');
    }

    // 2. Obtener token de acceso
    $token = obtenerTokenHacienda($config);
    
    // 3. Construir URL de consulta
    $urlConsulta = $config['api']['recepcion_url'] . 'recepcion/' . urlencode($clave);
    
    // 4. Realizar consulta
    $respuesta = hacerConsultaAPI($urlConsulta, $token);
    
    // 5. Interpretar respuesta
    return interpretarRespuestaEstado($respuesta);
}

/**
 * Obtiene token de acceso para la API
 */
function obtenerTokenHacienda(array $config): string {
    static $tokenCache = null;
    
    if ($tokenCache !== null) {
        return $tokenCache;
    }

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
        throw new RuntimeException('Error de autenticación con Hacienda: ' . json_encode($response));
    }

    $tokenCache = $response['access_token'];
    return $tokenCache;
}

/**
 * Realiza la consulta a la API de Hacienda
 */
function hacerConsultaAPI(string $url, string $token): array {
    $headers = [
        'Authorization: Bearer ' . $token,
        'Accept: application/json'
    ];

    $response = hacerRequest($url, 'GET', null, $headers);
    
    if ($response['status'] !== 200) {
        throw new RuntimeException('Error en consulta a Hacienda: ' . json_encode($response));
    }

    return $response['response'];
}

/**
 * Interpreta la respuesta de Hacienda sobre el estado
 */
function interpretarRespuestaEstado(array $respuesta): array {
    $estadosHacienda = [
        'aceptada' => ['Aceptada', 'La factura fue aceptada correctamente'],
        'rechazada' => ['Rechazada', 'La factura fue rechazada por Hacienda'],
        'recibida' => ['Recibida', 'La factura está en proceso de validación'],
        'procesando' => ['Procesando', 'La factura está siendo procesada'],
        'error' => ['Error', 'Ocurrió un error al procesar la factura']
    ];

    $estado = strtolower($respuesta['estado'] ?? 'desconocido');
    
    return [
        'clave' => $respuesta['clave'] ?? '',
        'fecha' => $respuesta['fecha'] ?? '',
        'estado' => $estadosHacienda[$estado][0] ?? 'Desconocido',
        'detalle' => $respuesta['respuesta-xml'] ?? ($estadosHacienda[$estado][1] ?? 'Estado no reconocido'),
        'completo' => $respuesta
    ];
}

/**
 * Función genérica para requests HTTP (debería estar en helpers.php)
 */
function hacerRequest(string $url, string $method, $data = null, array $headers = []): array {
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    
    if ($data !== null) {
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
        throw new RuntimeException("Error en la petición: $error");
    }
    
    curl_close($ch);
    
    return [
        'status' => $httpCode,
        'response' => json_decode($response, true) ?? $response
    ];
}