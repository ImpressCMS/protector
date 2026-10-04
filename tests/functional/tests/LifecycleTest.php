<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Tests\Functional\Scenario;

use ImpressCMS\Module\Protector\Tests\Functional\Scenario;
use ImpressCMS\Module\Protector\Tests\Functional\SiteTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * These scenarios change the installation itself, so every test puts the golden snapshot back afterwards.
 */
#[Group('lifecycle')]
final class LifecycleTest extends SiteTestCase
{
    /** preference name => [stored default, value type, form type]; order is the display order */
    private const PREFERENCES = [
        'global_disabled' => ['0', 'int', 'yesno'],
        'default_lang' => ['english', 'text', 'textbox'],
        'log_level' => ['255', 'int', 'select'],
        'banip_time0' => ['86400', 'int', 'textbox'],
        'reliable_ips' => ['a:2:{i:0;s:9:"^192.168.";i:1;s:9:"127.0.0.1";}', 'array', 'textsarea'],
        'session_fixed_topbit' => ['24', 'int', 'textbox'],
        'groups_denyipmove' => ['a:1:{i:0;s:1:"1";}', 'array', 'group_multi'],
        'san_nullbyte' => ['1', 'int', 'yesno'],
        'die_badext' => ['1', 'int', 'yesno'],
        'contami_action' => ['3', 'int', 'select'],
        'isocom_action' => ['0', 'int', 'select'],
        'union_action' => ['0', 'int', 'select'],
        'id_forceintval' => ['0', 'int', 'yesno'],
        'file_dotdot' => ['1', 'int', 'yesno'],
        'bf_count' => ['10', 'int', 'textbox'],
        'bwlimit_count' => ['0', 'int', 'textbox'],
        'dos_skipmodules' => ['', 'text', 'textbox'],
        'dos_expire' => ['60', 'int', 'textbox'],
        'dos_f5count' => ['20', 'int', 'textbox'],
        'dos_f5action' => ['exit', 'text', 'select'],
        'dos_crcount' => ['40', 'int', 'textbox'],
        'dos_craction' => ['exit', 'text', 'select'],
        'dos_crsafe' => ['/(msnbot|Googlebot|Yahoo! Slurp)/i', 'text', 'textbox'],
        'bip_except' => ['a:1:{i:0;s:1:"1";}', 'array', 'group_multi'],
        'disable_features' => ['1', 'int', 'select'],
        'enable_dblayertrap' => ['1', 'int', 'yesno'],
        'dblayertrap_wo_server' => ['0', 'int', 'yesno'],
        'enable_bigumbrella' => ['1', 'int', 'yesno'],
        'spamcount_uri4user' => ['0', 'int', 'textbox'],
        'spamcount_uri4guest' => ['5', 'int', 'textbox'],
        'filters' => ['', 'text', 'textsarea'],
        'enable_manip_check' => ['0', 'int', 'yesno'],
        'manip_value' => ['', 'text', 'textbox'],
    ];

    protected function tearDown(): void
    {
        $GLOBALS['protector_functional']['site']->restore();
        parent::tearDown();
    }

    #[Scenario('LIF-01', 'the module was installed by the ImpressCMS installer', 'the installation is inspected', 'the module is registered, active, at version 5.1.0; it has 33 preferences with the documented names, types and defaults (including dos_skipmodules, which the core uses to detect Protector); and the log and access tables exist')]
    public function testFreshInstallation(): void
    {
        $pdo = self::config()->pdo(self::config()->get('DB_NAME'));
        $prefix = self::config()->get('DB_PREFIX');

        $module = $pdo->query("SELECT version, isactive, hasadmin, hasmain, hasconfig FROM `{$prefix}_modules` WHERE dirname = 'protector'")->fetch();
        $this->assertSame('5.1.0', $module['version']);
        $this->assertSame([1, 1, 0, 1], [(int) $module['isactive'], (int) $module['hasadmin'], (int) $module['hasmain'], (int) $module['hasconfig']]);

        $rows = $pdo->query("SELECT conf_name, conf_value, conf_valuetype, conf_formtype FROM `{$prefix}_config` WHERE conf_title LIKE '\\_MI\\_PROTECTOR%' ORDER BY conf_order, conf_id")->fetchAll();
        $actual = [];

        foreach ($rows as $row) {
            $actual[$row['conf_name']] = [$row['conf_value'], $row['conf_valuetype'], $row['conf_formtype']];
        }

        $this->assertSame(self::PREFERENCES, $actual);

        $tables = $pdo->query("SHOW TABLES LIKE '{$prefix}\\_protector\\_%'")->fetchAll(\PDO::FETCH_COLUMN);
        sort($tables);
        $this->assertSame(["{$prefix}_protector_access", "{$prefix}_protector_log"], $tables);

        $logColumns = $pdo->query("SHOW COLUMNS FROM `{$prefix}_protector_log`")->fetchAll(\PDO::FETCH_COLUMN);
        $this->assertSame(['lid', 'uid', 'ip', 'type', 'agent', 'description', 'extra', 'timestamp'], $logColumns);

        $accessColumns = $pdo->query("SHOW COLUMNS FROM `{$prefix}_protector_access`")->fetchAll(\PDO::FETCH_COLUMN);
        $this->assertSame(['ip', 'request_uri', 'malicious_actions', 'expire'], $accessColumns);
    }

    #[Scenario('LIF-02', 'the module was installed by the ImpressCMS installer', 'the files that connect Protector to the core are inspected', 'the preload file declares IcmsPreloadProtector and includes the module\'s pre- and post-check files')]
    public function testPreloadIsInstalled(): void
    {
        $preload = (string) file_get_contents(self::layout()->preloadFile());

        $this->assertStringContainsString('class IcmsPreloadProtector', $preload);
        $this->assertStringContainsString('eventStartCoreBoot', $preload);
        $this->assertStringContainsString('precheck.inc.php', $preload);
        $this->assertStringContainsString('eventFinishCoreBoot', $preload);
        $this->assertStringContainsString('postcheck.inc.php', $preload);
    }

    #[Scenario('LIF-03', 'the module is installed', 'the control panel dashboard is opened', 'it does not warn that Protector cannot be found')]
    public function testDashboardDoesNotWarnWhileInstalled(): void
    {
        $page = $this->admin()->page('/admin.php');

        $this->assertStringNotContainsString('unable to find if Protector', $page->body);
    }

    #[Scenario('LIF-04', 'the module is installed', 'the administrator uninstalls the module in the control panel', 'the module, its preferences and both tables are gone; the preload file is removed; the dashboard now warns that Protector cannot be found')]
    public function testUninstall(): void
    {
        $pdo = self::config()->pdo(self::config()->get('DB_NAME'));
        $prefix = self::config()->get('DB_PREFIX');

        $this->admin()->uninstallModule('protector');

        $this->assertSame(0, (int) $pdo->query("SELECT COUNT(*) FROM `{$prefix}_modules` WHERE dirname = 'protector'")->fetchColumn());
        $this->assertSame(0, (int) $pdo->query("SELECT COUNT(*) FROM `{$prefix}_config` WHERE conf_title LIKE '\\_MI\\_PROTECTOR%'")->fetchColumn());
        $this->assertSame([], $pdo->query("SHOW TABLES LIKE '{$prefix}\\_protector\\_%'")->fetchAll());
        clearstatcache();
        $this->assertFileDoesNotExist(self::layout()->preloadFile());
        $this->assertStringContainsString('unable to find if Protector', $this->admin()->page('/admin.php')->body);
    }

    #[Scenario('LIF-05', 'the module was uninstalled', 'the administrator installs it again', 'the module, its 33 preferences, both tables and the preload file are back')]
    public function testReinstallAfterUninstall(): void
    {
        $pdo = self::config()->pdo(self::config()->get('DB_NAME'));
        $prefix = self::config()->get('DB_PREFIX');

        $this->admin()->uninstallModule('protector');
        $this->admin()->installModule('protector');

        $this->assertSame(1, (int) $pdo->query("SELECT COUNT(*) FROM `{$prefix}_modules` WHERE dirname = 'protector' AND isactive = 1")->fetchColumn());
        $this->assertSame(33, (int) $pdo->query("SELECT COUNT(*) FROM `{$prefix}_config` WHERE conf_title LIKE '\\_MI\\_PROTECTOR%'")->fetchColumn());
        $this->assertCount(2, $pdo->query("SHOW TABLES LIKE '{$prefix}\\_protector\\_%'")->fetchAll());
        clearstatcache();
        $this->assertFileExists(self::layout()->preloadFile());
    }

    #[Scenario('LIF-06', 'a preference was changed to a non-default value and a log record exists', 'the administrator runs "update" for the module in the control panel', 'the changed preference, all 33 preferences and the log record are still there')]
    public function testUpdateKeepsSettingsAndData(): void
    {
        $pdo = self::config()->pdo(self::config()->get('DB_NAME'));
        $prefix = self::config()->get('DB_PREFIX');
        $this->configure(['bf_count' => 777]);
        $pdo->exec("INSERT INTO `{$prefix}_protector_log` (uid, ip, type, agent, description, `timestamp`) VALUES (0, '10.0.0.1', 'KEEPME', 'UA', 'kept across update', NOW())");

        $this->admin()->updateModule('protector');

        $this->assertSame('777', $this->preference('bf_count'));
        $this->assertSame(33, (int) $pdo->query("SELECT COUNT(*) FROM `{$prefix}_config` WHERE conf_title LIKE '\\_MI\\_PROTECTOR%'")->fetchColumn());
        $this->assertSame(['KEEPME'], $this->logTypes());
    }

    #[Scenario('LIF-07', 'the module is installed and active', 'an ordinary page is requested', 'the page is served and Protector\'s runtime preference cache has been written in the module\'s data directory')]
    public function testRuntimeCacheIsWrittenOnTheFirstRequest(): void
    {
        $response = $this->probe($this->client());

        $this->assertTrue($this->isServed($response));
        $this->assertNotNull(self::layout()->configCacheFile());
        $this->assertGreaterThan(0, $response->json()['protector_conf_keys']);
    }

    #[Scenario('LIF-09', 'the module is installed', 'it is updated, uninstalled and installed again in the control panel', 'its two admin templates are registered exactly once after the installation, the update and the reinstallation, and are removed by the uninstallation')]
    public function testTemplatesAreRegisteredOnceAndRemovedOnUninstall(): void
    {
        $expected = ['protector_admin_advisory.html', 'protector_admin_index.html'];

        $this->assertSame($expected, $this->templateNames());

        $this->admin()->updateModule('protector');
        $this->assertSame($expected, $this->templateNames());

        $this->admin()->uninstallModule('protector');
        $this->assertSame([], $this->templateNames());

        $this->admin()->installModule('protector');
        $this->assertSame($expected, $this->templateNames());
    }

    /**
     * @return list<string>
     */
    private function templateNames(): array
    {
        $prefix = self::config()->get('DB_PREFIX');

        return self::config()->pdo(self::config()->get('DB_NAME'))
            ->query("SELECT tpl_file FROM `{$prefix}_tplfile` WHERE tpl_module = 'protector' ORDER BY tpl_file")
            ->fetchAll(\PDO::FETCH_COLUMN);
    }
}
