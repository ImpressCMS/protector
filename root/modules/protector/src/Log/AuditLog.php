<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Log;

use ImpressCMS\Module\Protector\Config\ConfigStore;
use ImpressCMS\Module\Protector\Http\Responder;
use ImpressCMS\Module\Protector\Http\ServerRequest;

final class AuditLog
{
    private const TABLE = 'protector_log';

    private string $message = '';

    private string $lastType = 'UNKNOWN';

    private bool $written = false;

    private bool $databaseReady = false;

    public function __construct(
        private readonly ConfigStore $config,
        private readonly Responder $responder,
    ) {
    }

    public function databaseIsReady(): void
    {
        $this->databaseReady = true;
    }

    public function note(string $message): void
    {
        $this->message .= $message;
    }

    public function noteIncident(string $type, string $message): void
    {
        $this->lastType = $type;
        $this->message .= $message;
    }

    public function replaceMessage(string $message): void
    {
        $this->message = $message;
    }

    public function message(): string
    {
        return $this->message;
    }

    public function lastType(): string
    {
        return $this->lastType;
    }

    public function rememberType(string $type): void
    {
        $this->lastType = $type;
    }

    public function write(string $type, int $uid = 0, bool $skipRepeat = false, LogLevel|int $level = LogLevel::Basic): void
    {
        if ($this->written) {
            return;
        }

        $levelBit = $level instanceof LogLevel ? $level->value : $level;

        if (!($this->config->current()->int('log_level') & $levelBit)) {
            return;
        }

        if (!$this->databaseReady && !\icms::$xoopsDB) {
            $this->responder->halt('No DB connection');
        }

        $ip = ServerRequest::clientIp();
        $agent = ServerRequest::userAgent();
        $table = XOOPS_DB_PREFIX . '_' . self::TABLE;

        if ($skipRepeat && $this->repeatsLastRecord($table, $ip, $type)) {
            $this->written = true;

            return;
        }

        \icms::$xoopsDB->queryF(
            "INSERT INTO {$table} SET ip='{$ip}',agent='{$agent}',type='" . addslashes($type)
            . "',description='" . addslashes($this->message) . "',uid='" . $uid . "',timestamp=NOW()"
        );
        $this->written = true;
    }

    private function repeatsLastRecord(string $table, string $ip, string $type): bool
    {
        $result = \icms::$xoopsDB->queryF("SELECT ip,type FROM {$table} ORDER BY timestamp DESC LIMIT 1");
        [$lastIp, $lastType] = \icms::$xoopsDB->fetchRow($result);

        return $lastIp == $ip && $lastType == $type;
    }
}
