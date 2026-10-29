<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Install;

/*
 * The package build writes include/obsolete-files.txt: one file of an older layout per line, prefixed "root:" (relative to
 * the site root) or "trust:" (relative to the trust path). Only those files are ever deleted, never a directory as a whole.
 */
final class ObsoleteFiles
{
    private const OWN_DIRECTORY = 'modules/protector/';

    private const NEVER_DELETED = ['modules/protector/configs/'];

    private string $listFile;

    private string $siteRoot;

    private string $trustPath;

    public function __construct(string $listFile, string $siteRoot, string $trustPath)
    {
        $this->listFile = $listFile;
        $this->siteRoot = rtrim($siteRoot, '/\\');
        $this->trustPath = rtrim($trustPath, '/\\');
    }

    public static function forCurrentSite(): self
    {
        return new self(dirname(__DIR__, 2) . '/include/obsolete-files.txt', ICMS_ROOT_PATH, ICMS_TRUST_PATH);
    }

    /** @return list<string> the valid entries, as written in the list */
    public function entries(): array
    {
        $lines = is_file($this->listFile) ? file($this->listFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : false;

        if ($lines === false) {
            return [];
        }

        return array_values(array_filter(array_map('trim', $lines), [$this, 'isUsableEntry']));
    }

    public function listsTrustFile(string $relativePath): bool
    {
        return in_array("trust:{$relativePath}", $this->entries(), true);
    }

    /** @return array{removed: list<string>, failed: list<string>} */
    public function remove(): array
    {
        $removed = [];
        $failed = [];

        foreach ($this->entries() as $entry) {
            $path = $this->absolutePath($entry);

            if (!is_file($path)) {
                continue;
            }

            if (@unlink($path)) {
                $removed[] = $entry;
                $this->removeEmptyParents($path, $this->baseOf($entry));

                continue;
            }

            $failed[] = $entry;
        }

        return ['removed' => $removed, 'failed' => $failed];
    }

    private function isUsableEntry(string $entry): bool
    {
        if (strpos($entry, 'root:') !== 0 && strpos($entry, 'trust:') !== 0) {
            return false;
        }

        $relative = $this->relativePathOf($entry);

        if (strpos($relative, self::OWN_DIRECTORY) !== 0 || strpos($relative, '..') !== false || strpos($relative, '\\') !== false) {
            return false;
        }

        foreach (self::NEVER_DELETED as $protected) {
            if (strpos($relative, $protected) === 0) {
                return false;
            }
        }

        return true;
    }

    private function relativePathOf(string $entry): string
    {
        return substr($entry, strpos($entry, ':') + 1);
    }

    private function baseOf(string $entry): string
    {
        return strpos($entry, 'root:') === 0 ? $this->siteRoot : $this->trustPath;
    }

    private function absolutePath(string $entry): string
    {
        return "{$this->baseOf($entry)}/{$this->relativePathOf($entry)}";
    }

    private function removeEmptyParents(string $file, string $base): void
    {
        $stop = "{$base}/" . rtrim(self::OWN_DIRECTORY, '/');
        $directory = dirname($file);

        while (strlen($directory) > strlen($stop) && strpos($directory, $stop) === 0 && $this->holdsNothingButPlaceholders($directory)) {
            @unlink("{$directory}/index.html");

            if (!@rmdir($directory)) {
                return;
            }

            $directory = dirname($directory);
        }
    }

    /*
     * The core drops an index.html that goes back one page into every directory it copies.
     */
    private function holdsNothingButPlaceholders(string $directory): bool
    {
        if (!is_dir($directory)) {
            return false;
        }

        $entries = array_values(array_diff(scandir($directory) ?: ['.', '..', '?'], ['.', '..']));

        if ($entries === []) {
            return true;
        }

        return $entries === ['index.html']
            && in_array(trim((string) @file_get_contents("{$directory}/index.html")), ['', '<script>history.go(-1);</script>'], true);
    }
}
