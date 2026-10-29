<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Config;

use ImpressCMS\Module\Protector\Database\PdoProvider;
use ImpressCMS\Module\Protector\Storage\AtomicFile;
use ImpressCMS\Module\Protector\Storage\DataPaths;
use ImpressCMS\Module\Protector\Storage\StoredArray;

final class ConfigStore
{
    /**
     * @readonly
     */
    private DataPaths $paths;
    /**
     * @readonly
     */
    private ?PdoProvider $database = null;
    /**
     * @readonly
     */
    private string $tablePrefix = '';
    private const TITLE_PATTERN = '_MI_PROTECTOR%';

    private const MINIMUM_PREFERENCES = 5;

    private ProtectorConfig $current;

    private string $cachedPayload;

    public function __construct(
        DataPaths $paths,
        ?PdoProvider $database = null,
        string $tablePrefix = ''
    ) {
        $this->paths = $paths;
        $this->database = $database;
        $this->tablePrefix = $tablePrefix;
        $this->cachedPayload = (string) @file_get_contents($this->paths->forReading($this->paths->configCache()));
        $this->current = new ProtectorConfig(StoredArray::decode($this->cachedPayload) ?? []);
    }

    public function current(): ProtectorConfig
    {
        return $this->current;
    }

    public function refreshFromDatabase(): bool
    {
        $connection = ($nullsafeVariable1 = $this->database) ? $nullsafeVariable1->connection() : null;

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

        if ($payload === $this->cachedPayload && is_file($this->paths->configCache())) {
            return true;
        }

        AtomicFile::write($this->paths->configCache(), $payload);
        $this->current = new ProtectorConfig($values);

        return true;
    }

    public function update(string $name, string $value): void
    {
        $connection = ($nullsafeVariable2 = $this->database) ? $nullsafeVariable2->connection() : null;

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
