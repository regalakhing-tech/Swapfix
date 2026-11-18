<?php
declare(strict_types=1);

use SwapFix\Integrations\Mail\Mailer;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__) . '/bootstrap/app.php';
$recipient = $argv[1] ?? '';
if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, 'Usage: php scripts/mail-test.php your-test-address@example.com' . PHP_EOL);
    exit(1);
}
$services = require dirname(__DIR__) . '/config/services.php';
try {
    (new Mailer($services['mail']))->send($recipient, 'SwapFix SMTP test', '<p>Your SwapFix SMTP configuration is working.</p>', 'Your SwapFix SMTP configuration is working.');
    echo 'The SMTP server accepted the message. Check the destination inbox.' . PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}
