<?php
return [
  'hacienda' => [
    'usuario'    => 'cpf-01-1260-0730@prod.comprobanteselectronicos.go.cr',
    'contrasena' => '}k)S+16+8j|BYu1-;SE-',
    'client_id'  => 'api-prod',
    'client_secret' => '',
    'url_token'  => 'https://idp.comprobanteselectronicos.go.cr/auth/realms/rut/protocol/openid-connect/token',
    'url_recepcion' => 'https://api.comprobanteselectronicos.go.cr/recepcion/v1/',
  ],
  'certificado' => [
    'ruta' => __DIR__ . '/certificados/011260073034.p12',
    'clave' => '', // Pon la contraseña del .p12 si tiene, o deja vacío
  ],
  'emisor' => [
    'nombre' => 'Daniel Ballestero',
    'cedula' => '112600730',
    'telefono' => '85502748',
    'correo' => 'asistencia@facturahacienda.com',
    'codigo_actividad' => '721001',
    'nombre_actividad' => 'CONSULTORES INFORMATICOS',
  ]
];
