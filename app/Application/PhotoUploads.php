<?php
declare(strict_types=1);

namespace SwapFix\Application;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use SwapFix\Http\UserSession;
use SwapFix\Integrations\Supabase\Storage;
use SwapFix\Support\DomainError;

/** Sends one form's photos to Storage and can undo them when the form is rejected. */
final class PhotoUploads
{
    private array $stored = [];

    public function __construct(private readonly Storage $storage, private readonly UserSession $user) {}

    /** @return list<string> Stored object paths in the user's own folder. */
    public function take(ServerRequestInterface $request, string $field, string $bucket, int $max): array
    {
        $files = $request->getUploadedFiles()[$field] ?? [];
        $files = array_filter(is_array($files) ? $files : [$files], static fn ($file): bool => $file instanceof UploadedFileInterface && $file->getError() !== UPLOAD_ERR_NO_FILE);
        if (count($files) > $max) {
            throw new DomainError('You can add up to ' . $max . ' photos here.');
        }
        // Leave room for multipart metadata below the hosting request-body limit.
        if (array_sum(array_map(static fn (UploadedFileInterface $file): int => (int) $file->getSize(), $files)) > 4194304) {
            throw new DomainError('Photos must total 4 MiB or less.');
        }
        $paths = [];
        foreach ($files as $file) {
            if ($file->getError() !== UPLOAD_ERR_OK) {
                $this->discard();
                throw new DomainError('A photo could not be uploaded. Use JPEG, PNG or WebP up to 4 MiB total.');
            }
            try {
                $stored = $this->storage->upload($this->user->accessToken, (string) $file->getStream()->getMetadata('uri'), $bucket, $this->user->id);
            } catch (\InvalidArgumentException) {
                $this->discard();
                throw new DomainError('Upload JPEG, PNG or WebP images up to 4 MiB total.');
            }
            $this->stored[] = $stored;
            $paths[] = $stored['path'];
        }
        return $paths;
    }

    public function discard(): void
    {
        foreach ($this->stored as $item) {
            $this->remove($item['bucket'], [$item['path']]);
        }
        $this->stored = [];
    }

    /** Cleanup is best effort: a leftover object is harmless and stays in the user's folder. */
    public function remove(string $bucket, array $paths): void
    {
        try {
            $this->storage->delete($this->user->accessToken, $bucket, $paths);
        } catch (\Throwable $exception) {
            error_log('SwapFix photo cleanup skipped: ' . $exception::class);
        }
    }
}
