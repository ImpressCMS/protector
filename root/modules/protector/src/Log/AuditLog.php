<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Log;

use ImpressCMS\Module\Protector\Config\ConfigStore;
use ImpressCMS\Module\Protector\Database\PdoProvider;
use ImpressCMS\Module\Protector\Http\ServerRequest;

final class AuditLog
{
    private const TABLE = 'protector_log';

    private string $message = '';

    private string $lastType = 'UNKNOWN';

    private bool $written = false;

    public function __construct(
        private readonly ConfigStore $config,
        private readonly PdoProvider $database,
        private readonly string $tablePrefix = '',
    ) {
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

        $connection = $this->database->connection();

        if ($connection === null) {
            return;
        }

        $table = $this->tablePrefix . '_' . self::TABLE;
        $ip = ServerRequest::clientIp();

        if ($skipRepeat && $this->repeatsLastRecord($connection, $table, $ip, $type)) {
            $this->written = true;

            return;
        }

        $statement = $connection->prepare(
            "INSERT INTO {$table} (ip, agent, type, description, uid, `timestamp`)"
            . ' VALUES (:ip, :agent, :type, :description, :uid, CURRENT_TIMESTAMP)'
        );
        $this->written = true;

        if ($statement === false) {
            return;
        }

        $statement->execute([
            'ip' => $ip,
            'agent' => ServerRequest::userAgent(),
            'type' => $type,
            'description' => $this->message,
            'uid' => $uid,
        ]);
    }

    private function repeatsLastRecord(\PDO $connection, string $table, string $ip, string $type): bool
    {
        $result = $connection->query("SELECT ip, type FROM {$table} ORDER BY `timestamp` DESC LIMIT 1");
        $last = $result ? $result->fetch(\PDO::FETCH_NUM) : false;

        return $last !== false && $last[0] == $ip && $last[1] == $type;
    }
}
