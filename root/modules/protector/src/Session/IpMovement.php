<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Session;

final class IpMovement
{
    private const MAXIMUM_IPV4_BITS = 32;

    private const MAXIMUM_IPV6_BITS = 128;

    public function hasMoved(string $previous, string $current, int $topBits): bool
    {
        if ($topBits < 1 || $topBits > self::MAXIMUM_IPV4_BITS) {
            return false;
        }

        $before = @inet_pton($previous);
        $now = @inet_pton($current);

        if ($before === false || $now === false || strlen($before) !== strlen($now)) {
            return false;
        }

        $bits = strlen($before) === 4 ? $topBits : min($topBits * 2, self::MAXIMUM_IPV6_BITS);
        $wholeBytes = intdiv($bits, 8);

        if (strncmp($before, $now, $wholeBytes) !== 0) {
            return true;
        }

        $remainingBits = $bits % 8;

        if ($remainingBits === 0) {
            return false;
        }

        $mask = (0xFF << (8 - $remainingBits)) & 0xFF;

        return (ord($before[$wholeBytes]) & $mask) !== (ord($now[$wholeBytes]) & $mask);
    }
}
