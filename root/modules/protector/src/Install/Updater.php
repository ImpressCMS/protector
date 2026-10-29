<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Install;

use ImpressCMS\Module\Protector\Database\CorePdoProvider;
use ImpressCMS\Module\Protector\Storage\DataPaths;

final class Updater
{
    private DataDirectory $dataDirectory;

    private PreloadInstaller $preload;

    private AccessIndex $accessIndex;

    private TrustPathFiles $trustPathFiles;

    private LegacyFilterMigrator $filterMigrator;

    private ObsoleteFiles $obsoleteFiles;

    public function __construct(
        DataDirectory $dataDirectory,
        PreloadInstaller $preload,
        AccessIndex $accessIndex,
        TrustPathFiles $trustPathFiles,
        LegacyFilterMigrator $filterMigrator,
        ObsoleteFiles $obsoleteFiles
    ) {
        $this->dataDirectory = $dataDirectory;
        $this->preload = $preload;
        $this->accessIndex = $accessIndex;
        $this->trustPathFiles = $trustPathFiles;
        $this->filterMigrator = $filterMigrator;
        $this->obsoleteFiles = $obsoleteFiles;
    }

    public static function forCurrentSite(): self
    {
        return new self(
            new DataDirectory(DataPaths::forCurrentSite()),
            PreloadInstaller::forCurrentSite(),
            new AccessIndex(new CorePdoProvider(), XOOPS_DB_PREFIX),
            TrustPathFiles::forCurrentSite(),
            LegacyFilterMigrator::forCurrentSite(),
            ObsoleteFiles::forCurrentSite(),
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
        $messages[] = $this->trustPathFiles->ensure();

        $migrated = $this->filterMigrator->migrate();

        if ($migrated !== []) {
            $messages[] = 'Custom filters copied from the trust path: ' . implode(', ', $migrated) . '.';
        }

        $messages[] = $this->removeObsoleteFiles();

        return implode("\n", array_filter($messages));
    }

    private function removeObsoleteFiles(): string
    {
        $result = $this->obsoleteFiles->remove();

        if ($result['failed'] !== []) {
            return 'Obsolete files could not be deleted: ' . implode(', ', $result['failed']) . '.';
        }

        return $result['removed'] === [] ? '' : sprintf('%d obsolete files of Protector 5 removed.', count($result['removed']));
    }
}
