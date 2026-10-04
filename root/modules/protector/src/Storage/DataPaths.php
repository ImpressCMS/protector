<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Storage;

final class DataPaths
{
    public function __construct(
        private readonly string $directory,
        private readonly string $siteSuffix,
    ) {
    }

    public static function forCurrentSite(): self
    {
        return new self(
            ICMS_TRUST_PATH . '/modules/protector/configs',
            substr(md5(ICMS_ROOT_PATH . XOOPS_DB_USER . XOOPS_DB_PREFIX), 0, 6),
        );
    }

    public function directory(): string
    {
        return $this->directory;
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
}
