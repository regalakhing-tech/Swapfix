<?php
declare(strict_types=1);

namespace SwapFix\Modules\Notifications;

use SwapFix\Integrations\Supabase\Database;
use SwapFix\Support\IntegrationException;

/** Sends queued notification email to opted-in recipients. Addresses come only from Auth records. */
final class MailDispatcher
{
    /** @param callable(string,string,string,string):void $send */
    public function __construct(private readonly Database $db, private $send, private readonly string $appUrl) {}

    /** @return array{sent:int, failed:int} */
    public function dispatch(int $limit = 20): array
    {
        $result = ['sent' => 0, 'failed' => 0];
        foreach ($this->db->rpc('sf_mail_claim', ['p_limit' => $limit]) as $item) {
            $url = $this->appUrl . $item['link_path'];
            $name = (string) $item['display_name'];
            $html = self::html($name, (string) $item['title'], (string) $item['body'], $url);
            $text = "Hello {$name},\n\n{$item['title']}\n{$item['body']}\n\nOpen SwapFix: {$url}\n";
            try {
                ($this->send)((string) $item['email'], 'SwapFix: ' . $item['title'], $html, $text);
                $sent = true;
            } catch (IntegrationException | \RuntimeException) {
                $sent = false;
            }
            $this->db->rpc('sf_mail_mark', ['p_outbox_id' => $item['outbox_id'], 'p_sent' => $sent]);
            $result[$sent ? 'sent' : 'failed']++;
        }
        return $result;
    }

    private static function html(string $name, string $title, string $body, string $url): string
    {
        ob_start();
        require dirname(__DIR__, 3) . '/resources/views/emails/notification.php';
        return (string) ob_get_clean();
    }
}
