<?php
declare(strict_types=1);

use SwapFix\Integrations\Supabase\SupabaseClient;

require dirname(__DIR__) . '/bootstrap/app.php';
$services = require dirname(__DIR__) . '/config/services.php';
$supabase = $services['supabase'];
$mail = $services['mail'];
echo 'PHP: ' . PHP_VERSION . PHP_EOL;
echo 'PHPMailer: installed' . PHP_EOL;
echo 'Supabase settings: ' . ($supabase['url'] !== '' && $supabase['publishable_key'] !== '' ? 'present' : 'missing') . PHP_EOL;
echo 'SMTP settings: ' . ($mail['host'] !== '' && $mail['from_address'] !== '' ? 'present (delivery not checked)' : 'missing') . PHP_EOL;
if (in_array('--supabase', $argv, true)) {
    try {
        $client = new SupabaseClient($supabase['url'], $supabase['publishable_key']);
        $client->request('GET', '/rest/v1/product_listings', ['query' => ['select' => 'id', 'limit' => 1]]);
        echo 'Supabase Data API read: successful' . PHP_EOL;
    } catch (Throwable $exception) {
        fwrite(STDERR, $exception->getMessage() . PHP_EOL);
        exit(1);
    }
}
