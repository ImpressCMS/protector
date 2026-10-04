<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Config;

final class ProtectorConfig
{
    /** @param array<string, mixed> $values */
    public function __construct(private readonly array $values = [])
    {
    }

    public function isEmpty(): bool
    {
        return $this->values === [];
    }

    public function isGloballyDisabled(): bool
    {
        return $this->enabled('global_disabled');
    }

    public function enabled(string $key): bool
    {
        return !empty($this->values[$key]);
    }

    public function int(string $key): int
    {
        return (int) ($this->values[$key] ?? 0);
    }

    public function string(string $key): string
    {
        return (string) ($this->values[$key] ?? '');
    }

    /** @return array<int, mixed> */
    public function storedList(string $key): array
    {
        $raw = $this->string($key);
        $list = @unserialize($raw);

        if (is_array($list)) {
            return $list;
        }

        $list = @unserialize(stripslashes($raw));

        return is_array($list) ? $list : [];
    }

    public function isReliableIp(string $ip): bool
    {
        foreach ($this->storedList('reliable_ips') as $pattern) {
            if (!empty($pattern) && preg_match('/' . $pattern . '/', $ip)) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->values;
    }
}
