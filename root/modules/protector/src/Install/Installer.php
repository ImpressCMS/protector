<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Install;

final class Installer
{
    public function __construct(private readonly PreloadInstaller $preload)
    {
    }

    public static function forCurrentSite(): self
    {
        return new self(PreloadInstaller::forCurrentSite());
    }

    public function install(): string
    {
        return $this->preload->ensure();
    }
}
