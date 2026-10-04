<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Dos;

use ImpressCMS\Module\Protector\Database\PdoProvider;

final class AccessRepository
{
    private const TABLE = 'protector_access';

    public function __construct(
        private readonly PdoProvider $database,
        private readonly string $tablePrefix,
        private readonly int $collectOneRequestIn = 20,
    ) {
    }

    public function collectGarbage(): void
    {
        if ($this->collectOneRequestIn > 1 && random_int(1, $this->collectOneRequestIn) !== 1) {
            return;
        }

        $this->run("DELETE FROM {$this->table()} WHERE expire < :now", ['now' => time()]);
    }

    public function record(string $ip, string $uri, int $secondsToLive): void
    {
        $this->run(
            "INSERT INTO {$this->table()} (ip, request_uri, expire) VALUES (:ip, :uri, :expire)",
            ['ip' => $ip, 'uri' => $uri, 'expire' => time() + $secondsToLive],
        );
    }

    public function recordFailedLogin(string $ip, string $uri, string $maliciousAction, int $secondsToLive): void
    {
        $this->run(
            "INSERT INTO {$this->table()} (ip, request_uri, malicious_actions, expire) VALUES (:ip, :uri, :action, :expire)",
            ['ip' => $ip, 'uri' => $uri, 'action' => $maliciousAction, 'expire' => time() + $secondsToLive],
        );
    }

    public function countAll(): int
    {
        return $this->count('', []);
    }

    public function countFromIp(string $ip): int
    {
        return $this->count(' AND ip = :ip', ['ip' => $ip]);
    }

    public function countFromIpForUri(string $ip, string $uri): int
    {
        return $this->count(' AND ip = :ip AND request_uri = :uri', ['ip' => $ip, 'uri' => $uri]);
    }

    public function countFailedLogins(string $ip): int
    {
        return $this->count(" AND ip = :ip AND malicious_actions LIKE 'BRUTE FORCE:%'", ['ip' => $ip]);
    }

    /** @param array<string, int|string> $parameters */
    private function count(string $condition, array $parameters): int
    {
        $statement = $this->run(
            "SELECT COUNT(*) FROM {$this->table()} WHERE expire >= :now{$condition}",
            ['now' => time(), ...$parameters],
        );

        return $statement === null ? 0 : (int) $statement->fetchColumn();
    }

    /** @param array<string, int|string> $parameters */
    private function run(string $sql, array $parameters): ?\PDOStatement
    {
        $connection = $this->database->connection();

        if ($connection === null) {
            return null;
        }

        try {
            $statement = $connection->prepare($sql);

            return $statement !== false && $statement->execute($parameters) ? $statement : null;
        } catch (\PDOException) {
            return null;
        }
    }

    private function table(): string
    {
        return "{$this->tablePrefix}_" . self::TABLE;
    }
}
