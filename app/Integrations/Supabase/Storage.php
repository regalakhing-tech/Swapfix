<?php
declare(strict_types=1);

namespace SwapFix\Integrations\Supabase;

final class Storage
{
    private const BUCKETS = ['avatars', 'listing-images', 'repair-images', 'offer-images'];
    private const TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    private const IMAGE_TYPES = ['image/jpeg' => IMAGETYPE_JPEG, 'image/png' => IMAGETYPE_PNG, 'image/webp' => IMAGETYPE_WEBP];
    private const MAX_DIMENSION = 12000;
    private const PATH = '~^[a-f0-9-]{36}/[a-f0-9]{32}\.(jpg|png|webp)$~';

    public function __construct(private readonly SupabaseClient $client, private readonly Auth $auth, private readonly int $maxBytes = 5242880)
    {
        if ($maxBytes < 1 || $maxBytes > 5242880) {
            throw new \InvalidArgumentException('Upload limit must be between 1 byte and 5 MiB.');
        }
    }

    /** Pass $userId only when the caller already holds a verified identity for this token. */
    public function upload(string $accessToken, string $temporaryPath, string $bucket, ?string $userId = null): array
    {
        if (!in_array($bucket, self::BUCKETS, true) || !is_file($temporaryPath) || !is_readable($temporaryPath)) {
            throw new \InvalidArgumentException('Invalid upload.');
        }
        $size = filesize($temporaryPath);
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($temporaryPath);
        $image = @getimagesize($temporaryPath);
        if ($size === false || $size < 1 || $size > $this->maxBytes || !isset(self::TYPES[$mime]) || $image === false
            || $image[2] !== self::IMAGE_TYPES[$mime] || $image[0] < 1 || $image[1] < 1
            || $image[0] > self::MAX_DIMENSION || $image[1] > self::MAX_DIMENSION) {
            throw new \InvalidArgumentException('Upload a JPEG, PNG or WebP image up to 5 MiB.');
        }
        $userId ??= $this->auth->user($accessToken)['id'];
        $path = strtolower($userId) . '/' . bin2hex(random_bytes(16)) . '.' . self::TYPES[$mime];
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

    public function publicUrl(string $bucket, string $path): string
    {
        $this->assertObject($bucket, $path);
        return $this->client->url() . '/storage/v1/object/public/' . $bucket . '/' . $path;
    }

    /** @return array<string, string> Signed URLs by path; paths the user cannot read are omitted. */
    public function signedUrls(string $accessToken, string $bucket, array $paths, int $expiresIn = 3600): array
    {
        $paths = array_values(array_unique($paths));
        if ($paths === []) {
            return [];
        }
        foreach ($paths as $path) {
            $this->assertObject($bucket, $path);
        }
        $signed = $this->client->request('POST', '/storage/v1/object/sign/' . $bucket, [
            'json' => ['expiresIn' => $expiresIn, 'paths' => $paths],
        ], $accessToken);
        $urls = [];
        foreach ($signed as $item) {
            if (is_string($item['signedURL'] ?? null) && is_string($item['path'] ?? null) && empty($item['error'])) {
                $urls[$item['path']] = $this->client->url() . '/storage/v1' . $item['signedURL'];
            }
        }
        return $urls;
    }

    public function delete(string $accessToken, string $bucket, array $paths): void
    {
        $paths = array_values($paths);
        if ($paths === []) {
            return;
        }
        foreach ($paths as $path) {
            $this->assertObject($bucket, $path);
        }
        $this->client->request('DELETE', '/storage/v1/object/' . $bucket, ['json' => ['prefixes' => $paths]], $accessToken);
    }

    private function assertObject(string $bucket, mixed $path): void
    {
        if (!in_array($bucket, self::BUCKETS, true) || !is_string($path) || preg_match(self::PATH, $path) !== 1) {
            throw new \InvalidArgumentException('Invalid storage object.');
        }
    }
}
