<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Install;

final class Uninstaller
{
    public function __construct(private readonly PreloadInstaller $preload)
    {
    }

    public static function forCurrentSite(): self
    {
        return new self(PreloadInstaller::forCurrentSite());
    }

    public function uninstall(): string
    {
        $this->preload->remove();

        return '';
    }
}
