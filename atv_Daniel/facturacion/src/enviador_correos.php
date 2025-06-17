<?php
// src/enviador_correos.php - Envío de facturas por correo electrónico
require_once __DIR__ . '/configuracion.php';
require_once __DIR__ . '/helpers.php';

class EnviadorCorreos {
    private $mailer;
    private $config;
    
    public function __construct() {
        $this->config = include __DIR__ . '/configuracion.php';
        
        $this->mailer = new PHPMailer\PHPMailer\PHPMailer(true);
        $this->configurarSMTP();
    }
    
    private function configurarSMTP(): void {
        $this->mailer->isSMTP();
        $this->mailer->Host = $this->config['correo']['smtp_host'];
        $this->mailer->SMTPAuth = true;
        $this->mailer->Username = $this->config['correo']['smtp_user'];
        $this->mailer->Password = $this->config['correo']['smtp_pass'];
        $this->mailer->SMTPSecure = $this->config['correo']['encryption'] === 'ssl' 
            ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS 
            : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $this->mailer->Port = $this->config['correo']['smtp_port'];
        $this->mailer->setFrom(
            $this->config['correo']['from_email'],
            $this->config['correo']['from_name']
        );
    }
    
    /**
     * Envía una factura por correo electrónico
     */
    public function enviarFactura(
        string $destinatario,
        string $consecutivo,
        string $xmlPath,
        string $pdfPath,
        array $datosFactura
    ): bool {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($destinatario);
            
            $this->mailer->isHTML(true);
            $this->mailer->Subject = "Factura Electrónica #{$consecutivo}";
            $this->mailer->Body = $this->generarCuerpoCorreo($datosFactura, $consecutivo);
            $this->mailer->AltBody = $this->generarTextoPlano($datosFactura, $consecutivo);
            
            // Adjuntar archivos
            $this->mailer->addAttachment($xmlPath, "FE_{$consecutivo}.xml");
            $this->mailer->addAttachment($pdfPath, "FE_{$consecutivo}.pdf");
            
            // Enviar con tracking
            $this->agregarTracking($consecutivo, $destinatario);
            
            return $this->mailer->send();
            
        } catch (Exception $e) {
            registrarError("Error enviando correo: " . $this->mailer->ErrorInfo);
            return false;
        }
    }
    
    private function generarCuerpoCorreo(array $datos, string $consecutivo): string {
        $totales = calcularTotales($datos['detalle']);
        
        ob_start();
        include __DIR__ . '/../templates/email_factura.html';
        return ob_get_clean();
    }
    
    private function generarTextoPlano(array $datos, string $consecutivo): string {
        return sprintf(
            "Factura Electrónica #%s\n\n" .
            "Cliente: %s\n" .
            "Fecha: %s\n" .
            "Monto Total: ₡%s\n\n" .
            "Adjunto encontrará:\n" .
            "- XML de la factura electrónica\n" .
            "- PDF con la representación gráfica\n\n" .
            "Este es un mensaje automático, por favor no responda.",
            $consecutivo,
            $datos['receptor']['nombre'],
            date('d/m/Y'),
            number_format($totales['total'], 2)
        );
    }
    
    private function agregarTracking(string $consecutivo, string $destinatario): void {
        // Pixel de tracking
        $trackingUrl = $this->config['correo']['tracking_url'] . 
                      "?id=" . urlencode($consecutivo) . 
                      "&dest=" . urlencode($destinatario);
        
        $trackingPixel = "<img src='{$trackingUrl}' width='1' height='1' alt=''>";
        $this->mailer->Body .= $trackingPixel;
        
        // Encabezados para tracking
        $this->mailer->addCustomHeader('X-Factura-ID', $consecutivo);
        $this->mailer->addCustomHeader('X-Cliente-Email', $destinatario);
    }
    
    /**
     * Verifica el estado de entrega de un correo
     */
    public function verificarEstado(string $consecutivo): ?array {
        // Implementación con API de tracking (Postmark, SendGrid, etc.)
        // Este es un ejemplo básico
        $logFile = __DIR__ . '/../logs/email_tracking.log';
        
        if (!file_exists($logFile)) {
            return null;
        }
        
        $logs = file($logFile, FILE_IGNORE_NEW_LINES);
        foreach ($logs as $log) {
            if (strpos($log, $consecutivo) !== false) {
                return json_decode($log, true);
            }
        }
        
        return null;
    }
}