<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Tests\Unit;

final class PackageBuildTest extends UnitTestCase
{
    private static ?string $sharedZip = null;

    private string $script = '';

    private string $output = '';

    public static function tearDownAfterClass(): void
    {
        if (self::$sharedZip !== null) {
            unlink(self::$sharedZip);
            rmdir(dirname(self::$sharedZip));
            self::$sharedZip = null;
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->script = dirname(__DIR__, 3) . '/bin/build-package';
        $this->output = "{$this->directory}/dist";
    }

    protected function tearDown(): void
    {
        foreach (glob("{$this->output}/*") ?: [] as $file) {
            unlink($file);
        }

        is_dir($this->output) && rmdir($this->output);

        parent::tearDown();
    }

    public function testEveryEntryIsInsideTheProtectorFolder(): void
    {
        $zip = $this->build();

        foreach ($this->names($zip) as $name) {
            $this->assertStringStartsWith('protector/', $name);
        }
    }

    public function testTheModuleItsShimsAndTheNewInstallClassesAreShipped(): void
    {
        $names = $this->names($this->build());

        foreach ([
            'protector/icms_version.php',
            'protector/include/onupdate.php',
            'protector/src/autoload.php',
            'protector/src/Install/ObsoleteFiles.php',
            'protector/include/trust_path/modules/protector/include/precheck.inc.php',
            'protector/include/trust_path/modules/protector/include/postcheck.inc.php',
            'protector/include/obsolete-files.txt',
        ] as $required) {
            $this->assertContains($required, $names);
        }
    }

    public function testDevelopmentFilesAreNotShipped(): void
    {
        foreach ($this->names($this->build()) as $name) {
            $this->assertDoesNotMatchRegularExpression('#(^|/)(tests?|docs?|vendor|\.git[^/]*)(/|$)#', $name);
        }
    }

    public function testPhpFilesHaveUnixLineEndingsAndFileModes(): void
    {
        $archive = $this->open($this->build());

        for ($index = 0; $index < $archive->numFiles; $index++) {
            $name = (string) $archive->getNameIndex($index);
            $operatingSystem = 0;
            $attributes = 0;
            $archive->getExternalAttributesIndex($index, $operatingSystem, $attributes);

            $this->assertSame(\ZipArchive::OPSYS_UNIX, $operatingSystem, $name);
            $this->assertContains($attributes >> 16, [0100644, 0100755], $name);

            if (substr($name, -4) === '.php') {
                $this->assertStringNotContainsString("\r\n", (string) $archive->getFromIndex($index), $name);
            }
        }
    }

    public function testTheZipNameCarriesTheModuleVersionAndTheCommit(): void
    {
        $zip = $this->build();
        $version = trim((string) file_get_contents(dirname(__DIR__, 3) . '/root/modules/protector/include/version.txt'));

        $this->assertMatchesRegularExpression('/^protector-' . preg_quote($version, '/') . '-[0-9a-f]{8}\.zip$/', basename($zip));
    }

    public function testTheObsoleteListNamesOldFilesAndNothingThatIsShipped(): void
    {
        $archive = $this->open($this->build());
        $names = $this->names($archive->filename);
        $entries = array_filter(explode("\n", (string) $archive->getFromName('protector/include/obsolete-files.txt')));

        $this->assertContains('root:modules/protector/xoops_version.php', $entries);
        $this->assertContains('trust:modules/protector/class/protector.php', $entries);

        foreach ($entries as $entry) {
            $this->assertMatchesRegularExpression('#^(root|trust):modules/protector/[^.].*#', $entry);
            $this->assertStringNotContainsString('/configs/', $entry);
            $this->assertStringNotContainsString('..', $entry);

            $relative = substr($entry, strpos($entry, ':') + 1 + strlen('modules/protector/'));
            $shipped = strpos($entry, 'root:') === 0 ? "protector/{$relative}" : "protector/include/trust_path/modules/protector/{$relative}";
            $this->assertNotContains($shipped, $names, $entry);
        }
    }

    public function testWithoutTheObsoleteListTheFileIsLeftOut(): void
    {
        $zip = $this->build(['--no-obsolete']);

        $this->assertNotContains('protector/include/obsolete-files.txt', $this->names($zip));
    }

    public function testAnUnknownObsoleteRefIsAnError(): void
    {
        [$status, $output] = $this->runScript(['--obsolete-from=no-such-ref', "--output={$this->output}"]);

        $this->assertSame(1, $status);
        $this->assertStringContainsString('Unknown ref', $output);
    }

    public function testAnUnknownArgumentPrintsTheUsage(): void
    {
        [$status, $output] = $this->runScript(['--bogus']);

        $this->assertSame(1, $status);
        $this->assertStringContainsString('Usage:', $output);
    }

    public function testHelpSucceeds(): void
    {
        [$status, $output] = $this->runScript(['--help']);

        $this->assertSame(0, $status);
        $this->assertStringContainsString('--obsolete-from', $output);
    }

    /** @param list<string> $extra */
    private function build(array $extra = []): string
    {
        if ($extra === [] && self::$sharedZip !== null) {
            return self::$sharedZip;
        }

        if (!in_array('--no-obsolete', $extra, true) && !$this->hasRef('5.2') && !$this->hasRef('origin/5.2')) {
            $this->markTestSkipped('The 5.2 branch is not available in this clone.');
        }

        $output = $extra === [] ? sys_get_temp_dir() . '/protector_package_' . bin2hex(random_bytes(4)) : $this->output;
        [$status, $text] = $this->runScript(array_merge(["--output={$output}"], $extra));
        $this->assertSame(0, $status, $text);
        $this->assertMatchesRegularExpression('/Package ready: (.+\.zip)/', $text);
        preg_match('/Package ready: (.+\.zip)/', $text, $match);
        $zip = trim($match[1]);

        if ($extra === []) {
            self::$sharedZip = $zip;
        }

        return $zip;
    }

    private function hasRef(string $ref): bool
    {
        exec('git -C ' . escapeshellarg(dirname($this->script, 2)) . ' rev-parse --verify --quiet ' . escapeshellarg("{$ref}^{commit}") . ' 2>&1', $lines, $status);

        return $status === 0;
    }

    /**
     * @param list<string> $arguments
     * @return array{0: int, 1: string}
     */
    private function runScript(array $arguments): array
    {
        $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($this->script) . ' ' . implode(' ', array_map('escapeshellarg', $arguments)) . ' 2>&1';
        $lines = [];
        exec($command, $lines, $status);

        return [$status, implode("\n", $lines)];
    }

    private function open(string $path): \ZipArchive
    {
        $archive = new \ZipArchive();
        $this->assertTrue($archive->open($path) === true, "Cannot open {$path}");

        return $archive;
    }

    /** @return list<string> */
    private function names(string $path): array
    {
        $archive = $this->open($path);
        $names = [];

        for ($index = 0; $index < $archive->numFiles; $index++) {
            $names[] = (string) $archive->getNameIndex($index);
        }

        return $names;
    }
}
