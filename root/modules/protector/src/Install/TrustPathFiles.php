<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Install;

/*
 * ImpressCMS 2.0.4 ships a preload that still includes two files from the trust path. The package carries a copy of
 * everything the module needs there under include/trust_path/modules/protector/, and this class puts it in place.
 */
final class TrustPathFiles
{
    private string $sourceDirectory;

    private string $targetDirectory;

    public function __construct(string $sourceDirectory, string $targetDirectory)
    {
        $this->sourceDirectory = rtrim($sourceDirectory, '/\\');
        $this->targetDirectory = rtrim($targetDirectory, '/\\');
    }

    public static function forCurrentSite(): self
    {
        return new self(
            dirname(__DIR__, 2) . '/include/trust_path/modules/protector',
            ICMS_TRUST_PATH . '/modules/protector'
        );
    }

    public function ensure(): string
    {
        $files = $this->files();

        if ($files === []) {
            return '';
        }

        $failed = [];

        foreach ($files as $relative) {
            if (!$this->place($relative)) {
                $failed[] = $relative;
            }
        }

        if ($failed !== []) {
            return 'Could not write to the trust path: ' . implode(', ', $failed) . '. Your site may not be protected.';
        }

        return 'Forwarding files placed in the trust path.';
    }

    /** @return list<string> paths relative to the source directory */
    private function files(): array
    {
        if (!is_dir($this->sourceDirectory)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->sourceDirectory, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            $files[] = str_replace('\\', '/', substr($file->getPathname(), strlen($this->sourceDirectory) + 1));
        }

        sort($files);

        return $files;
    }

    private function place(string $relative): bool
    {
        $source = "{$this->sourceDirectory}/{$relative}";
        $target = "{$this->targetDirectory}/{$relative}";

        if (is_file($target) && md5_file($target) === md5_file($source)) {
            return true;
        }

        if (!is_dir(dirname($target)) && !@mkdir(dirname($target), 0775, true)) {
            return false;
        }

        if (!@copy($source, $target)) {
            return false;
        }

        $this->forgetCompiledCopy($target);

        return true;
    }

    /*
     * The 5.x file that was just replaced may still be cached in compiled form and would keep reading files that the
     * update is about to delete.
     */
    private function forgetCompiledCopy(string $file): void
    {
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($file, true);
        }
    }
}
