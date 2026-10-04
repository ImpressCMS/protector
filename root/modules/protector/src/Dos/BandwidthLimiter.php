<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Dos;

use ImpressCMS\Module\Protector\Storage\DataPaths;

final class BandwidthLimiter
{
    private const MAXIMUM_SECONDS = 300;

    public function __construct(private readonly DataPaths $paths)
    {
    }

    public function expiresAt(): int
    {
        $lines = @file($this->paths->bandwidthLimit());

        return min((int) ($lines[0] ?? 0), time() + self::MAXIMUM_SECONDS);
    }

    public function isLimited(): bool
    {
        return $this->expiresAt() > time();
    }

    public function limitUntil(int $expire): bool
    {
        $handle = @fopen($this->paths->bandwidthLimit(), 'w');

        if (!$handle) {
            return false;
        }

        @flock($handle, LOCK_EX);
        fwrite($handle, min($expire, time() + self::MAXIMUM_SECONDS) . "\n");
        @flock($handle, LOCK_UN);
        fclose($handle);

        return true;
    }
}
