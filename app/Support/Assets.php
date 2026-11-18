<?php
declare(strict_types=1);

namespace SwapFix\Support;

final class Assets
{
    public static function url(string $path): string
    {
        if (!str_starts_with($path, '/assets/') || str_contains($path, '..')) {
            throw new \InvalidArgumentException('Invalid asset path.');
        }
        $file = dirname(__DIR__, 2) . '/public' . $path;
        if (!is_file($file)) {
            throw new \RuntimeException('An application asset is missing.');
        }
        return $path . '?v=' . hash_file('crc32b', $file);
    }

    public static function image(string $name): array
    {
        static $manifest;
        $manifest ??= json_decode((string) file_get_contents(dirname(__DIR__, 2) . '/resources/data/images.json'), true, flags: JSON_THROW_ON_ERROR);
        if (!isset($manifest[$name])) {
            throw new \InvalidArgumentException('Unknown application image.');
        }
        $image = $manifest[$name];
        $set = [];
        foreach ($image['sources'] as $width => $source) {
            $set[] = $source . ' ' . $width . 'w';
        }
        return ['src' => end($image['sources']), 'srcset' => implode(', ', $set), 'width' => $image['width'], 'height' => $image['height']];
    }
}
