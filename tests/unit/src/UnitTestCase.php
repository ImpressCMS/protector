<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Tests\Unit;

use ImpressCMS\Module\Protector\Config\ConfigStore;
use ImpressCMS\Module\Protector\Database\PdoProvider;
use ImpressCMS\Module\Protector\Http\Responder;
use ImpressCMS\Module\Protector\Log\AuditLog;
use ImpressCMS\Module\Protector\Storage\DataPaths;
use PHPUnit\Framework\TestCase;

abstract class UnitTestCase extends TestCase
{
    protected string $directory = '';

    private array $savedSuperglobals = [];

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/protector_unit_' . bin2hex(random_bytes(4));
        mkdir($this->directory);

        $this->savedSuperglobals = [$_GET, $_POST, $_COOKIE, $_REQUEST, $_SERVER, $_FILES];
    }

    protected function tearDown(): void
    {
        [$_GET, $_POST, $_COOKIE, $_REQUEST, $_SERVER, $_FILES] = $this->savedSuperglobals;

        foreach (glob("{$this->directory}/*") ?: [] as $file) {
            unlink($file);
        }

        rmdir($this->directory);
    }

    protected function paths(): DataPaths
    {
        return new DataPaths($this->directory, 'test01');
    }

    /** @param array<string, mixed> $values */
    protected function configStore(array $values = ['log_level' => '0']): ConfigStore
    {
        file_put_contents($this->paths()->configCache(), serialize($values));

        return new ConfigStore($this->paths());
    }

    protected function auditLog(?ConfigStore $config = null, ?\PDO $connection = null): AuditLog
    {
        $database = new class ($connection) implements PdoProvider {
            public function __construct(private readonly ?\PDO $connection)
            {
            }

            public function connection(): ?\PDO
            {
                return $this->connection;
            }
        };

        return new AuditLog($config ?? $this->configStore(), $database, 'test');
    }

    protected function throwingResponder(): Responder
    {
        return new class implements Responder {
            public function halt(string $message = ''): never
            {
                throw new \RuntimeException("halted: {$message}");
            }
        };
    }
}
