<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Ban;

final class IpComparator
{
    public static function compare(string $first, string $second): int
    {
        return self::weight($first) <=> self::weight($second);
    }

    private static function weight(string $address): int
    {
        $parts = explode('.', $address);

        return (int) ($parts[0] ?? 0) * 0x1000000
            + (int) ($parts[1] ?? 0) * 0x10000
            + (int) ($parts[2] ?? 0) * 0x100
            + (int) ($parts[3] ?? 0);
    }
}
