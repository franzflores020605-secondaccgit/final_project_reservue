<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Stores uploaded images under public/uploads/documents/ and returns the web path (e.g. uploads/documents/abc.jpg).
 */
final class DocumentImageStorage
{
    private const PREFIX = 'uploads/documents/';

    private const ALLOWED_EXT = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    public function __construct(
        private readonly string $documentsDirectory,
    ) {
    }

    public function store(UploadedFile $file): string
    {
        if (!is_dir($this->documentsDirectory)) {
            if (!mkdir($this->documentsDirectory, 0775, true) && !is_dir($this->documentsDirectory)) {
                throw new \RuntimeException(sprintf('Cannot create directory "%s".', $this->documentsDirectory));
            }
        }

        $ext = strtolower((string) $file->guessExtension());
        if (!\in_array($ext, self::ALLOWED_EXT, true)) {
            $ext = 'jpg';
        }

        $name = bin2hex(random_bytes(16)).'.'.$ext;
        $file->move($this->documentsDirectory, $name);

        return self::PREFIX.$name;
    }

    /**
     * Remove a file previously stored by this service (safe path check).
     */
    public function removeIfManaged(?string $webPath): void
    {
        if ($webPath === null || $webPath === '' || !str_starts_with($webPath, self::PREFIX)) {
            return;
        }

        $base = realpath($this->documentsDirectory);
        if ($base === false) {
            return;
        }

        $file = $base.\DIRECTORY_SEPARATOR.basename($webPath);
        $real = realpath($file);
        if ($real !== false && str_starts_with($real, $base) && is_file($real)) {
            unlink($real);
        }
    }
}
