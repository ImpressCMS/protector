<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Install;

use ImpressCMS\Module\Protector\Storage\DataPaths;

final class DataDirectory
{
    public function __construct(private readonly DataPaths $paths)
    {
    }

    public function ensure(): bool
    {
        $directory = $this->paths->directory();

        if (!is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        if (!is_file("{$directory}/index.html")) {
            @file_put_contents("{$directory}/index.html", '');
        }

        return is_dir($directory) && is_writable($directory);
    }

    /** @return list<string> the files that were moved */
    public function migrateLegacyFiles(): array
    {
        $legacy = $this->paths->legacyDirectory();

        if ($legacy === null || !is_dir($legacy) || !$this->ensure()) {
            return [];
        }

        $moved = [];

        foreach ($this->paths->fileNames() as $name) {
            $source = "{$legacy}/{$name}";
            $target = "{$this->paths->directory()}/{$name}";

            if (!is_file($source)) {
                continue;
            }

            if (is_file($target)) {
                @unlink($source);

                continue;
            }

            if (@rename($source, $target) || (@copy($source, $target) && @unlink($source))) {
                $moved[] = $name;
            }
        }

        return $moved;
    }
}
