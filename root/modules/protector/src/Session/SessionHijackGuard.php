<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Session;

use ImpressCMS\Module\Protector\Config\ProtectorConfig;
use ImpressCMS\Module\Protector\Http\ServerRequest;

final class SessionHijackGuard
{
    /**
     * @readonly
     */
    private ProtectorConfig $config;
    /**
     * @readonly
     */
    private SessionPurger $purger;
    /**
     * @readonly
     */
    private IpMovement $movement;
    public function __construct(ProtectorConfig $config, SessionPurger $purger, ?IpMovement $movement = null)
    {
        $movement ??= new IpMovement();
        $this->config = $config;
        $this->purger = $purger;
        $this->movement = $movement;
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
