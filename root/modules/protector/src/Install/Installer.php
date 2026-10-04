<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Install;

use ImpressCMS\Module\Protector\Storage\DataPaths;

final class Installer
{
    public function __construct(
        private readonly DataDirectory $dataDirectory,
        private readonly PreloadInstaller $preload,
    ) {
    }

    public static function forCurrentSite(): self
    {
        return new self(
            new DataDirectory(DataPaths::forCurrentSite()),
            PreloadInstaller::forCurrentSite(),
        );
    }

    public function install(): string
    {
        $messages = [];

        if (!$this->dataDirectory->ensure()) {
            $messages[] = 'The data directory could not be created or is not writable.';
        }

        $messages[] = $this->preload->ensure();

        return implode("\n", array_filter($messages));
    }
}
