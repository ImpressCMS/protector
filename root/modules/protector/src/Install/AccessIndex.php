<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Install;

use ImpressCMS\Module\Protector\Database\PdoProvider;

final class AccessIndex
{
    private const NAME = 'ip_uri_expire';

    public function __construct(
        private readonly PdoProvider $database,
        private readonly string $tablePrefix,
    ) {
    }

    public function ensure(): string
    {
        $connection = $this->database->connection();

        if ($connection === null) {
            return 'No database connection: the index of the access table was not checked.';
        }

        $table = "{$this->tablePrefix}_protector_access";

        try {
            $existing = $connection->prepare("SHOW INDEX FROM {$table} WHERE Key_name = :name");
            $existing->execute(['name' => self::NAME]);

            if ($existing->fetch() !== false) {
                return '';
            }

            $connection->exec("ALTER TABLE {$table} ADD KEY " . self::NAME . ' (ip(45), request_uri(191), expire)');
        } catch (\PDOException $exception) {
            return "The index of the access table could not be added: {$exception->getMessage()}";
        }

        return 'Index added to the access table.';
    }
}
