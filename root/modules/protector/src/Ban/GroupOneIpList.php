<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Ban;

use ImpressCMS\Module\Protector\Storage\AtomicFile;
use ImpressCMS\Module\Protector\Storage\DataPaths;
use ImpressCMS\Module\Protector\Storage\StoredArray;

final class GroupOneIpList
{
    public function __construct(private readonly DataPaths $paths)
    {
    }

    /** @return array<int|string, mixed> */
    public function entries(): array
    {
        $lines = @file($this->paths->forReading($this->paths->groupOneIps()));
        $payload = $lines === false ? '' : (string) ($lines[0] ?? '');
        return StoredArray::decode($payload) ?? [];
    }

    /** @return array<int|string, int|string> */
    public function entriesWithInfo(): array
    {
        return array_flip($this->entries());
    }

    /** @param array<int|string, int|string> $entries */
    public function write(array $entries): bool
    {
        return AtomicFile::write($this->paths->groupOneIps(), StoredArray::encode($entries) . "\n");
    }

    public function path(): string
    {
        return $this->paths->groupOneIps();
    }
}
