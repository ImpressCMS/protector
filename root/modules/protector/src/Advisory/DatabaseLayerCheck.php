<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Advisory;

final class DatabaseLayerCheck implements Check
{
    public function __construct(
        private readonly string $databaseClass,
        private readonly string $databaseType,
        private readonly string $readyText,
        private readonly string $notReadyText,
    ) {
    }

    public function run(): CheckResult
    {
        $trapped = strtolower($this->databaseClass) === 'protectormysqldatabase';

        if (!$trapped && !str_starts_with($this->databaseType, 'pdo.')) {
            return new CheckResult('databasefactory.php', '', false, $this->notReadyText);
        }

        return CheckResult::ok('databasefactory.php', $this->readyText);
    }
}
