<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Tests\Functional\Site;

use ImpressCMS\Module\Protector\Tests\Functional\Config;

/**
 * Copies every table of the test database into a sibling snapshot database and back.
 * Avoids any dependency on a mysqldump binary.
 */
final class DbSnapshot
{
    public function __construct(private readonly Config $config)
    {
    }

    public function create(): int
    {
        return $this->copyAll($this->config->get('DB_NAME'), $this->config->get('SNAPSHOT_DB'));
    }

    public function restore(): int
    {
        return $this->copyAll($this->config->get('SNAPSHOT_DB'), $this->config->get('DB_NAME'));
    }

    public function dropDatabase(string $name): void
    {
        $this->assertOwnDatabase($name);
        $this->config->pdo()->exec("DROP DATABASE IF EXISTS `{$name}`");
    }

    private function copyAll(string $from, string $to): int
    {
        $this->assertOwnDatabase($from);
        $this->assertOwnDatabase($to);

        $pdo = $this->config->pdo();
        $pdo->exec("DROP DATABASE IF EXISTS `{$to}`");
        $pdo->exec("CREATE DATABASE `{$to}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');

        $tables = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = " . $pdo->quote($from) . " AND TABLE_TYPE = 'BASE TABLE'")
            ->fetchAll(\PDO::FETCH_COLUMN);

        foreach ($tables as $table) {
            $pdo->exec("CREATE TABLE `{$to}`.`{$table}` LIKE `{$from}`.`{$table}`");
            $pdo->exec("INSERT INTO `{$to}`.`{$table}` SELECT * FROM `{$from}`.`{$table}`");
        }

        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');

        return count($tables);
    }

    private function assertOwnDatabase(string $name): void
    {
        $own = [$this->config->get('DB_NAME'), $this->config->get('SNAPSHOT_DB')];

        if (!in_array($name, $own, true)) {
            throw new \LogicException("Refusing to touch database '{$name}': not one of the harness databases.");
        }
    }
}
