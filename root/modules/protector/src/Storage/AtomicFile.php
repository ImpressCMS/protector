<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Storage;

final class AtomicFile
{
    public static function write(string $path, string $contents): bool
    {
        if (!is_dir(dirname($path))) {
            @mkdir(dirname($path), 0775, true);
        }

        $temporary = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';

        if (@file_put_contents($temporary, $contents, LOCK_EX) !== false) {
            if (@rename($temporary, $path)) {
                return true;
            }

            @unlink($temporary);
        }

        return @file_put_contents($path, $contents, LOCK_EX) !== false;
    }
}
