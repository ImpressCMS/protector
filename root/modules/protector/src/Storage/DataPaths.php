<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Storage;

final class DataPaths
{
    public function __construct(
        private readonly string $directory,
        private readonly string $siteSuffix,
        private readonly ?string $legacyDirectory = null,
    ) {
    }

    public static function forCurrentSite(): self
    {
        return new self(
            ICMS_TRUST_PATH . '/cache/protector',
            substr(md5(ICMS_ROOT_PATH . XOOPS_DB_USER . XOOPS_DB_PREFIX), 0, 6),
            ICMS_TRUST_PATH . '/modules/protector/configs',
        );
    }

    public function directory(): string
    {
        return $this->directory;
    }

    public function legacyDirectory(): ?string
    {
        return $this->legacyDirectory;
    }

    public function badIps(): string
    {
        return "{$this->directory}/badips{$this->siteSuffix}";
    }

    public function groupOneIps(): string
    {
        return "{$this->directory}/group1ips{$this->siteSuffix}";
    }

    public function bandwidthLimit(): string
    {
        return "{$this->directory}/bwlimit{$this->siteSuffix}";
    }

    public function configCache(): string
    {
        return "{$this->directory}/configcache{$this->siteSuffix}";
    }

    /** @return list<string> file names of the state files that older versions kept in the legacy directory */
    public function fileNames(): array
    {
        return array_map('basename', [$this->badIps(), $this->groupOneIps(), $this->bandwidthLimit(), $this->configCache()]);
    }

    public function forReading(string $path): string
    {
        if ($this->legacyDirectory === null || is_file($path)) {
            return $path;
        }

        $legacy = "{$this->legacyDirectory}/" . basename($path);

        return is_file($legacy) ? $legacy : $path;
    }
}
