<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Ban;

use ImpressCMS\Module\Protector\Http\ServerRequest;
use ImpressCMS\Module\Protector\Storage\AtomicFile;
use ImpressCMS\Module\Protector\Storage\DataPaths;
use ImpressCMS\Module\Protector\Storage\StoredArray;

final class BanList
{
    private const FOREVER = 0x7fffffff;

    public function __construct(private readonly DataPaths $paths)
    {
    }

    /** @return array<string, int> */
    public function entries(): array
    {
        $lines = @file($this->paths->forReading($this->paths->badIps()));
        $payload = $lines === false ? '' : (string) ($lines[0] ?? '');
        $entries = StoredArray::decode($payload);

        if ($entries === null || isset($entries[0])) {
            return [];
        }

        $expired = 0;

        foreach ($entries as $jailedUntil) {
            if ($jailedUntil >= time()) {
                break;
            }

            $expired++;
        }

        return array_slice($entries, $expired);
    }

    /** @return array<int, string> */
    public function addresses(): array
    {
        return array_map('strval', array_keys($this->entries()));
    }

    /** @param array<string, int> $entries */
    public function write(array $entries): bool
    {
        asort($entries);

        return AtomicFile::write($this->paths->badIps(), StoredArray::encode($entries) . "\n");
    }

    public function register(string $ip, int $jailedUntil = 0): bool
    {
        if ($ip === '') {
            return false;
        }

        $entries = $this->entries();
        $entries[$ip] = $jailedUntil ?: self::FOREVER;

        return $this->write($entries);
    }

    public function registerClient(int $jailedUntil = 0): bool
    {
        return $this->register(ServerRequest::clientIp(), $jailedUntil);
    }

    public function path(): string
    {
        return $this->paths->badIps();
    }
}
