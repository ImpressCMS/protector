<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Session;

use ImpressCMS\Module\Protector\Config\ProtectorConfig;
use ImpressCMS\Module\Protector\Http\ServerRequest;

final class SessionHijackGuard
{
    public function __construct(
        private readonly ProtectorConfig $config,
        private readonly SessionPurger $purger,
        private readonly IpMovement $movement = new IpMovement(),
    ) {
    }

    public function check(Visitor $visitor): void
    {
        $current = ServerRequest::rawClientIp();
        $previous = (string) ($_SESSION['protector_last_ip'] ?? '');

        if ($previous !== '' && $this->movement->hasMoved($previous, $current, $this->config->int('session_fixed_topbit')) && $this->mustBeSignedOut($visitor)) {
            $this->purger->purge(true);
        }

        $_SESSION['protector_last_ip'] = $current;
    }

    private function mustBeSignedOut(Visitor $visitor): bool
    {
        if (!$visitor->isMember()) {
            return false;
        }

        return array_intersect($visitor->groups(), $this->config->storedList('groups_denyipmove')) !== [];
    }
}
