<?php
return [
  'hacienda' => [
    'usuario'    => 'cpf-01-1260-0730@stag.comprobanteselectronicos.go.cr',
    'contrasena' => 'RyL+%AE_z2/KKRWOX>/O',
    'client_id'  => 'api-stag',
    'client_secret' => '',
    'url_token'  => 'https://idp.comprobanteselectronicos.go.cr/auth/realms/rut-stag/protocol/openid-connect/token',
    'url_recepcion' => 'https://api-sandbox.comprobanteselectronicos.go.cr/recepcion/v1/recepcion/',
  ],
  'certificado' => [
    'ruta' => __DIR__ . '/certificados/011260073034.p12',
    'clave' => '', // si no tiene contraseña
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

