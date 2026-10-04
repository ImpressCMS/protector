<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Database;

use ImpressCMS\Module\Protector\Config\ConfigStore;

final class DatabaseTrap
{
    private const NEEDLES = ['information_schema', 'select', "'", '"'];

    private const MINIMUM_LENGTH = 6;

    /** @var array<int, string> */
    private array $doubtful = [];

    public function __construct(private readonly ConfigStore $config)
    {
    }

    public function arm(bool $force): void
    {
        if ($this->coreIsNotLoading()) {
            return;
        }

        $this->doubtful = [];
        $this->collect($_GET);
        $this->collect($_POST);
        $this->collect($_COOKIE);

        if (!$this->config->current()->enabled('dblayertrap_wo_server')) {
            $this->collect($_SERVER);
        }

        if ($this->doubtful === [] && !$force) {
            return;
        }

        if (!defined('XOOPS_DB_ALTERNATIVE')) {
            define('XOOPS_DB_ALTERNATIVE', 'ProtectorMysqlDatabase');
        }

        class_exists(SqlInjectionGuard::class);
    }

    /** @return array<int, string> */
    public function doubtfulValues(): array
    {
        return $this->doubtful;
    }

    private function coreIsNotLoading(): bool
    {
        return !empty($GLOBALS['xoopsOption']['nocommon'])
            || defined('_LEGACY_PREVENT_EXEC_COMMON_')
            || defined('_LEGACY_PREVENT_LOAD_CORE_');
    }

    private function collect(mixed $value): void
    {
        if (is_array($value)) {
            array_walk($value, $this->collect(...));

            return;
        }

        $text = (string) $value;

        if (strlen($text) < self::MINIMUM_LENGTH) {
            return;
        }

        foreach (self::NEEDLES as $needle) {
            if (stristr($text, $needle)) {
                $this->doubtful[] = $text;
            }
        }
    }
}
