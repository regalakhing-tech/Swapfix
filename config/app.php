<?php
declare(strict_types=1);

use SwapFix\Support\Env;

return [
    'name' => Env::get('APP_NAME', 'SwapFix'),
    'environment' => Env::get('APP_ENV', 'production'),
    'url' => rtrim(Env::get('APP_URL', 'http://127.0.0.1:8000'), '/'),
    'timezone' => Env::get('APP_TIMEZONE', 'Asia/Manila'),
    'key' => Env::get('APP_KEY'),
];
