<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Dos;

use ImpressCMS\Module\Protector\Ban\BanList;
use ImpressCMS\Module\Protector\Ban\HtaccessWriter;
use ImpressCMS\Module\Protector\Config\ProtectorConfig;
use ImpressCMS\Module\Protector\Filter\FilterHandler;
use ImpressCMS\Module\Protector\Http\Responder;
use ImpressCMS\Module\Protector\Http\ServerRequest;
use ImpressCMS\Module\Protector\Log\AuditLog;
use ImpressCMS\Module\Protector\Log\LogLevel;

final class DosGuard
{
    private const SLEEP_SECONDS = 5;

    private bool $inspected = false;

    public function __construct(
        private readonly ProtectorConfig $config,
        private readonly AccessRepository $access,
        private readonly BandwidthLimiter $bandwidth,
        private readonly BanList $banList,
        private readonly HtaccessWriter $htaccess,
        private readonly FilterHandler $filters,
        private readonly AuditLog $log,
        private readonly Responder $responder,
    ) {
    }

    public function check(int $uid, bool $canBan): void
    {
        if ($this->inspected) {
            return;
        }

        $ip = ServerRequest::clientIp();

        if ($ip === '') {
            return;
        }

        if (!$this->access->purgeExpired()) {
            $this->inspected = true;

            return;
        }

        $uri = ServerRequest::uri();
        $timeToLive = $this->config->int('dos_expire');

        $this->limitBandwidthWhenCrowded($timeToLive);

        if ($this->access->countFromIpForUri($ip, $uri) > $this->config->int('dos_f5count')) {
            $this->access->record($ip, $uri, $timeToLive);
            $this->filters->execute('f5attack_overrun');
            $this->respond('DoS', DosAction::fromStored($this->config->string('dos_f5action')), $uid, $canBan);

            return;
        }

        if ($this->isWelcomedCrawler()) {
            $this->inspected = true;

            return;
        }

        $crawlerCount = $this->access->countFromIp($ip);
        $this->access->record($ip, $uri, $timeToLive);

        if ($crawlerCount > $this->config->int('dos_crcount')) {
            $this->filters->execute('crawler_overrun');
            $this->respond('CRAWLER', DosAction::fromStored($this->config->string('dos_craction')), $uid, $canBan);
        }
    }

    private function limitBandwidthWhenCrowded(int $timeToLive): void
    {
        $limit = $this->config->int('bwlimit_count');

        if ($limit < 10) {
            return;
        }

        if ($this->access->countAll() > $limit) {
            $this->bandwidth->limitUntil(time() + $timeToLive);
        }
    }

    private function isWelcomedCrawler(): bool
    {
        $pattern = $this->config->string('dos_crsafe');

        return trim($pattern) !== '' && (bool) preg_match($pattern, ServerRequest::rawUserAgent());
    }

    private function respond(string $type, DosAction $action, int $uid, bool $canBan): void
    {
        $this->inspected = true;
        $this->log->rememberType($type);

        if ($action === DosAction::Exit) {
            $this->log->write($type, $uid, true, LogLevel::Dos);
            $this->responder->halt();
        }

        $this->enforce($action, $canBan);
        $this->log->write($type, $uid, true, LogLevel::Dos);
    }

    private function enforce(DosAction $action, bool $canBan): void
    {
        if ($action === DosAction::Sleep) {
            sleep(self::SLEEP_SECONDS);

            return;
        }

        if (!$canBan) {
            return;
        }

        match ($action) {
            DosAction::BanTemporarily => $this->banList->registerClient(time() + $this->config->int('banip_time0')),
            DosAction::BanPermanently => $this->banList->registerClient(),
            DosAction::DenyByHtaccess => $this->htaccess->denyClient(),
            default => null,
        };
    }
}
