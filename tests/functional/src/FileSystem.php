<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Tests\Functional;

final class FileSystem
{
    public static function normalize(string $path): string
    {
        return rtrim(str_replace('\\', '/', $path), '/');
    }

    public static function mirror(string $source, string $destination, array $excludeDirectories = []): void
    {
        $source = self::normalize($source);
        $destination = self::normalize($destination);

        if (!is_dir($source)) {
            throw new \RuntimeException("Cannot mirror missing directory {$source}");
        }

        if (PHP_OS_FAMILY === 'Windows') {
            self::robocopy($source, $destination, $excludeDirectories);

            return;
        }

        self::removeTree($destination);
        self::copyTree($source, $destination);
    }

    public static function copyTree(string $source, string $destination): void
    {
        if (!is_dir($destination)) {
            mkdir($destination, 0777, true);
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $item) {
            $relative = substr(self::normalize($item->getPathname()), strlen(self::normalize($source)));
            $target = $destination . $relative;

            if ($item->isDir()) {
                is_dir($target) || mkdir($target, 0777, true);

                continue;
            }

            copy($item->getPathname(), $target);
        }
    }

    public static function removeTree(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }

        if (is_file($path) || is_link($path)) {
            @chmod($path, 0666);
            unlink($path);

            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $item) {
            @chmod($item->getPathname(), 0777);
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($path);
    }

    public static function emptyDirectory(string $path, array $keep = ['index.html', '.htaccess']): void
    {
        if (!is_dir($path)) {
            return;
        }

        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..' || in_array($entry, $keep, true)) {
                continue;
            }

            self::removeTree($path . '/' . $entry);
        }
    }

    private static function robocopy(string $source, string $destination, array $excludeDirectories): void
    {
        $command = sprintf(
            'robocopy %s %s /MIR /NFL /NDL /NJH /NJS /NP /R:1 /W:1',
            escapeshellarg(str_replace('/', '\\', $source)),
            escapeshellarg(str_replace('/', '\\', $destination)),
        );

        foreach ($excludeDirectories as $directory) {
            $command .= ' /XD ' . escapeshellarg(str_replace('/', '\\', $directory));
        }

        exec($command . ' 2>&1', $output, $code);

        if ($code >= 8) {
            throw new \RuntimeException("robocopy failed ({$code}): " . implode("\n", $output));
        }
    }
}
