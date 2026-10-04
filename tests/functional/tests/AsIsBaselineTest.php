<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Tests\Functional\Scenario;

use ImpressCMS\Module\Protector\Tests\Functional\KnownDefect;
use ImpressCMS\Module\Protector\Tests\Functional\Scenario;
use ImpressCMS\Module\Protector\Tests\Functional\SiteTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Runs only with PROTECTOR_PROFILE=as-is (module code exactly as in the repository). It records the facts that make
 * the "enabled" profile necessary. Under the default profile these scenarios are reported as skipped.
 */
#[Group('as-is')]
final class AsIsBaselineTest extends SiteTestCase
{
    protected function setUp(): void
    {
        if (self::config()->get('PROFILE') !== 'as-is') {
            $this->markTestSkipped('Runs only with PROTECTOR_PROFILE=as-is.');
        }

        parent::setUp();
    }

    #[Scenario('ASI-01', 'the module exactly as in the repository, installed on ImpressCMS 2.1', 'ordinary pages are requested', 'the postcheck stage never runs, so the preference cache is never written and Protector holds no preferences')]
    #[KnownDefect('S7', 'postcheck.inc.php waits for a class name that does not exist (Icms\Db\Legacy\icms_db_legacy_Factory)')]
    public function testPostcheckStageNeverRuns(): void
    {
        $this->probe($this->client());
        $second = $this->probe($this->client());

        $json = $second->json();
        $this->assertFalse($json['postcheck_guard_class_exists']);
        $this->assertTrue($json['real_factory_class_exists']);
        $this->assertSame(0, $json['protector_conf_keys']);
        $this->assertNull(self::layout()->configCacheFile());
    }

    #[Scenario('ASI-02', 'the module exactly as in the repository', 'a request carries an isolated comment and the preference "sanitize" is set', 'nothing is sanitised and nothing is logged: Protector does not protect at all')]
    #[KnownDefect('S7', 'postcheck.inc.php waits for a class name that does not exist (Icms\Db\Legacy\icms_db_legacy_Factory)')]
    public function testNothingIsProtected(): void
    {
        $this->configure(['isocom_action' => 1, 'union_action' => 3]);

        $response = $this->probe($this->client(), ['cid' => ',password /*', 'id' => '1 UNION SELECT 1']);

        $this->assertSame(',password /*', $response->json()['get']['cid']);
        $this->assertSame('1 UNION SELECT 1', $response->json()['get']['id']);
        $this->assertSame([], $this->logRows());
    }

    #[Scenario('ASI-03', 'any profile', 'a page asks PHP for the client address in two ways', '$_SERVER["REMOTE_ADDR"] has the address while filter_input(INPUT_SERVER, "REMOTE_ADDR") is null in this environment (nginx + PHP FastCGI), which is why every address-based check is inert without the patch')]
    #[KnownDefect('D4', 'the module reads the client address with filter_input(INPUT_SERVER, ...)')]
    public function testFilterInputDoesNotSeeTheClientAddress(): void
    {
        $json = $this->probe($this->client('127.0.0.2'))->json();

        $this->assertSame('127.0.0.2', $json['remote_addr_server']);
        $this->assertNull($json['remote_addr_filter_input']);
    }

    #[Scenario('ASI-04', 'the module exactly as in the repository', 'the signature of the database-trap class is compared with the core class it extends', 'the module declares query(string $sql, int $limit, int $start) while the core declares query(string $sql, ?int $limit, ?int $start), which is a fatal error as soon as the trap would be used')]
    #[KnownDefect('S11', 'ProtectorMySQLDatabase::query() is not compatible with Icms\Db\Legacy\Mysql\Proxy::query()')]
    public function testDatabaseTrapSignatureIsIncompatibleWithTheCore(): void
    {
        $module = (string) file_get_contents(self::layout()->liveTrustModuleDir() . '/class/ProtectorMysqlDatabase.class.php');
        $core = (string) file_get_contents(self::layout()->sitePath() . '/libraries/Icms/Db/Legacy/Mysql/Proxy.php');

        $this->assertStringContainsString('function query(string $sql, int $limit = 0, int $start = 0)', $module);
        $this->assertStringContainsString('function query(string $sql, ?int $limit = 0, ?int $start = 0)', $core);
    }
}
