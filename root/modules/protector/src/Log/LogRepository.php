<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Log;

use ImpressCMS\Module\Protector\Database\PdoProvider;

final class LogRepository
{
    public function __construct(
        private readonly PdoProvider $database,
        private readonly string $tablePrefix,
    ) {
    }

    public function count(): int
    {
        return (int) $this->statement("SELECT COUNT(*) FROM {$this->table()}")?->fetchColumn();
    }

    /** @return list<LogEntry> */
    public function page(int $offset, int $limit): array
    {
        $statement = $this->statement(
            'SELECT l.lid, l.uid, l.ip, l.agent, l.type, l.description, UNIX_TIMESTAMP(l.`timestamp`) AS moment, u.uname'
            . " FROM {$this->table()} l LEFT JOIN {$this->tablePrefix}_users u ON l.uid = u.uid"
            . ' ORDER BY l.`timestamp` DESC, l.lid DESC LIMIT :limit OFFSET :offset',
            ['limit' => $limit, 'offset' => $offset],
        );

        $entries = [];

        while ($statement && ($row = $statement->fetch(\PDO::FETCH_ASSOC))) {
            $entries[] = new LogEntry(
                (int) $row['lid'],
                (int) $row['uid'],
                (string) $row['ip'],
                (string) $row['agent'],
                (string) $row['type'],
                (string) $row['description'],
                (int) $row['moment'],
                $row['uname'] === null ? null : (string) $row['uname'],
            );
        }

        return $entries;
    }

    /** @param array<int, int|string> $ids */
    public function delete(array $ids): void
    {
        $ids = array_values(array_map('intval', $ids));

        if ($ids === []) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $this->statement("DELETE FROM {$this->table()} WHERE lid IN ({$placeholders})", $ids);
    }

    public function deleteAll(): void
    {
        $this->statement("DELETE FROM {$this->table()}");
    }

    public function compact(): int
    {
        $statement = $this->statement("SELECT lid, ip, type FROM {$this->table()} ORDER BY lid DESC");
        $seen = [];
        $duplicates = [];

        while ($statement && ($row = $statement->fetch(\PDO::FETCH_NUM))) {
            $key = $row[1] . "\0" . $row[2];

            if (isset($seen[$key])) {
                $duplicates[] = (int) $row[0];

                continue;
            }

            $seen[$key] = true;
        }

        foreach (array_chunk($duplicates, 500) as $chunk) {
            $this->delete($chunk);
        }

        return count($duplicates);
    }

    /** @param array<int|string, int|string> $parameters */
    private function statement(string $sql, array $parameters = []): ?\PDOStatement
    {
        $connection = $this->database->connection();

        if ($connection === null) {
            return null;
        }

        try {
            $statement = $connection->prepare($sql);

            if ($statement === false) {
                return null;
            }

            foreach ($parameters as $name => $value) {
                $statement->bindValue(is_int($name) ? $name + 1 : ":{$name}", $value, is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
            }

            return $statement->execute() ? $statement : null;
        } catch (\PDOException) {
            return null;
        }
    }

    private function table(): string
    {
        return "{$this->tablePrefix}_protector_log";
    }
}
