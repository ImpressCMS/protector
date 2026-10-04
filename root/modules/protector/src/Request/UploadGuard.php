<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Request;

use ImpressCMS\Module\Protector\Log\AuditLog;

final class UploadGuard
{
    private const FORBIDDEN_EXTENSIONS = ['php', 'phtml', 'phtm', 'php3', 'php4', 'cgi', 'pl', 'asp'];

    private const IMAGE_EXTENSIONS = [
        1 => 'gif', 2 => 'jpg', 3 => 'png', 4 => 'swf', 5 => 'psd', 6 => 'bmp', 7 => 'tif', 8 => 'tif',
        9 => 'jpc', 10 => 'jp2', 11 => 'jpx', 12 => 'jb2', 13 => 'swc', 14 => 'iff', 15 => 'wbmp', 16 => 'xbm',
    ];

    public function __construct(private readonly AuditLog $log)
    {
    }

    /** @param array<string, mixed> $files */
    public function isSafe(array $files): bool
    {
        $safe = true;

        foreach ($files as $file) {
            if (!empty($file['error'])) {
                continue;
            }

            if (empty($file['name']) || !is_string($file['name'])) {
                continue;
            }

            $safe = $this->inspect($file) && $safe;
        }

        return $safe;
    }

    /** @param array<string, mixed> $file */
    private function inspect(array $file): bool
    {
        $name = $file['name'];
        $extension = $this->normalisedExtension($name);
        $safe = true;

        if (count(explode('.', str_replace('.tar.gz', '.tgz', $name))) > 2) {
            $this->log->noteIncident('UPLOAD', "Attempt to multiple dot file {$name}.\n");
            $safe = false;
        }

        if (in_array($extension, self::FORBIDDEN_EXTENSIONS, true)) {
            $this->log->noteIncident('UPLOAD', "Attempt to upload {$name}.\n");
            $safe = false;
        }

        if (in_array($extension, self::IMAGE_EXTENSIONS, true) && !$this->isGenuineImage((string) ($file['tmp_name'] ?? ''), $extension)) {
            $this->log->noteIncident('UPLOAD', "Attempt to upload camouflaged image file {$name}.\n");
            $safe = false;
        }

        return $safe;
    }

    private function normalisedExtension(string $name): string
    {
        $extension = strtolower(substr((string) strrchr($name, '.'), 1));

        return match ($extension) {
            'jpeg' => 'jpg',
            'tiff' => 'tif',
            'swc' => 'swf',
            default => $extension,
        };
    }

    private function isGenuineImage(string $path, string $extension): bool
    {
        $attributes = @getimagesize($path);

        if ($attributes === false && is_uploaded_file($path)) {
            $attributes = $this->imageSizeViaTemporaryCopy($path);
        }

        if ($attributes === false) {
            return false;
        }

        $type = (int) $attributes[2];

        if ($type === IMAGETYPE_SWC) {
            $type = IMAGETYPE_SWF;
        }

        return (self::IMAGE_EXTENSIONS[$type] ?? null) === $extension;
    }

    /** @return array<int|string, mixed>|false */
    private function imageSizeViaTemporaryCopy(string $path): array|false
    {
        $copy = ICMS_ROOT_PATH . '/uploads/protector_upload_temporary' . md5((string) time());
        move_uploaded_file($path, $copy);
        $attributes = @getimagesize($copy);
        @unlink($copy);

        return $attributes;
    }
}
