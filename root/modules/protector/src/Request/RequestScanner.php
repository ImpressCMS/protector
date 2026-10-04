<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Request;

use ImpressCMS\Module\Protector\Config\ProtectorConfig;
use ImpressCMS\Module\Protector\Log\AuditLog;
use ImpressCMS\Module\Protector\Log\LogLevel;

final class RequestScanner
{
    private const SYSTEM_GLOBALS = [
        'GLOBALS', '_SESSION', '_GET', '_POST', '_COOKIE', '_SERVER', '_REQUEST', '_ENV', '_FILES',
        'xoopsDB', 'xoopsUser', 'xoopsUserId', 'xoopsUserGroups', 'xoopsUserIsAdmin',
        'xoopsConfig', 'icmsConfig', 'xoopsOption', 'xoopsModule', 'xoopsModuleConfig',
    ];

    /** @var array<int, DoubtfulValue> */
    private array $doubtful = [];

    private bool $contaminated = false;

    public function __construct(
        private readonly ProtectorConfig $config,
        private readonly AuditLog $log,
        private readonly RequestMutator $mutator,
    ) {
    }

    public function scan(): void
    {
        $this->walk($_GET, 'G', []);
        $this->walk($_POST, 'P', []);
        $this->walk($_COOKIE, 'C', []);
    }

    public function isContaminated(): bool
    {
        return $this->contaminated;
    }

    /** @return array<int, DoubtfulValue> */
    public function doubtfulValues(): array
    {
        return $this->doubtful;
    }

    /** @param array<int, int|string> $path */
    private function walk(mixed $value, string $source, array $path): void
    {
        if (is_array($value)) {
            $this->walkArray($value, $source, $path);

            return;
        }

        $value = (string) $value;

        if ($this->config->enabled('san_nullbyte') && str_contains($value, "\0")) {
            $value = str_replace("\0", ' ', $value);
            $this->mutator->replace($source, $path, $value);
            $this->log->note("Injecting Null-byte '{$value}' found.\n");
            $this->log->write('NullByte', 0, false, LogLevel::Injection);
        }

        if (preg_match('?[\s\'"`/]?', $value)) {
            $this->doubtful[] = new DoubtfulValue($source, $path, $value);
        }
    }

    /**
     * @param array<int|string, mixed> $values
     * @param array<int, int|string> $path
     */
    private function walkArray(array $values, string $source, array $path): void
    {
        foreach ($values as $key => $value) {
            if (in_array($key, self::SYSTEM_GLOBALS, true)) {
                $this->contaminated = true;
                $this->log->noteIncident('CONTAMI', "Attempt to inject '{$key}' was found.\n");
            }

            $this->walk($value, $source, [...$path, $key]);
        }
    }
}
