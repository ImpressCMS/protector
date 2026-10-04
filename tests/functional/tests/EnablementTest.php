<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Tests\Functional\Scenario;

use ImpressCMS\Module\Protector\Tests\Functional\Scenario;
use ImpressCMS\Module\Protector\Tests\Functional\SiteTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * The three fixes that make Protector work at all on ImpressCMS 2.1 (S7, D4, S11). Until Phase 1 these scenarios
 * documented the broken behaviour (ASI-01 to ASI-04); the first Phase 1 commit flipped them.
 */
#[Group('enablement')]
final class EnablementTest extends SiteTestCase
{
    #[Scenario('ENA-01', 'the module installed from the repository on ImpressCMS 2.1', 'ordinary pages are requested', 'the postcheck stage runs: the 33 preferences are loaded from the database and the preference cache file is written (S7 fixed)')]
    public function testPostcheckStageRuns(): void
    {
        $this->probe($this->client());
        $second = $this->probe($this->client());

        $this->assertTrue($second->json()['real_factory_class_exists']);
        $this->assertSame(33, $second->json()['protector_conf_keys']);
        $this->assertNotNull(self::layout()->configCacheFile());
    }

    #[Scenario('ENA-02', 'isolated-comment action "sanitize" and UNION action "exit"', 'a request carries both an isolated comment and a UNION', 'the comment is closed, the UNION ends the request, and only the first event of the request is logged (one record per request): Protector protects (S7 fixed)')]
    public function testProtectionIsActive(): void
    {
        $this->configure(['isocom_action' => 1, 'union_action' => 3]);

        $response = $this->probe($this->client(), ['cid' => ',password /*', 'id' => '1 UNION SELECT 1']);

        $this->assertStringContainsString('Protector detects attacking actions', $response->body);
        $this->assertSame(['ISOCOM'], $this->logTypes());
    }

    #[Scenario('ENA-03', 'a visitor from 127.0.0.2, whatever PHP\'s filter_input() returns in this environment', 'the visitor triggers a check that is logged', 'the record carries the visitor\'s address, taken from $_SERVER (D4 fixed)')]
    public function testClientAddressComesFromTheServerVariables(): void
    {
        $this->probe($this->client('127.0.0.2'), ['cid' => ',password /*']);

        $this->assertSame(['127.0.0.2'], array_column($this->logRows(), 'ip'));
    }

    #[Scenario('ENA-04', 'the module installed from the repository on ImpressCMS 2.1', 'the database-trap class is compared with the core class it extends', 'its query() signature is compatible, so the trap can be loaded (S11 fixed)')]
    public function testDatabaseTrapSignatureIsCompatibleWithTheCore(): void
    {
        $module = (string) file_get_contents(self::layout()->databaseTrapClassFile());
        $core = (string) file_get_contents(self::layout()->sitePath() . '/libraries/Icms/Db/Legacy/Mysql/Proxy.php');

        $this->assertStringContainsString('function query(string $sql, ?int $limit = 0, ?int $start = 0)', $module);
        $this->assertStringContainsString('function query(string $sql, ?int $limit = 0, ?int $start = 0)', $core);
    }
}
