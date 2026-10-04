<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Tests\Unit;

use ImpressCMS\Module\Protector\Config\ConfigStore;
use ImpressCMS\Module\Protector\Database\PdoProvider;
use ImpressCMS\Module\Protector\Dos\AccessRepository;

final class DatabaseRepositoriesTest extends UnitTestCase
{
    private \PDO $database;

    protected function setUp(): void
    {
        parent::setUp();

        $this->database = new \PDO('sqlite::memory:', null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        $this->database->exec('CREATE TABLE test_protector_access (ip TEXT, request_uri TEXT, malicious_actions TEXT, expire INTEGER)');
        $this->database->exec('CREATE TABLE test_config (conf_name TEXT, conf_title TEXT, conf_value TEXT)');
    }

    public function testRecordedRequestsAreCountedPerAddressAndUri(): void
    {
        $access = $this->repository();

        $access->record('10.0.0.1', '/a', 60);
        $access->record('10.0.0.1', '/a', 60);
        $access->record('10.0.0.1', '/b', 60);
        $access->record('10.0.0.2', '/a', 60);

        $this->assertSame(4, $access->countAll());
        $this->assertSame(3, $access->countFromIp('10.0.0.1'));
        $this->assertSame(2, $access->countFromIpForUri('10.0.0.1', '/a'));
    }

    public function testExpiredRowsAreIgnoredEvenBeforeTheyAreCollected(): void
    {
        $this->database->exec("INSERT INTO test_protector_access (ip, request_uri, expire) VALUES ('10.0.0.1', '/a', " . (time() - 5) . ')');
        $access = $this->repository();

        $access->record('10.0.0.1', '/a', 60);

        $this->assertSame(1, $access->countFromIp('10.0.0.1'));
        $this->assertSame(2, (int) $this->database->query('SELECT COUNT(*) FROM test_protector_access')->fetchColumn());
    }

    public function testGarbageCollectionRemovesExpiredRows(): void
    {
        $this->database->exec("INSERT INTO test_protector_access (ip, request_uri, expire) VALUES ('10.0.0.1', '/a', " . (time() - 5) . ')');
        $access = $this->repository();
        $access->record('10.0.0.2', '/a', 60);

        $access->collectGarbage();

        $this->assertSame(1, (int) $this->database->query('SELECT COUNT(*) FROM test_protector_access')->fetchColumn());
    }

    public function testGarbageCollectionOnlyRunsOnSomeRequests(): void
    {
        $this->database->exec("INSERT INTO test_protector_access (ip, request_uri, expire) VALUES ('10.0.0.1', '/a', " . (time() - 5) . ')');
        $access = new AccessRepository($this->provider(), 'test', 1000000);

        for ($request = 0; $request < 20; $request++) {
            $access->collectGarbage();
        }

        $this->assertSame(1, (int) $this->database->query('SELECT COUNT(*) FROM test_protector_access')->fetchColumn());
    }

    public function testFailedLoginsAreCountedSeparately(): void
    {
        $access = $this->repository();

        $access->recordFailedLogin('10.0.0.1', '/user.php', "BRUTE FORCE: o'brien", 600);
        $access->record('10.0.0.1', '/user.php', 60);

        $this->assertSame(1, $access->countFailedLogins('10.0.0.1'));
        $this->assertSame(0, $access->countFailedLogins('10.0.0.2'));
        $this->assertSame("BRUTE FORCE: o'brien", $this->database->query('SELECT malicious_actions FROM test_protector_access WHERE malicious_actions IS NOT NULL')->fetchColumn());
    }

    public function testQuotesInRequestDataCannotBreakTheStatements(): void
    {
        $access = $this->repository();
        $uri = "/a.php?x=' OR '1'='1";

        $access->record("10.0.0.1' OR '1'='1", $uri, 60);

        $this->assertSame(1, $access->countFromIpForUri("10.0.0.1' OR '1'='1", $uri));
        $this->assertSame(0, $access->countFromIp('10.0.0.1'));
    }

    public function testMissingTableOrConnectionCountsAsZero(): void
    {
        $this->database->exec('DROP TABLE test_protector_access');

        $this->assertSame(0, $this->repository()->countAll());
        $this->assertSame(0, (new AccessRepository($this->provider(null), 'test'))->countAll());
    }

    public function testPreferencesAreReadFromTheDatabaseAndCached(): void
    {
        $this->seedPreferences();
        $store = new ConfigStore($this->paths(), $this->provider(), 'test');

        $this->assertTrue($store->refreshFromDatabase());

        $this->assertSame(7, $store->current()->int('bf_count'));
        $this->assertSame($store->current()->toArray(), (new ConfigStore($this->paths()))->current()->toArray());
    }

    public function testTooFewPreferencesMeanTheModuleIsNotInstalled(): void
    {
        $this->database->exec("INSERT INTO test_config VALUES ('bf_count', '_MI_PROTECTOR_BF', '7')");
        $store = new ConfigStore($this->paths(), $this->provider(), 'test');

        $this->assertFalse($store->refreshFromDatabase());
        $this->assertTrue($store->current()->isEmpty());
    }

    public function testUpdatingAPreferenceOnlyTouchesProtectorRows(): void
    {
        $this->seedPreferences();
        $this->database->exec("INSERT INTO test_config VALUES ('bf_count', '_MI_OTHER_BF', '99')");
        $store = new ConfigStore($this->paths(), $this->provider(), 'test');

        $store->update('bf_count', "13'; DROP TABLE test_config; --");

        $this->assertSame("13'; DROP TABLE test_config; --", $store->current()->string('bf_count'));
        $this->assertSame('99', $this->database->query("SELECT conf_value FROM test_config WHERE conf_title = '_MI_OTHER_BF'")->fetchColumn());
    }

    private function seedPreferences(): void
    {
        foreach (['bf_count' => '7', 'a' => '1', 'b' => '2', 'c' => '3', 'd' => '4'] as $name => $value) {
            $this->database->exec("INSERT INTO test_config VALUES ('{$name}', '_MI_PROTECTOR_{$name}', '{$value}')");
        }
    }

    private function repository(): AccessRepository
    {
        return new AccessRepository($this->provider(), 'test', 1);
    }

    private function provider(?\PDO $connection = null): PdoProvider
    {
        $connection ??= func_num_args() === 0 ? $this->database : null;

        return new class ($connection) implements PdoProvider {
            public function __construct(private readonly ?\PDO $connection)
            {
            }

            public function connection(): ?\PDO
            {
                return $this->connection;
            }
        };
    }
}
