<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    // Lista de orígenes permitidos: agrega los dominios donde se ejecuta tu frontend
    'allowed_origins' => [
        'http://localhost:3000',
        'http://127.0.0.1:3000',
        'http://192.168.1.205:3000', // Este cubre también subrutas, ej: http://192.168.1.205:3000/captura
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // Importante: habilita el soporte de credenciales para enviar cookies
    'supports_credentials' => true,
];
