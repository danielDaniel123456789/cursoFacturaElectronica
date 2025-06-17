<?php
// src/gestion_consecutivos.php - Manejo de números consecutivos para facturación electrónica

const ARCHIVO_CONSECUTIVO = __DIR__ . '/../consecutivo.json';
const PREFIJO = 'FE'; // Factura Electrónica

/**
 * Obtiene el próximo número consecutivo para facturación
 * Formato requerido por Hacienda: FE-001-000-000000001
 */
function obtenerConsecutivo(): string {
    // 1. Cargar o inicializar el consecutivo
    $datos = file_exists(ARCHIVO_CONSECUTIVO) 
        ? json_decode(file_get_contents(ARCHIVO_CONSECUTIVO), true) 
        : ['ultimo' => 0, 'fecha' => date('Y-m-d')];
    
    // 2. Reiniciar secuencia si es un nuevo día
    if ($datos['fecha'] !== date('Y-m-d')) {
        $datos['ultimo'] = 0;
        $datos['fecha'] = date('Y-m-d');
    }
    
    // 3. Incrementar el consecutivo
    $datos['ultimo']++;
    
    // 4. Guardar el nuevo valor
    file_put_contents(ARCHIVO_CONSECUTIVO, json_encode($datos));
    
    // 5. Formatear según requisitos de Hacienda
    return formatearConsecutivo($datos['ultimo']);
}

/**
 * Formatea el número consecutivo según especificaciones de Hacienda
 * Ejemplo: FE-001-001-000000123
 */
function formatearConsecutivo(int $numero): string {
    $config = include __DIR__ . '/configuracion.php';
    
    $partes = [
        PREFIJO, // FE para Factura Electrónica
        str_pad($config['empresa']['tipo_identificacion'], 3, '0', STR_PAD_LEFT), // Tipo ID
        str_pad(substr($config['empresa']['identificacion'], 0, 3), 3, '0', STR_PAD_LEFT), // 3 primeros dígitos
        str_pad($numero, 9, '0', STR_PAD_LEFT) // Número secuencial
    ];
    
    return implode('-', $partes);
}

/**
 * Valida la estructura de un consecutivo
 */
function validarConsecutivo(string $consecutivo): bool {
    return preg_match('/^FE-\d{3}-\d{3}-\d{9}$/', $consecutivo) === 1;
}