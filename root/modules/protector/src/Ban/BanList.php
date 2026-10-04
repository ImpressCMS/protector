<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Ban;

use ImpressCMS\Module\Protector\Http\ServerRequest;
use ImpressCMS\Module\Protector\Storage\DataPaths;

final class BanList
{
    private const FOREVER = 0x7fffffff;

    public function __construct(private readonly DataPaths $paths)
    {
    }

    /** @return array<string, int> */
    public function entries(): array
    {
        $lines = @file($this->paths->badIps());
        $payload = $lines === false ? '' : (string) ($lines[0] ?? '');
        $entries = $payload === '' ? [] : @unserialize($payload);

        if (!is_array($entries) || isset($entries[0])) {
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

        $handle = @fopen($this->paths->badIps(), 'w');

        if (!$handle) {
            return false;
        }

        @flock($handle, LOCK_EX);
        fwrite($handle, serialize($entries) . "\n");
        @flock($handle, LOCK_UN);
        fclose($handle);

        return true;
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
