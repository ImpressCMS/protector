<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Tests\Unit;

use ImpressCMS\Module\Protector\Ban\IpComparator;
use ImpressCMS\Module\Protector\Filter\FilterHandler;

final class FilterHandlerTest extends UnitTestCase
{
    protected function tearDown(): void
    {
        foreach (['filters_byconfig/precommon_zz_/', 'filters_byconfig/', 'filters_enabled/'] as $folder) {
            foreach (glob("{$this->directory}/{$folder}*") ?: [] as $file) {
                is_file($file) && unlink($file);
            }

            is_dir("{$this->directory}/{$folder}") && rmdir("{$this->directory}/{$folder}");
        }

        parent::tearDown();
    }

    public function testFilterListedInThePreferenceIsExecuted(): void
    {
        $this->createFilter('filters_byconfig/precommon_zz_listed.php', 'function protector_precommon_zz_listed() { return 1; }');

        $this->assertSame(1, $this->handler('precommon_zz_listed')->execute('precommon_zz'));
    }

    public function testFilterInTheEnabledFolderIsExecutedWithoutBeingListed(): void
    {
        $this->createFilter('filters_enabled/precommon_zz_enabled.php', 'function protector_precommon_zz_enabled() { return 1; }');

        $this->assertSame(1, $this->handler('')->execute('precommon_zz'));
    }

    public function testDyingMessageIsUsedOnlyWhenNoFilterHandledTheEvent(): void
    {
        $this->createFilter('filters_enabled/precommon_zz_handled.php', 'function protector_precommon_zz_handled() { return 1; }');
        $handler = $this->handler('');

        $this->assertSame(1, $handler->run('precommon_zz', 'stop'));
        $this->expectExceptionMessage('halted: stop');
        $handler->run('precommon_none', 'stop');
    }

    public function testPreferenceCannotPointOutsideTheFilterFolder(): void
    {
        mkdir("{$this->directory}/filters_byconfig/precommon_zz_", 0777, true);
        file_put_contents("{$this->directory}/outside.php", '<?php $GLOBALS["outside_ran"] = true;');
        unset($GLOBALS['outside_ran']);

        $result = $this->handler('precommon_zz_/../../outside')->execute('precommon_zz');

        $this->assertSame(0, $result);
        $this->assertArrayNotHasKey('outside_ran', $GLOBALS);
        unlink("{$this->directory}/outside.php");
    }

    public function testIpsAreSortedNumerically(): void
    {
        $addresses = ['192.168.0.1', '10.0.0.2', '10.0.0.10', '2.0.0.1', '10.0.0.2'];

        usort($addresses, IpComparator::compare(...));

        $this->assertSame(['2.0.0.1', '10.0.0.2', '10.0.0.2', '10.0.0.10', '192.168.0.1'], $addresses);
    }

    private function handler(string $filters): FilterHandler
    {
        @mkdir("{$this->directory}/filters_byconfig", 0777, true);
        @mkdir("{$this->directory}/filters_enabled", 0777, true);

        return new FilterHandler($this->configStore(['filters' => $filters, 'log_level' => '0']), $this->throwingResponder(), $this->directory);
    }

    private function createFilter(string $relativePath, string $code): void
    {
        @mkdir(dirname("{$this->directory}/{$relativePath}"), 0777, true);
        file_put_contents("{$this->directory}/{$relativePath}", "<?php {$code}");
    }
}
