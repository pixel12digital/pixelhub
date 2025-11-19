<?php

use PixelHub\Core\Env;

// Carrega .env se ainda não foi carregado
Env::load();

return [
    'host' => Env::get('DB_HOST', 'localhost'),
    'port' => Env::get('DB_PORT', '3306'),
    'database' => Env::get('DB_NAME', 'pixel_hub'),
    'username' => Env::get('DB_USER', 'root'),
    'password' => Env::get('DB_PASS', ''),
    'charset' => Env::get('DB_CHARSET', 'utf8mb4'),
];

