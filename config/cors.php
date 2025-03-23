<?php

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    // Cambia esto según la URL de tu frontend en desarrollo
    'allowed_origins' => ['http://localhost:3000'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // Si vas a usar cookies o tokens con credenciales, cámbialo a true
    'supports_credentials' => true,

];
