<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Dos;

use ImpressCMS\Module\Protector\Ban\BanList;
use ImpressCMS\Module\Protector\Config\ProtectorConfig;
use ImpressCMS\Module\Protector\Filter\FilterHandler;
use ImpressCMS\Module\Protector\Http\Responder;
use ImpressCMS\Module\Protector\Http\ServerRequest;
use ImpressCMS\Module\Protector\Log\AuditLog;

final class BruteForceGuard
{
    private const RECORD_SECONDS_TO_LIVE = 600;

    public function __construct(
        private readonly ProtectorConfig $config,
        private readonly AccessRepository $access,
        private readonly BanList $banList,
        private readonly FilterHandler $filters,
        private readonly AuditLog $log,
        private readonly Responder $responder,
    ) {
    }

    public function check(): void
    {
        $ip = ServerRequest::clientIp();

        if ($ip === '') {
            return;
        }

        $victim = $this->attemptedUsername();

        if ($victim === 'deleted') {
            return;
        }

        $uri = ServerRequest::uri();

        $this->access->purgeExpired();

        if ($this->access->countFailedLogins($ip) > $this->config->int('bf_count')) {
            $this->banList->register($ip, time() + $this->config->int('banip_time0'));
            $this->log->noteIncident('BruteForce', "Trying to login as '" . addslashes($victim) . "' found.\n");
            $this->log->write('BRUTE FORCE', 0, true);

            if ($this->filters->execute('bruteforce_overrun') === 0) {
                $this->responder->halt();
            }
        }

        $this->access->recordFailedLogin($ip, $uri, addslashes("BRUTE FORCE: {$victim}"), self::RECORD_SECONDS_TO_LIVE);
    }

    private function attemptedUsername(): string
    {
        $fromCookie = !empty($_COOKIE['autologin_uname']);

        $name = $fromCookie
            ? filter_input(INPUT_COOKIE, 'autologin_uname', FILTER_SANITIZE_STRING)
            : filter_input(INPUT_POST, 'uname', FILTER_SANITIZE_STRING);

        return (string) $name;
    }
}
