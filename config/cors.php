<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Aquí defines qué orígenes pueden hacer peticiones a tu API, qué métodos,
    | qué cabeceras, y si admites el envío de credenciales (cookies).
    |
    */

    // Rutas que aceptan CORS
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    // Métodos HTTP permitidos
    'allowed_methods' => ['*'],

    // Orígenes permitidos (ajustado a tu frontend)
    'allowed_origins' => [
        'http://localhost:3000',
        'http://127.0.0.1:3000',
        'http://192.168.1.205:3000',
        'https://www.systemamericanschool.com',
        'https://systemamericanschool.com',
        'https://api.systemamericanschool.com',
    ],

    // Patrónes de orígenes permitidos (vacío aquí)
    'allowed_origins_patterns' => [],

    // Cabeceras permitidas
    'allowed_headers' => ['*'],

    // Cabeceras expuestas al navegador
    'exposed_headers' => [],

    // Tiempo (en segundos) que un preflight puede guardarse en caché
    'max_age' => 0,

    // IMPORTANTE: permite enviar cookies de sesión (Sanctum) entre dominios
    'supports_credentials' => true,
];
