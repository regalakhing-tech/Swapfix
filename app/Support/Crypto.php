<?php
declare(strict_types=1);

namespace SwapFix\Support;

/** Authenticated cookie encryption and CSRF signing derived from APP_KEY. */
final class Crypto
{
    private const CIPHER = 'aes-256-gcm';
    private readonly string $encryptionKey;
    private readonly string $signingKey;

    public function __construct(string $appKey)
    {
        $raw = str_starts_with($appKey, 'base64:') ? base64_decode(substr($appKey, 7), true) : $appKey;
        if (!is_string($raw) || strlen($raw) < 32) {
            throw new \InvalidArgumentException('APP_KEY must contain at least 32 bytes.');
        }
        $this->encryptionKey = hash_hkdf('sha256', $raw, 32, 'swapfix:cookie-encryption');
        $this->signingKey = hash_hkdf('sha256', $raw, 32, 'swapfix:csrf-signing');
    }

    public function encrypt(array $payload, string $purpose): string
    {
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt(json_encode($payload, JSON_THROW_ON_ERROR), self::CIPHER, $this->encryptionKey, OPENSSL_RAW_DATA, $iv, $tag, $purpose, 16);
        if ($cipher === false) {
            throw new \RuntimeException('Encryption failed.');
        }
        return rtrim(strtr(base64_encode($iv . $tag . $cipher), '+/', '-_'), '=');
    }

    public function decrypt(string $value, string $purpose): ?array
    {
        $raw = base64_decode(strtr($value, '-_', '+/'), true);
        if ($raw === false || strlen($raw) < 29) {
            return null;
        }
        $plain = openssl_decrypt(substr($raw, 28), self::CIPHER, $this->encryptionKey, OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16), $purpose);
        if ($plain === false) {
            return null;
        }
        $data = json_decode($plain, true);
        return is_array($data) ? $data : null;
    }

    public function sign(string $value): string
    {
        return hash_hmac('sha256', $value, $this->signingKey);
    }
}
