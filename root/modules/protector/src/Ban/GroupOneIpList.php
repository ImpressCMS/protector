<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Ban;

use ImpressCMS\Module\Protector\Storage\DataPaths;

final class GroupOneIpList
{
    public function __construct(private readonly DataPaths $paths)
    {
    }

    /** @return array<int|string, mixed> */
    public function entries(): array
    {
        $lines = @file($this->paths->groupOneIps());
        $payload = $lines === false ? '' : (string) ($lines[0] ?? '');
        $entries = $payload === '' ? [] : @unserialize($payload);

        return is_array($entries) ? $entries : [];
    }

    /** @return array<int|string, int|string> */
    public function entriesWithInfo(): array
    {
        return array_flip($this->entries());
    }

    /** @param array<int|string, int|string> $entries */
    public function write(array $entries): bool
    {
        $handle = @fopen($this->paths->groupOneIps(), 'w');

        if (!$handle) {
            return false;
        }

        @flock($handle, LOCK_EX);
        fwrite($handle, serialize($entries) . "\n");
        @flock($handle, LOCK_UN);
        fclose($handle);

        return true;
    }

    public function path(): string
    {
        return $this->paths->groupOneIps();
    }
}
