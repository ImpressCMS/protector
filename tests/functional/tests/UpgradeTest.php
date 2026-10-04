<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Tests\Functional\Scenario;

use ImpressCMS\Module\Protector\Tests\Functional\FileSystem;
use ImpressCMS\Module\Protector\Tests\Functional\Scenario;
use ImpressCMS\Module\Protector\Tests\Functional\SiteTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Installs the previous release, gives it data, copies the current files over it without deleting anything (what an
 * administrator does) and runs the module update. Every later phase has to pass this too.
 */
#[Group('lifecycle')]
#[Group('upgrade')]
final class UpgradeTest extends SiteTestCase
{
    private const PREVIOUS_RELEASE_REF = '5.2';

    private string $exported = '';

    protected function tearDown(): void
    {
        $GLOBALS['protector_functional']['site']->restore();

        if ($this->exported !== '') {
            FileSystem::removeTree($this->exported);
        }

        parent::tearDown();
    }

    #[Scenario('LIF-08', 'a site running the previous release (branch 5.2) with a changed preference, a log record and a banned address', 'the current files are copied over the installation without removing anything and the administrator runs "update" for the module', 'preferences, log record and ban list are kept; the banned address is still blocked; checks, SQL trap and both admin pages work with the new code')]
    public function testUpgradeFromThePreviousRelease(): void
    {
        $this->upgradeFromThePreviousRelease();
        $pdo = self::config()->pdo(self::config()->get('DB_NAME'));
        $prefix = self::config()->get('DB_PREFIX');

        $this->assertSame('777', $this->preference('bf_count'));
        $this->assertSame(33, (int) $pdo->query("SELECT COUNT(*) FROM `{$prefix}_config` WHERE conf_title LIKE '\\_MI\\_PROTECTOR%'")->fetchColumn());
        $this->assertSame(1, (int) $pdo->query("SELECT COUNT(*) FROM `{$prefix}_protector_log` WHERE type = 'OLDROW'")->fetchColumn());

        $banned = $this->probe($this->client('127.0.0.50'));
        $this->assertStringContainsString('You are registered as BAD_IP by Protector.', $banned->body);

        $served = $this->probe($this->client('127.0.0.2'), ['cid' => ',password /*']);
        $this->assertTrue($this->isServed($served));
        $this->assertContains('ISOCOM', $this->logTypes());

        $trap = $this->client('127.0.0.3')->get('/sqlq.php', ['q' => '1 UNION SELECT 1']);
        $this->assertStringContainsString('SQL Injection found', $trap->body);

        $admin = $this->admin();
        $start = $admin->page('/modules/protector/admin/index.php');
        $advisory = $admin->page('/modules/protector/admin/index.php', ['page' => 'advisory']);
        $this->assertStringContainsString('OLDROW', $start->body);
        $this->assertStringContainsString('allow_url_fopen', $advisory->body);
    }

    #[Scenario('LIF-11', 'a site running the previous release (branch 5.2) with a banned address, its state files in the old data directory', 'the current files are copied over the installation and the administrator runs "update" for the module', 'the state files have moved to the new data directory, the old ones are gone, the access table has the composite index and the banned address is still blocked')]
    public function testUpgradeMovesTheDataFilesAndAddsTheIndex(): void
    {
        $this->upgradeFromThePreviousRelease();
        $pdo = self::config()->pdo(self::config()->get('DB_NAME'));
        $prefix = self::config()->get('DB_PREFIX');

        $this->assertNotNull(self::layout()->badIpsFile());
        $this->assertNotNull(self::layout()->configCacheFile());
        $this->assertSame([], glob(self::layout()->legacyDataDir() . '/badips*') ?: []);
        $this->assertSame([], glob(self::layout()->legacyDataDir() . '/configcache*') ?: []);
        $this->assertGreaterThan(0, (int) $pdo->query("SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = '{$prefix}_protector_access' AND index_name = 'ip_uri_expire'")->fetchColumn());
        $this->assertStringContainsString('You are registered as BAD_IP by Protector.', $this->probe($this->client('127.0.0.50'))->body);
    }

    private function upgradeFromThePreviousRelease(): void
    {
        $this->exported = $this->exportPreviousRelease();
        $pdo = self::config()->pdo(self::config()->get('DB_NAME'));
        $prefix = self::config()->get('DB_PREFIX');
        $site = $GLOBALS['protector_functional']['site'];

        putenv('PROTECTOR_MODULE_SOURCE=' . $this->exported);

        try {
            $site->install(static function (string $message): void {
            });
        } finally {
            putenv('PROTECTOR_MODULE_SOURCE');
        }

        $pdo->exec("UPDATE `{$prefix}_config` SET conf_value = '777' WHERE conf_name = 'bf_count' AND conf_title LIKE '\\_MI\\_PROTECTOR%'");
        $pdo->exec("INSERT INTO `{$prefix}_protector_log` (uid, ip, type, agent, description, `timestamp`) VALUES (0, '10.9.9.9', 'OLDROW', 'UA', 'written by the previous release', NOW())");
        $admin = $this->admin();
        $this->saveIpLists($admin, "127.0.0.50\n");
        $this->warmUp();

        FileSystem::copyTree(self::layout()->repoRootTree() . '/modules/protector', self::layout()->liveModuleDir());
        FileSystem::copyTree(self::layout()->repoTrustTree() . '/modules/protector', self::layout()->liveTrustModuleDir());
        sleep(4);

        $this->admin()->updateModule('protector');
    }

    private function exportPreviousRelease(): string
    {
        $repository = str_replace('\\', '/', dirname(__DIR__, 3));
        $target = sys_get_temp_dir() . '/protector_previous_' . bin2hex(random_bytes(4));
        mkdir($target, 0777, true);

        $command = sprintf(
            'git -C %s archive %s root trust_path | tar -x -C %s 2>&1',
            escapeshellarg($repository),
            escapeshellarg(self::PREVIOUS_RELEASE_REF),
            escapeshellarg($target),
        );
        exec($command, $output, $status);

        if ($status !== 0 || !is_dir($target . '/root/modules/protector')) {
            FileSystem::removeTree($target);
            $this->markTestSkipped('Cannot export git ref "' . self::PREVIOUS_RELEASE_REF . '": ' . implode(' ', $output));
        }

        return $target;
    }
}
