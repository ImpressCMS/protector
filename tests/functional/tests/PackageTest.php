<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Tests\Functional\Scenario;

use ImpressCMS\Module\Protector\Tests\Functional\FileSystem;
use ImpressCMS\Module\Protector\Tests\Functional\Scenario;
use ImpressCMS\Module\Protector\Tests\Functional\SiteTestCase;

/**
 * The distributable package, built by bin/build-package from the committed HEAD, unzipped in the modules folder of a
 * site that runs the Protector 5.1 bundled with ImpressCMS 2.0.4.
 * @group lifecycle
 * @group package
 */
final class PackageTest extends SiteTestCase
{
    private const CUSTOM_FILTER = 'postcommon_post_zzpackage';

    private string $workDirectory = '';

    protected function tearDown(): void
    {
        $GLOBALS['protector_functional']['site']->restore();

        if ($this->workDirectory !== '') {
            FileSystem::removeTree($this->workDirectory);
        }

        parent::tearDown();
    }

    #[Scenario('LIF-12', 'a site that runs the Protector 5.1 bundled with ImpressCMS 2.0.4, with a custom filter in the trust path, a changed preference, a log record and a banned address', 'the package is unzipped in the modules folder and the administrator runs "update" for the module', 'no file of the old layout is left except data, the trust-path files are in place, the custom filter now lives in the module and still runs, preferences, log record and ban list are kept and the protection works')]
    public function testPackageUpgradesTheBundledProtector(): void
    {
        $zip = $this->buildPackage();
        $this->installTheBundledProtector();
        $trustFilter = self::layout()->liveTrustModuleDir() . '/filters_byconfig/' . self::CUSTOM_FILTER . '.php';
        file_put_contents($trustFilter, "<?php\nfunction protector_" . self::CUSTOM_FILTER . "() { \$_POST['marker'] = 'set-by-custom-filter'; return true; }\n");
        $before = $this->filesOfTheInstalledModule();
        $pdo = self::config()->pdo(self::config()->get('DB_NAME'));
        $prefix = self::config()->get('DB_PREFIX');
        $pdo->exec("UPDATE `{$prefix}_config` SET conf_value = '777' WHERE conf_name = 'bf_count' AND conf_title LIKE '\\_MI\\_PROTECTOR%'");
        $pdo->exec("INSERT INTO `{$prefix}_protector_log` (uid, ip, type, agent, description, `timestamp`) VALUES (0, '10.9.9.9', 'OLDROW', 'UA', 'written by the bundled release', NOW())");
        $this->saveIpLists($this->admin(), "127.0.0.50\n");
        $this->configure(['filters' => self::CUSTOM_FILTER]);
        $this->assertSame('set-by-custom-filter', $this->client()->post('/probe.php', ['msg' => 'hello'])->json()['post']['marker'], 'the custom filter runs on the bundled release');

        $archive = new \ZipArchive();
        $this->assertTrue($archive->open($zip) === true);
        $this->assertTrue($archive->extractTo(self::layout()->sitePath() . '/modules'));
        $shipped = $this->namesOf($archive);
        $archive->close();
        sleep(4);

        $this->admin()->updateModule('protector');

        $this->assertSame([], $this->staleFiles($before, $shipped));
        foreach (['precheck.inc.php', 'postcheck.inc.php'] as $forwarding) {
            $this->assertSame(
                str_replace("\r\n", "\n", (string) file_get_contents(self::layout()->repoTrustTree() . "/modules/protector/include/{$forwarding}")),
                (string) file_get_contents(self::layout()->liveTrustModuleDir() . "/include/{$forwarding}")
            );
        }
        $this->assertFileExists(self::layout()->customFilterDir() . '/' . self::CUSTOM_FILTER . '.php');
        $this->assertSame('777', $this->preference('bf_count'));
        $this->assertSame(1, (int) $pdo->query("SELECT COUNT(*) FROM `{$prefix}_protector_log` WHERE type = 'OLDROW'")->fetchColumn());
        $this->assertNotNull(self::layout()->badIpsFile());

        $this->warmUp();
        $this->assertSame('set-by-custom-filter', $this->client()->post('/probe.php', ['msg' => 'hello'])->json()['post']['marker'], 'the custom filter runs from the module directory');
        $this->assertStringContainsString('You are registered as BAD_IP by Protector.', $this->probe($this->client('127.0.0.50'))->body);
        $this->assertStringContainsString('SQL Injection found', $this->client('127.0.0.3')->get('/sqlq.php', ['q' => '1 UNION SELECT 1'])->body);
        $this->assertStringContainsString('OLDROW', $this->admin()->page('/modules/protector/admin/index.php')->body);
    }

    private function buildPackage(): string
    {
        $repository = str_replace('\\', '/', dirname(__DIR__, 3));
        $this->workDirectory = sys_get_temp_dir() . '/protector_package_' . bin2hex(random_bytes(4));
        mkdir($this->workDirectory, 0777, true);

        exec(sprintf('git -C %s rev-parse --verify --quiet %s 2>&1', escapeshellarg($repository), escapeshellarg('5.2^{commit}')), $ignored, $hasBranch);

        if ($hasBranch !== 0) {
            $this->markTestSkipped('The 5.2 branch is needed to list the obsolete files.');
        }

        exec(sprintf('%s %s --output=%s 2>&1', escapeshellarg(PHP_BINARY), escapeshellarg($repository . '/bin/build-package'), escapeshellarg($this->workDirectory)), $output, $status);
        $this->assertSame(0, $status, implode("\n", $output));

        $zips = glob($this->workDirectory . '/protector-*.zip') ?: [];
        $this->assertCount(1, $zips);

        return $zips[0];
    }

    private function installTheBundledProtector(): void
    {
        $previous = getenv('PROTECTOR_BUNDLE_FROM_CORE');
        putenv('PROTECTOR_BUNDLE_FROM_CORE=1');

        try {
            $GLOBALS['protector_functional']['site']->install(static function (string $message): void {
            });
        } finally {
            putenv($previous === false ? 'PROTECTOR_BUNDLE_FROM_CORE' : "PROTECTOR_BUNDLE_FROM_CORE={$previous}");
        }

        $this->assertFileExists(self::layout()->liveTrustModuleDir() . '/class/protector.php', 'the site runs the bundled 5.x code');
    }

    /**
     * @return list<string> "root:" and "trust:" paths relative to the module folder, data folders left out
     */
    private function filesOfTheInstalledModule(): array
    {
        $files = [];

        foreach (['root' => self::layout()->liveModuleDir(), 'trust' => self::layout()->liveTrustModuleDir()] as $kind => $directory) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS));

            foreach ($iterator as $file) {
                $relative = substr(FileSystem::normalize($file->getPathname()), strlen(FileSystem::normalize($directory)) + 1);

                if (strpos($relative, 'configs/') !== 0) {
                    $files[] = "{$kind}:{$relative}";
                }
            }
        }

        sort($files);

        return $files;
    }

    /**
     * @param list<string> $before
     * @param list<string> $shipped entry names of the package
     * @return list<string> files of the old installation that are neither shipped nor the site owner's own
     */
    private function staleFiles(array $before, array $shipped): array
    {
        $stale = [];

        foreach ($this->filesOfTheInstalledModule() as $file) {
            [$kind, $relative] = explode(':', $file, 2);
            $isShipped = $kind === 'root'
                ? in_array("protector/{$relative}", $shipped, true)
                : in_array("protector/include/trust_path/modules/protector/{$relative}", $shipped, true);

            $isOwners = $file === 'trust:filters_byconfig/' . self::CUSTOM_FILTER . '.php';

            if (!$isShipped && !$isOwners && !$this->isPlaceholder($kind, $relative) && in_array($file, $before, true)) {
                $stale[] = $file;
            }
        }

        return $stale;
    }

    /**
     * The core puts an index.html that goes back one page into every directory it copies; they are not module files.
     */
    private function isPlaceholder(string $kind, string $relative): bool
    {
        if (basename($relative) !== 'index.html') {
            return false;
        }

        $directory = $kind === 'root' ? self::layout()->liveModuleDir() : self::layout()->liveTrustModuleDir();

        return trim((string) file_get_contents("{$directory}/{$relative}")) === '<script>history.go(-1);</script>';
    }

    /**
     * @return list<string>
     */
    private function namesOf(\ZipArchive $archive): array
    {
        $names = [];

        for ($index = 0; $index < $archive->numFiles; $index++) {
            $names[] = (string) $archive->getNameIndex($index);
        }

        return $names;
    }
}
