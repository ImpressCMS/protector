<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Tests\Unit;

use ImpressCMS\Module\Protector\Log\LogLevel;

final class AuditLogTest extends UnitTestCase
{
    private \PDO $database;

    protected function setUp(): void
    {
        parent::setUp();

        $this->database = new \PDO('sqlite::memory:', null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        $this->database->exec('CREATE TABLE test_protector_log (lid INTEGER PRIMARY KEY AUTOINCREMENT, uid INTEGER, ip TEXT, agent TEXT, type TEXT, description TEXT, `timestamp` TEXT)');
        $_SERVER['REMOTE_ADDR'] = '203.0.113.7';
        $_SERVER['HTTP_USER_AGENT'] = 'Test/1.0 <b>';
    }

    public function testRecordHoldsTheTypeAddressAgentUserAndAccumulatedMessage(): void
    {
        $log = $this->auditLog($this->configStore(['log_level' => '255']), $this->database);
        $log->noteIncident('ISOCOM', "first 'quoted' \\ note\n");
        $log->note('second');

        $log->write('ISOCOM', 5, false, LogLevel::Injection);

        $row = $this->rows()[0];
        $this->assertSame('ISOCOM', $row['type']);
        $this->assertSame('203.0.113.7', $row['ip']);
        $this->assertSame('Test/1.0 ', $row['agent']);
        $this->assertSame(5, (int) $row['uid']);
        $this->assertSame("first 'quoted' \\ note\nsecond", $row['description']);
    }

    public function testOnlyTheFirstEventOfARequestIsWritten(): void
    {
        $log = $this->auditLog($this->configStore(['log_level' => '255']), $this->database);

        $log->write('ONE');
        $log->write('TWO');

        $this->assertSame(['ONE'], array_column($this->rows(), 'type'));
    }

    public function testLevelsSwitchedOffInThePreferencesAreNotWritten(): void
    {
        $log = $this->auditLog($this->configStore(['log_level' => (string) LogLevel::Dos->value]), $this->database);

        $log->write('INJECTION', 0, false, LogLevel::Injection);
        $log->write('DOS', 0, false, LogLevel::Dos);

        $this->assertSame(['DOS'], array_column($this->rows(), 'type'));
    }

    public function testRepeatOfTheLastRecordIsSkippedWhenRequested(): void
    {
        $this->database->exec("INSERT INTO test_protector_log (uid, ip, type, agent, description, `timestamp`) VALUES (0, '203.0.113.7', 'DoS', '', '', '2030-01-01')");
        $log = $this->auditLog($this->configStore(['log_level' => '255']), $this->database);

        $log->write('DoS', 0, true);

        $this->assertCount(1, $this->rows());
    }

    public function testDifferentTypeOrAddressIsStillWritten(): void
    {
        $this->database->exec("INSERT INTO test_protector_log (uid, ip, type, agent, description, `timestamp`) VALUES (0, '203.0.113.99', 'DoS', '', '', '2030-01-01')");
        $log = $this->auditLog($this->configStore(['log_level' => '255']), $this->database);

        $log->write('DoS', 0, true);

        $this->assertCount(2, $this->rows());
    }

    public function testMissingDatabaseConnectionNeitherFailsNorEndsTheRequest(): void
    {
        $log = $this->auditLog($this->configStore(['log_level' => '255']), null);

        $log->write('NULLBYTE');

        $this->assertSame([], $this->rows());
    }

    /** @return array<int, array<string, mixed>> */
    private function rows(): array
    {
        return $this->database->query('SELECT * FROM test_protector_log ORDER BY lid')->fetchAll(\PDO::FETCH_ASSOC);
    }
}
