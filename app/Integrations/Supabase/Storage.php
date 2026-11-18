<?php
declare(strict_types=1);

namespace SwapFix\Integrations\Supabase;

final class Storage
{
    private const BUCKETS = ['avatars', 'listing-images', 'repair-images'];
    private const TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    public function __construct(private readonly SupabaseClient $client, private readonly Auth $auth, private readonly int $maxBytes = 5242880)
    {
        if ($maxBytes < 1 || $maxBytes > 5242880) {
            throw new \InvalidArgumentException('Upload limit must be between 1 byte and 5 MiB.');
        }
    }

    public function upload(string $accessToken, string $temporaryPath, string $bucket): array
    {
        if (!in_array($bucket, self::BUCKETS, true) || !is_file($temporaryPath) || !is_readable($temporaryPath)) {
            throw new \InvalidArgumentException('Invalid upload.');
        }
        $size = filesize($temporaryPath);
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($temporaryPath);
        if ($size === false || $size < 1 || $size > $this->maxBytes || !isset(self::TYPES[$mime]) || @getimagesize($temporaryPath) === false) {
            throw new \InvalidArgumentException('Upload a JPEG, PNG or WebP image up to 5 MiB.');
        }
        $user = $this->auth->user($accessToken);
        $path = $user['id'] . '/' . bin2hex(random_bytes(16)) . '.' . self::TYPES[$mime];
        $stream = fopen($temporaryPath, 'rb');
        if ($stream === false) {
            throw new \RuntimeException('The upload could not be read.');
        }
        try {
            $this->client->request('POST', '/storage/v1/object/' . $bucket . '/' . $path, [
                'headers' => ['Content-Type' => $mime, 'x-upsert' => 'false'], 'body' => $stream,
            ], $accessToken);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
        return ['bucket' => $bucket, 'path' => $path];
    }
}
