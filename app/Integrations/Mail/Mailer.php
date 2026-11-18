<?php
declare(strict_types=1);

namespace SwapFix\Integrations\Mail;

use PHPMailer\PHPMailer\PHPMailer;
use SwapFix\Support\IntegrationException;

final class Mailer
{
    public function __construct(private readonly array $config)
    {
        if (!in_array($config['encryption'] ?? 'tls', ['tls', 'ssl'], true)) {
            throw new \InvalidArgumentException('SMTP encryption must be tls or ssl.');
        }
    }

    public function prepare(string $recipient, string $subject, string $html, string $plainText): PHPMailer
    {
        if (empty($this->config['host']) || empty($this->config['from_address'])) {
            throw new \RuntimeException('SMTP is not configured.');
        }
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $this->config['host'];
        $mail->Port = (int) ($this->config['port'] ?? 587);
        $mail->SMTPAuth = !empty($this->config['username']);
        $mail->Username = $this->config['username'] ?? '';
        $mail->Password = $this->config['password'] ?? '';
        $mail->SMTPSecure = ($this->config['encryption'] ?? 'tls') === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->SMTPDebug = 0;
        $mail->Timeout = 15;
        $mail->CharSet = PHPMailer::CHARSET_UTF8;
        $mail->setFrom($this->config['from_address'], $this->config['from_name'] ?? 'SwapFix');
        $mail->addAddress($recipient);
        $mail->Subject = $subject;
        $mail->isHTML(true);
        $mail->Body = $html;
        $mail->AltBody = $plainText;
        return $mail;
    }

    public function send(string $recipient, string $subject, string $html, string $plainText): void
    {
        try {
            $this->prepare($recipient, $subject, $html, $plainText)->send();
        } catch (\PHPMailer\PHPMailer\Exception) {
            throw new IntegrationException('Email delivery failed. Check the SMTP configuration.');
        }
    }
}
