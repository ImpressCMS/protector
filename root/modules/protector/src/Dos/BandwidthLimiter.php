<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Dos;

use ImpressCMS\Module\Protector\Storage\AtomicFile;
use ImpressCMS\Module\Protector\Storage\DataPaths;

final class BandwidthLimiter
{
    private const MAXIMUM_SECONDS = 300;

    public function __construct(private readonly DataPaths $paths)
    {
    }

    public function expiresAt(): int
    {
        $lines = @file($this->paths->forReading($this->paths->bandwidthLimit()));

        return min((int) ($lines[0] ?? 0), time() + self::MAXIMUM_SECONDS);
    }

    public function isLimited(): bool
    {
        return $this->expiresAt() > time();
    }

    public function limitUntil(int $expire): bool
    {
        return AtomicFile::write($this->paths->bandwidthLimit(), min($expire, time() + self::MAXIMUM_SECONDS) . "\n");
    }
}
