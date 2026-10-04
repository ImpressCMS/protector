<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Dos;

final class AccessRepository
{
    private const TABLE = 'protector_access';

    public function purgeExpired(): bool
    {
        return \icms::$xoopsDB->queryF('DELETE FROM ' . $this->table() . ' WHERE expire < UNIX_TIMESTAMP()') !== false;
    }

    public function record(string $ip, string $uri, int $secondsToLive): void
    {
        \icms::$xoopsDB->queryF(
            'INSERT INTO ' . $this->table() . " SET ip='{$ip}',request_uri='{$uri}',expire=UNIX_TIMESTAMP()+'{$secondsToLive}'"
        );
    }

    public function recordFailedLogin(string $ip, string $uri, string $maliciousAction, int $secondsToLive): void
    {
        \icms::$xoopsDB->queryF(
            'INSERT INTO ' . $this->table()
            . " SET ip='{$ip}',request_uri='{$uri}',malicious_actions='{$maliciousAction}',expire=UNIX_TIMESTAMP()+{$secondsToLive}"
        );
    }

    public function countAll(): int
    {
        return $this->count('');
    }

    public function countFromIp(string $ip): int
    {
        return $this->count(" WHERE ip='{$ip}'");
    }

    public function countFromIpForUri(string $ip, string $uri): int
    {
        return $this->count(" WHERE ip='{$ip}' AND request_uri='{$uri}'");
    }

    public function countFailedLogins(string $ip): int
    {
        return $this->count(" WHERE ip='{$ip}' AND malicious_actions like 'BRUTE FORCE:%'");
    }

    private function count(string $where): int
    {
        $result = \icms::$xoopsDB->query('SELECT COUNT(*) FROM ' . $this->table() . $where);
        [$count] = \icms::$xoopsDB->fetchRow($result);

        return (int) $count;
    }

    private function table(): string
    {
        return \icms::$xoopsDB->prefix(self::TABLE);
    }
}
