<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Install;

use ImpressCMS\Module\Protector\Database\CorePdoProvider;
use ImpressCMS\Module\Protector\Storage\DataPaths;

final class Updater
{
    public function __construct(
        private readonly DataDirectory $dataDirectory,
        private readonly PreloadInstaller $preload,
        private readonly AccessIndex $accessIndex,
    ) {
    }

    public static function forCurrentSite(): self
    {
        return new self(
            new DataDirectory(DataPaths::forCurrentSite()),
            PreloadInstaller::forCurrentSite(),
            new AccessIndex(new CorePdoProvider(), XOOPS_DB_PREFIX),
        );
    }

    public function update(): string
    {
        $messages = [];

        if (!$this->dataDirectory->ensure()) {
            $messages[] = 'The data directory could not be created or is not writable.';
        }

        $moved = $this->dataDirectory->migrateLegacyFiles();

        if ($moved !== []) {
            $messages[] = 'Moved to the data directory: ' . implode(', ', $moved) . '.';
        }

        $messages[] = $this->accessIndex->ensure();
        $messages[] = $this->preload->ensure();

        return implode("\n", array_filter($messages));
    }
}
