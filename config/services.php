<?php
declare(strict_types=1);

use SwapFix\Support\Env;

return [
    'supabase' => [
        'url' => Env::get('SUPABASE_URL'),
        'publishable_key' => Env::get('SUPABASE_PUBLISHABLE_KEY'),
        'upload_max_bytes' => (int) Env::get('UPLOAD_MAX_BYTES', '4194304'),
    ],
    'mail' => [
        'host' => Env::get('SMTP_HOST'),
        'port' => (int) Env::get('SMTP_PORT', '587'),
        'encryption' => Env::get('SMTP_ENCRYPTION', 'tls'),
        'username' => Env::get('SMTP_USERNAME'),
        'password' => Env::get('SMTP_PASSWORD'),
        'from_address' => Env::get('MAIL_FROM_ADDRESS'),
        'from_name' => Env::get('MAIL_FROM_NAME', 'SwapFix'),
    ],
    // Optional notification email worker. Never used for user requests.
    'mail_worker' => [
        'secret_key' => Env::get('SUPABASE_SECRET_KEY'),
        'cron_secret' => Env::get('CRON_SECRET'),
    ],
];
