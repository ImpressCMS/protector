<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Install;

/*
 * Protector 5 loaded filters from the trust path, Protector 6 loads them from the module directory. Files the 5.x package
 * shipped are in the module directory already (and in the obsolete list); what is left over was added by the site owner.
 */
final class LegacyFilterMigrator
{
    private const FOLDERS = ['filters_byconfig', 'filters_enabled'];

    private string $legacyDirectory;

    private string $moduleDirectory;

    private ObsoleteFiles $obsolete;

    public function __construct(string $legacyDirectory, string $moduleDirectory, ObsoleteFiles $obsolete)
    {
        $this->legacyDirectory = rtrim($legacyDirectory, '/\\');
        $this->moduleDirectory = rtrim($moduleDirectory, '/\\');
        $this->obsolete = $obsolete;
    }

    public static function forCurrentSite(): self
    {
        return new self(
            ICMS_TRUST_PATH . '/modules/protector',
            dirname(__DIR__, 2),
            ObsoleteFiles::forCurrentSite()
        );
    }

    /** @return list<string> the files that were copied, as folder/name */
    public function migrate(): array
    {
        $migrated = [];

        foreach (self::FOLDERS as $folder) {
            foreach ($this->candidates($folder) as $name) {
                if ($this->copy($folder, $name)) {
                    $migrated[] = "{$folder}/{$name}";
                }
            }
        }

        return $migrated;
    }

    /** @return list<string> */
    private function candidates(string $folder): array
    {
        $names = is_dir("{$this->legacyDirectory}/{$folder}") ? scandir("{$this->legacyDirectory}/{$folder}") : false;

        if ($names === false) {
            return [];
        }

        return array_values(array_filter($names, function (string $name) use ($folder): bool {
            return preg_match('/^[A-Za-z0-9_]+\.php$/', $name) === 1
                && is_file("{$this->legacyDirectory}/{$folder}/{$name}")
                && !$this->obsolete->listsTrustFile("modules/protector/{$folder}/{$name}");
        }));
    }

    private function copy(string $folder, string $name): bool
    {
        $target = "{$this->moduleDirectory}/{$folder}/{$name}";

        if (file_exists($target)) {
            return false;
        }

        if (!is_dir(dirname($target)) && !@mkdir(dirname($target), 0775, true)) {
            return false;
        }

        return @copy("{$this->legacyDirectory}/{$folder}/{$name}", $target);
    }
}
