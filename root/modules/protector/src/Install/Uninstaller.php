<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Install;

final class Uninstaller
{
    /**
     * @readonly
     */
    private PreloadInstaller $preload;
    public function __construct(PreloadInstaller $preload)
    {
        $this->preload = $preload;
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
