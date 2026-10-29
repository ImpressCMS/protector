<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Install;

final class Installer
{
    private PreloadInstaller $preload;

    private TrustPathFiles $trustPathFiles;

    public function __construct(PreloadInstaller $preload, TrustPathFiles $trustPathFiles)
    {
        $this->preload = $preload;
        $this->trustPathFiles = $trustPathFiles;
    }

    public static function forCurrentSite(): self
    {
        return new self(PreloadInstaller::forCurrentSite(), TrustPathFiles::forCurrentSite());
    }

    public function install(): string
    {
        return implode("\n", array_filter([$this->trustPathFiles->ensure(), $this->preload->ensure()]));
    }
}
