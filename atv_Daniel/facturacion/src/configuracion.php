<?php
// Configuración para API de Hacienda Costa Rica (PRODUCCIÓN)
return [
    
    'api' => [
        'recepcion_url' => 'https://api.comprobanteselectronicos.go.cr/recepcion/v1/',
        'token_url' => 'https://idp.comprobanteselectronicos.go.cr/auth/realms/rut/protocol/openid-connect/token'
    ],
    
    'credenciales' => [
        'usuario' => 'cpf-01-1260-0730@prod.comprobanteselectronicos.go.cr',
        'password' => '}k)S+16+8j|BYu1-;SE-',
        'client_id' => 'api-prod',
        'client_secret' => ''
    ],
    
    'certificado' => [
        'ruta' => __DIR__ . '/../certificados/certificado.p12',
        'clave' => '' // Clave del certificado p12 (si aplica)
    ],
    
    'ambiente' => 'produccion',
    
    // Configuración adicional
    'empresa' => [
        'identificacion' => '012600730', // Sin cédula jurídica (cpf-01-1260-0730)
        'tipo_identificacion' => '02' // 01=Físico, 02=Jurídico, 03=DIMEX, 04=NITE
    ]
];