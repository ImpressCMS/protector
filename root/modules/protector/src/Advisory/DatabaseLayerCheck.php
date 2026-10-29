<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Advisory;

final class DatabaseLayerCheck implements Check
{
    /**
     * @readonly
     */
    private string $databaseClass;
    /**
     * @readonly
     */
    private string $databaseType;
    /**
     * @readonly
     */
    private string $readyText;
    /**
     * @readonly
     */
    private string $notReadyText;
    public function __construct(string $databaseClass, string $databaseType, string $readyText, string $notReadyText)
    {
        $this->databaseClass = $databaseClass;
        $this->databaseType = $databaseType;
        $this->readyText = $readyText;
        $this->notReadyText = $notReadyText;
    }

    public function run(): CheckResult
    {
        $trapped = strtolower($this->databaseClass) === 'protectormysqldatabase';

        if (!$trapped && strncmp($this->databaseType, 'pdo.', strlen('pdo.')) !== 0) {
            return new CheckResult('databasefactory.php', '', false, $this->notReadyText);
        }

        return CheckResult::ok('databasefactory.php', $this->readyText);
    }
}
