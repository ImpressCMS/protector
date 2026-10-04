<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Tests\Unit;

use ImpressCMS\Module\Protector\Ban\BanList;
use ImpressCMS\Module\Protector\Config\ConfigStore;
use ImpressCMS\Module\Protector\Install\DataDirectory;
use ImpressCMS\Module\Protector\Install\PreloadInstaller;
use ImpressCMS\Module\Protector\Storage\DataPaths;

final class InstallTest extends UnitTestCase
{
    private string $legacy = '';

    private string $current = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->legacy = $this->directory . '/legacy';
        $this->current = $this->directory . '/cache/protector';
        mkdir($this->legacy);
    }

    protected function tearDown(): void
    {
        foreach ([$this->legacy, $this->current, $this->directory . '/cache', $this->directory . '/preloads'] as $folder) {
            foreach (glob("{$folder}/*") ?: [] as $file) {
                is_file($file) && unlink($file);
            }

            is_dir($folder) && rmdir($folder);
        }

        parent::tearDown();
    }

    public function testDataDirectoryIsCreatedWithAnIndexFile(): void
    {
        $this->assertTrue($this->dataDirectory()->ensure());

        $this->assertDirectoryExists($this->current);
        $this->assertFileExists("{$this->current}/index.html");
    }

    public function testLegacyStateFilesAreMovedToTheNewDirectory(): void
    {
        file_put_contents("{$this->legacy}/badipsabc123", 'bad');
        file_put_contents("{$this->legacy}/configcacheabc123", 'cache');
        file_put_contents("{$this->legacy}/index.html", '');
        file_put_contents("{$this->legacy}/unrelated", 'keep');

        $moved = $this->dataDirectory()->migrateLegacyFiles();

        $this->assertSame(['badipsabc123', 'configcacheabc123'], $moved);
        $this->assertSame('bad', file_get_contents("{$this->current}/badipsabc123"));
        $this->assertFileDoesNotExist("{$this->legacy}/badipsabc123");
        $this->assertFileExists("{$this->legacy}/unrelated");
    }

    public function testMigrationNeverOverwritesFilesThatAlreadyExistInTheNewDirectoryAndDropsTheOldCopy(): void
    {
        mkdir($this->current, 0777, true);
        file_put_contents("{$this->legacy}/badipsabc123", 'old');
        file_put_contents("{$this->current}/badipsabc123", 'new');

        $this->assertSame([], $this->dataDirectory()->migrateLegacyFiles());
        $this->assertSame('new', file_get_contents("{$this->current}/badipsabc123"));
        $this->assertFileDoesNotExist("{$this->legacy}/badipsabc123");
    }

    public function testMigrationWithoutALegacyDirectoryDoesNothing(): void
    {
        rmdir($this->legacy);

        $this->assertSame([], $this->dataDirectory()->migrateLegacyFiles());
    }

    public function testFilesAreReadFromTheLegacyDirectoryUntilTheyAreMoved(): void
    {
        file_put_contents("{$this->legacy}/badipsabc123", serialize(['10.0.0.1' => time() + 600]) . "\n");
        $paths = $this->paths();

        $this->assertSame(['10.0.0.1'], (new BanList($paths))->addresses());
        $this->assertSame("{$this->current}/badipsabc123", $paths->badIps());
    }

    public function testWritesGoToTheNewDirectoryAndTakePrecedence(): void
    {
        file_put_contents("{$this->legacy}/badipsabc123", serialize(['10.0.0.1' => time() + 600]) . "\n");
        $bans = new BanList($this->paths());

        $bans->register('10.0.0.2', time() + 600);

        $this->assertFileExists("{$this->current}/badipsabc123");
        $this->assertSame(['10.0.0.2'], array_values(array_diff($bans->addresses(), ['10.0.0.1'])));
        $this->assertSame(['10.0.0.1'], array_values(array_diff((new BanList($this->paths()))->addresses(), ['10.0.0.2'])));
    }

    public function testPreferenceCacheIsReadFromTheLegacyDirectoryAndRewrittenInTheNewOne(): void
    {
        file_put_contents("{$this->legacy}/configcacheabc123", serialize(['bf_count' => '7']));

        $store = new ConfigStore($this->paths());

        $this->assertSame(7, $store->current()->int('bf_count'));
    }

    public function testPreloadIsCopiedOnlyWhenMissingAndNotAlreadyRunning(): void
    {
        mkdir("{$this->directory}/preloads");
        file_put_contents("{$this->directory}/source.php", '<?php // preload');
        $target = "{$this->directory}/preloads/protector.php";

        $this->assertSame('', (new PreloadInstaller("{$this->directory}/source.php", "{$this->directory}/preloads", true))->ensure());
        $this->assertFileDoesNotExist($target);

        $this->assertStringContainsString('copied', (new PreloadInstaller("{$this->directory}/source.php", "{$this->directory}/preloads", false))->ensure());
        $this->assertFileExists($target);

        file_put_contents($target, 'core copy');
        $this->assertSame('', (new PreloadInstaller("{$this->directory}/source.php", "{$this->directory}/preloads", false))->ensure());
        $this->assertSame('core copy', file_get_contents($target));

        (new PreloadInstaller("{$this->directory}/source.php", "{$this->directory}/preloads", false))->remove();
        $this->assertFileDoesNotExist($target);
        unlink("{$this->directory}/source.php");
    }

    public function testPreloadFailureIsReported(): void
    {
        file_put_contents("{$this->directory}/source.php", '<?php');

        $message = (new PreloadInstaller("{$this->directory}/source.php", "{$this->directory}/missing", false))->ensure();

        $this->assertStringContainsString('not protected', $message);
        unlink("{$this->directory}/source.php");
    }

    protected function paths(): DataPaths
    {
        return new DataPaths($this->current, 'abc123', $this->legacy);
    }

    private function dataDirectory(): DataDirectory
    {
        return new DataDirectory($this->paths());
    }
}
