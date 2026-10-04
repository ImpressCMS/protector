<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Config;

use ImpressCMS\Module\Protector\Database\PdoProvider;
use ImpressCMS\Module\Protector\Storage\AtomicFile;
use ImpressCMS\Module\Protector\Storage\DataPaths;
use ImpressCMS\Module\Protector\Storage\StoredArray;

final class ConfigStore
{
    private const TITLE_PATTERN = '_MI_PROTECTOR%';

    private const MINIMUM_PREFERENCES = 5;

    private ProtectorConfig $current;

    private string $cachedPayload;

    public function __construct(
        private readonly DataPaths $paths,
        private readonly ?PdoProvider $database = null,
        private readonly string $tablePrefix = '',
    ) {
        $this->cachedPayload = (string) @file_get_contents($this->paths->configCache());
        $this->current = new ProtectorConfig(StoredArray::decode($this->cachedPayload) ?? []);
    }

    public function current(): ProtectorConfig
    {
        return $this->current;
    }

    public function refreshFromDatabase(): bool
    {
        $connection = $this->database?->connection();

        if ($connection === null) {
            return false;
        }

        $statement = $connection->prepare("SELECT conf_name, conf_value FROM {$this->tablePrefix}_config WHERE conf_title LIKE :title");

        if ($statement === false || !$statement->execute(['title' => self::TITLE_PATTERN])) {
            return false;
        }

        $values = $statement->fetchAll(\PDO::FETCH_KEY_PAIR);

        if (count($values) < self::MINIMUM_PREFERENCES) {
            return false;
        }

        $payload = StoredArray::encode($values);

        if ($payload === $this->cachedPayload) {
            return true;
        }

        AtomicFile::write($this->paths->configCache(), $payload);
        $this->current = new ProtectorConfig($values);

        return true;
    }

    public function update(string $name, string $value): void
    {
        $connection = $this->database?->connection();

        if ($connection === null) {
            return;
        }

        $statement = $connection->prepare(
            "UPDATE {$this->tablePrefix}_config SET conf_value = :value WHERE conf_title LIKE :title AND conf_name = :name"
        );
        if ($statement !== false) {
            $statement->execute(['value' => $value, 'title' => self::TITLE_PATTERN, 'name' => $name]);
        }

        $this->refreshFromDatabase();
    }
}
