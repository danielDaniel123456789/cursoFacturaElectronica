<?php
// src/registro_facturas.php - Registro de facturas en base de datos
require_once __DIR__ . '/configuracion.php';
require_once __DIR__ . '/helpers.php';

class RegistroFacturas {
    private $pdo;
    
    public function __construct() {
        $config = include __DIR__ . '/configuracion.php';
        $dsn = "mysql:host={$config['db']['host']};dbname={$config['db']['database']};charset=utf8mb4";
        
        try {
            $this->pdo = new PDO($dsn, $config['db']['username'], $config['db']['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
        } catch (PDOException $e) {
            registrarError("Error de conexión a BD: " . $e->getMessage());
            throw new RuntimeException("No se pudo conectar a la base de datos");
        }
    }

    /**
     * Registra una nueva factura en la base de datos
     */
    public function registrar(array $datosFactura, string $consecutivo, array $respuestaHacienda): bool {
        try {
            $stmt = $this->pdo->prepare("INSERT INTO facturas (
                consecutivo, clave, fecha, estado_hacienda,
                emisor_id, emisor_nombre,
                receptor_id, receptor_nombre, receptor_email,
                monto_total, impuestos, xml_path, pdf_path, respuesta_api
            ) VALUES (
                :consecutivo, :clave, NOW(), :estado_hacienda,
                :emisor_id, :emisor_nombre,
                :receptor_id, :receptor_nombre, :receptor_email,
                :monto_total, :impuestos, :xml_path, :pdf_path, :respuesta_api
            )");
            
            $totales = calcularTotales($datosFactura['detalle']);
            
            return $stmt->execute([
                ':consecutivo' => $consecutivo,
                ':clave' => $respuestaHacienda['clave'] ?? '',
                ':estado_hacienda' => $respuestaHacienda['estado'] ?? 'pendiente',
                ':emisor_id' => $datosFactura['emisor']['identificacion'],
                ':emisor_nombre' => 'CONSULTORÍA INFORMÁTICA', // Nombre de tu empresa
                ':receptor_id' => $datosFactura['receptor']['identificacion'],
                ':receptor_nombre' => $datosFactura['receptor']['nombre'],
                ':receptor_email' => $datosFactura['receptor']['correo'],
                ':monto_total' => $totales['total'],
                ':impuestos' => $totales['impuestos'],
                ':xml_path' => $this->guardarArchivo('xml', $consecutivo, $respuestaHacienda['xml'] ?? ''),
                ':pdf_path' => $this->guardarArchivo('pdf', $consecutivo, $respuestaHacienda['pdf'] ?? ''),
                ':respuesta_api' => json_encode($respuestaHacienda)
            ]);
            
        } catch (PDOException $e) {
            registrarError("Error al registrar factura: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Consulta facturas por criterios
     */
    public function consultar(array $filtros = [], int $limit = 100): array {
        $where = [];
        $params = [];
        
        if (!empty($filtros['consecutivo'])) {
            $where[] = 'consecutivo LIKE :consecutivo';
            $params[':consecutivo'] = "%{$filtros['consecutivo']}%";
        }
        
        if (!empty($filtros['cliente_id'])) {
            $where[] = 'receptor_id = :cliente_id';
            $params[':cliente_id'] = $filtros['cliente_id'];
        }
        
        if (!empty($filtros['fecha_desde'])) {
            $where[] = 'fecha >= :fecha_desde';
            $params[':fecha_desde'] = $filtros['fecha_desde'];
        }
        
        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $stmt = $this->pdo->prepare(
            "SELECT * FROM facturas {$whereClause} ORDER BY fecha DESC LIMIT :limit"
        );
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    /**
     * Actualiza el estado de una factura
     */
    public function actualizarEstado(string $clave, string $estado, string $respuestaHacienda): bool {
        try {
            $stmt = $this->pdo->prepare(
                "UPDATE facturas SET 
                 estado_hacienda = :estado,
                 respuesta_api = :respuesta,
                 fecha_actualizacion = NOW()
                 WHERE clave = :clave"
            );
            
            return $stmt->execute([
                ':estado' => $estado,
                ':respuesta' => $respuestaHacienda,
                ':clave' => $clave
            ]);
            
        } catch (PDOException $e) {
            registrarError("Error al actualizar estado: " . $e->getMessage());
            return false;
        }
    }
    
    private function guardarArchivo(string $tipo, string $consecutivo, string $contenido = ''): string {
        $dir = __DIR__ . "/../storage/{$tipo}_facturas";
        if (!file_exists($dir)) {
            mkdir($dir, 0755, true);
        }
        
        $filename = "FE_{$consecutivo}.{$tipo}";
        $path = "{$dir}/{$filename}";
        
        if ($contenido) {
            file_put_contents($path, $tipo === 'pdf' ? base64_decode($contenido) : $contenido);
        }
        
        return $path;
    }
}