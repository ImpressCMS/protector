<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Session;

use ImpressCMS\Module\Protector\Config\ProtectorConfig;
use ImpressCMS\Module\Protector\Http\ServerRequest;

final class SessionHijackGuard
{
    private const ADDRESS_BITS = 32;

    public function __construct(
        private readonly ProtectorConfig $config,
        private readonly SessionPurger $purger,
    ) {
    }

    public function check(Visitor $visitor): void
    {
        $current = ServerRequest::rawClientIp();
        $previous = (string) ($_SESSION['protector_last_ip'] ?? '');

        if (!empty($previous) && $this->leftTheNetwork($previous, $current) && $this->mustBeSignedOut($visitor)) {
            $this->purger->purge(true);
        }

        $_SESSION['protector_last_ip'] = $current;
    }

    private function leftTheNetwork(string $previous, string $current): bool
    {
        $shift = self::ADDRESS_BITS - $this->config->int('session_fixed_topbit');

        if ($shift >= self::ADDRESS_BITS || $shift < 0) {
            return false;
        }

        return $this->numeric($previous) >> $shift !== $this->numeric($current) >> $shift;
    }

    private function mustBeSignedOut(Visitor $visitor): bool
    {
        if (!$visitor->isMember()) {
            return false;
        }

        return array_intersect($visitor->groups(), $this->config->storedList('groups_denyipmove')) !== [];
    }

    private function numeric(string $address): int
    {
        $parts = explode('.', $address);

        return (int) ($parts[0] ?? 0) * 0x1000000
            + (int) ($parts[1] ?? 0) * 0x10000
            + (int) ($parts[2] ?? 0) * 0x100
            + (int) ($parts[3] ?? 0);
    }
}
