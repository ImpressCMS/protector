<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Tests\Unit;

use ImpressCMS\Module\Protector\Install\LegacyFilterMigrator;
use ImpressCMS\Module\Protector\Install\ObsoleteFiles;
use ImpressCMS\Module\Protector\Install\TrustPathFiles;

final class PackageInstallTest extends UnitTestCase
{
    private string $site = '';

    private string $trust = '';

    private string $list = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->site = "{$this->directory}/site";
        $this->trust = "{$this->directory}/trust";
        $this->list = "{$this->directory}/obsolete-files.txt";

        mkdir("{$this->site}/modules/protector", 0777, true);
        mkdir("{$this->trust}/modules/protector", 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->site);
        $this->removeTree($this->trust);
        $this->removeTree("{$this->directory}/package");
        is_file($this->list) && unlink($this->list);

        parent::tearDown();
    }

    public function testObsoleteFilesAreRemovedAndNothingElse(): void
    {
        $this->write("{$this->site}/modules/protector/search.php");
        $this->write("{$this->site}/modules/protector/admin/old.php");
        $this->write("{$this->site}/modules/protector/admin/keep.php");
        $this->write("{$this->trust}/modules/protector/class/protector.php");
        $this->writeList(['root:modules/protector/search.php', 'root:modules/protector/admin/old.php', 'trust:modules/protector/class/protector.php']);

        $result = $this->obsoleteFiles()->remove();

        $this->assertSame(
            ['root:modules/protector/search.php', 'root:modules/protector/admin/old.php', 'trust:modules/protector/class/protector.php'],
            $result['removed']
        );
        $this->assertSame([], $result['failed']);
        $this->assertFileDoesNotExist("{$this->site}/modules/protector/search.php");
        $this->assertFileExists("{$this->site}/modules/protector/admin/keep.php");
        $this->assertFileDoesNotExist("{$this->trust}/modules/protector/class/protector.php");
    }

    public function testDirectoriesThatBecomeEmptyAreRemovedButNeverTheModuleDirectories(): void
    {
        $this->write("{$this->trust}/modules/protector/patches/ImpressCMS1.1/databasefactory.php");
        $this->writeList(['trust:modules/protector/patches/ImpressCMS1.1/databasefactory.php']);

        $this->obsoleteFiles()->remove();

        $this->assertDirectoryDoesNotExist("{$this->trust}/modules/protector/patches");
        $this->assertDirectoryExists("{$this->trust}/modules/protector");
    }

    public function testDirectoriesThatOnlyHoldThePlaceholderPageGoAwayToo(): void
    {
        $this->write("{$this->trust}/modules/protector/class/protector.php");
        $this->write("{$this->trust}/modules/protector/class/index.html", '<script>history.go(-1);</script>');
        $this->write("{$this->site}/modules/protector/blocks/blocks.php");
        $this->write("{$this->site}/modules/protector/blocks/index.html", '<script>history.go(-1);</script>');
        $this->write("{$this->site}/modules/protector/blocks/notes.txt", 'site owner');
        $this->writeList(['trust:modules/protector/class/protector.php', 'root:modules/protector/blocks/blocks.php']);

        $this->obsoleteFiles()->remove();

        $this->assertDirectoryDoesNotExist("{$this->trust}/modules/protector/class");
        $this->assertFileExists("{$this->site}/modules/protector/blocks/notes.txt");
        $this->assertFileExists("{$this->site}/modules/protector/blocks/index.html");
    }

    public function testEntriesOutsideTheModuleOrWithTraversalAreIgnored(): void
    {
        $this->write("{$this->site}/index.php");
        $this->write("{$this->trust}/modules/protector/configs/badipsabc123");
        $this->write("{$this->site}/modules/protector/../index.php");
        $this->writeList([
            'root:index.php',
            'root:modules/protector/../index.php',
            'trust:modules/protector/../../mainfile.php',
            'trust:modules/protector/configs/badipsabc123',
            'other:modules/protector/search.php',
            'root:modules\\protector\\search.php',
        ]);

        $this->assertSame([], $this->obsoleteFiles()->entries());
        $this->assertSame(['removed' => [], 'failed' => []], $this->obsoleteFiles()->remove());
        $this->assertFileExists("{$this->site}/index.php");
        $this->assertFileExists("{$this->trust}/modules/protector/configs/badipsabc123");
    }

    public function testMissingListMeansNothingIsRemoved(): void
    {
        $this->assertSame([], $this->obsoleteFiles()->entries());
        $this->assertSame(['removed' => [], 'failed' => []], $this->obsoleteFiles()->remove());
    }

    public function testTrustFilesAreRecognisedInTheList(): void
    {
        $this->writeList(['root:modules/protector/search.php', 'trust:modules/protector/filters_byconfig/shipped.php']);

        $this->assertTrue($this->obsoleteFiles()->listsTrustFile('modules/protector/filters_byconfig/shipped.php'));
        $this->assertFalse($this->obsoleteFiles()->listsTrustFile('modules/protector/search.php'));
        $this->assertFalse($this->obsoleteFiles()->listsTrustFile('modules/protector/filters_byconfig/custom.php'));
    }

    public function testCustomFiltersAreCopiedFromTheTrustPathIntoTheModule(): void
    {
        $this->write("{$this->trust}/modules/protector/filters_enabled/postcommon_post_mine.php", '<?php // mine');
        $this->write("{$this->trust}/modules/protector/filters_byconfig/precommon_badip_mine.php", '<?php // byconfig');

        $migrated = $this->migrator()->migrate();

        $this->assertSame(['filters_byconfig/precommon_badip_mine.php', 'filters_enabled/postcommon_post_mine.php'], $migrated);
        $this->assertSame('<?php // mine', file_get_contents("{$this->site}/modules/protector/filters_enabled/postcommon_post_mine.php"));
        $this->assertFileExists("{$this->trust}/modules/protector/filters_enabled/postcommon_post_mine.php");
    }

    public function testShippedFiltersAndExistingTargetsAreNotCopied(): void
    {
        $this->write("{$this->trust}/modules/protector/filters_byconfig/shipped.php", '<?php // old');
        $this->write("{$this->trust}/modules/protector/filters_byconfig/precommon_badip_mine.php", '<?php // old mine');
        $this->write("{$this->site}/modules/protector/filters_byconfig/precommon_badip_mine.php", '<?php // new mine');
        $this->writeList(['trust:modules/protector/filters_byconfig/shipped.php']);

        $this->assertSame([], $this->migrator()->migrate());
        $this->assertFileDoesNotExist("{$this->site}/modules/protector/filters_byconfig/shipped.php");
        $this->assertSame('<?php // new mine', file_get_contents("{$this->site}/modules/protector/filters_byconfig/precommon_badip_mine.php"));
    }

    public function testOnlyPlainFilterFileNamesAreMigrated(): void
    {
        foreach (['bad-name.php', 'notes.txt', '.hidden.php', 'a.b.php'] as $name) {
            $this->write("{$this->trust}/modules/protector/filters_enabled/{$name}");
        }
        mkdir("{$this->trust}/modules/protector/filters_enabled/folder.php");

        $this->assertSame([], $this->migrator()->migrate());
        rmdir("{$this->trust}/modules/protector/filters_enabled/folder.php");
    }

    public function testMigrationWithoutALegacyFolderDoesNothing(): void
    {
        $this->assertSame([], $this->migrator()->migrate());
    }

    public function testTrustPathFilesAreCopiedAndOverwriteOldOnes(): void
    {
        $source = "{$this->directory}/package";
        $this->write("{$source}/include/precheck.inc.php", '<?php // forward');
        $this->write("{$source}/public_check.php", '<?php // public');
        $this->write("{$this->trust}/modules/protector/include/precheck.inc.php", '<?php // protector 5');

        $message = (new TrustPathFiles($source, "{$this->trust}/modules/protector"))->ensure();

        $this->assertStringContainsString('placed', $message);
        $this->assertSame('<?php // forward', file_get_contents("{$this->trust}/modules/protector/include/precheck.inc.php"));
        $this->assertSame('<?php // public', file_get_contents("{$this->trust}/modules/protector/public_check.php"));
    }

    public function testTrustPathFilesAreCreatedWhenTheTrustFolderDoesNotExistYet(): void
    {
        $source = "{$this->directory}/package";
        $this->write("{$source}/include/postcheck.inc.php");
        $this->removeTree("{$this->trust}/modules");

        (new TrustPathFiles($source, "{$this->trust}/modules/protector"))->ensure();

        $this->assertFileExists("{$this->trust}/modules/protector/include/postcheck.inc.php");
    }

    public function testWithoutBundledCopiesNothingHappens(): void
    {
        $message = (new TrustPathFiles("{$this->directory}/absent", "{$this->trust}/modules/protector"))->ensure();

        $this->assertSame('', $message);
        $this->assertFileDoesNotExist("{$this->trust}/modules/protector/include");
    }

    public function testFailureToWriteIsReported(): void
    {
        $source = "{$this->directory}/package";
        $this->write("{$source}/include/precheck.inc.php");
        $this->write("{$this->directory}/blocker", 'a file where a folder is needed');

        $message = (new TrustPathFiles($source, "{$this->directory}/blocker"))->ensure();

        $this->assertStringContainsString('include/precheck.inc.php', $message);
        $this->assertStringContainsString('not be protected', $message);
        unlink("{$this->directory}/blocker");
    }

    private function obsoleteFiles(): ObsoleteFiles
    {
        return new ObsoleteFiles($this->list, $this->site, $this->trust);
    }

    private function migrator(): LegacyFilterMigrator
    {
        return new LegacyFilterMigrator("{$this->trust}/modules/protector", "{$this->site}/modules/protector", $this->obsoleteFiles());
    }

    /** @param list<string> $entries */
    private function writeList(array $entries): void
    {
        file_put_contents($this->list, implode("\n", $entries) . "\n");
    }

    private function write(string $path, string $content = '<?php'): void
    {
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        file_put_contents($path, $content);
    }

    private function removeTree(string $path): void
    {
        if (is_file($path)) {
            unlink($path);

            return;
        }

        if (!is_dir($path)) {
            return;
        }

        foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $child) {
            $this->removeTree("{$path}/{$child}");
        }

        rmdir($path);
    }
}
