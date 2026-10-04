<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Config;

use ImpressCMS\Module\Protector\Storage\AtomicFile;
use ImpressCMS\Module\Protector\Storage\DataPaths;
use ImpressCMS\Module\Protector\Storage\StoredArray;

final class ConfigStore
{
    private const CONFIG_TITLE_PREFIX = '_MI_PROTECTOR';

    private ProtectorConfig $current;

    private string $cachedPayload;

    public function __construct(private readonly DataPaths $paths)
    {
        $this->cachedPayload = (string) @file_get_contents($this->paths->configCache());
        $this->current = new ProtectorConfig(StoredArray::decode($this->cachedPayload) ?? []);
    }

    public function current(): ProtectorConfig
    {
        return $this->current;
    }

    public function refreshFromDatabase(): bool
    {
        $result = \icms::$xoopsDB->queryF(
            'SELECT conf_name,conf_value FROM ' . XOOPS_DB_PREFIX . "_config WHERE conf_title like '" . self::CONFIG_TITLE_PREFIX . "%'"
        );

        if (!$result || \icms::$xoopsDB->getRowsNum($result) < 5) {
            return false;
        }

        $values = [];

        while ([$name, $value] = \icms::$xoopsDB->fetchRow($result)) {
            $values[$name] = $value;
        }

        $payload = serialize($values);

        if ($payload === $this->cachedPayload) {
            return true;
        }

        AtomicFile::write($this->paths->configCache(), $payload);
        $this->current = new ProtectorConfig($values);

        return true;
    }

    public function update(string $name, string $value): void
    {
        \icms::$xoopsDB->queryF(
            'UPDATE `' . \icms::$xoopsDB->prefix('config') . "` SET `conf_value`='" . addslashes($value)
            . "' WHERE `conf_title` like '" . self::CONFIG_TITLE_PREFIX . "%' AND `conf_name`='" . addslashes($name) . "' LIMIT 1"
        );
        $this->refreshFromDatabase();
    }
}
