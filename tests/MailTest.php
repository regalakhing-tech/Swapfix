<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use SwapFix\Integrations\Mail\Mailer;

final class MailTest extends TestCase
{
    public function testEmailCanBePreparedWithoutSending(): void
    {
        $mailer = new Mailer([
            'host' => 'smtp.example.test', 'port' => 587, 'encryption' => 'tls',
            'username' => 'account', 'password' => 'secret',
            'from_address' => 'noreply@example.test', 'from_name' => 'SwapFix',
        ]);
        $message = $mailer->prepare('customer@example.test', 'Repair update', '<p>Your device is ready.</p>', 'Your device is ready.');
        self::assertTrue($message->preSend());
        self::assertStringContainsString('Your device is ready.', $message->getSentMIMEMessage());
        self::assertSame('smtp', $message->Mailer);
        self::assertSame('tls', $message->SMTPSecure);
        self::assertSame(0, $message->SMTPDebug);
        self::assertSame('customer@example.test', $message->getToAddresses()[0][0]);
    }

    public function testMissingSmtpHostFailsClearly(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('SMTP is not configured.');
        (new Mailer([]))->prepare('customer@example.test', 'Test', '<p>Test</p>', 'Test');
    }

    public function testInsecureSmtpConfigurationIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Mailer(['encryption' => 'none']);
    }
}
