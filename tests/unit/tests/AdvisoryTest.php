<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Tests\Unit;

use ImpressCMS\Module\Protector\Advisory\DatabaseLayerCheck;
use ImpressCMS\Module\Protector\Advisory\DatabasePrefixCheck;
use ImpressCMS\Module\Protector\Advisory\DataDirectoryCheck;
use ImpressCMS\Module\Protector\Advisory\IniFlagCheck;
use ImpressCMS\Module\Protector\Advisory\PhpVersionCheck;
use ImpressCMS\Module\Protector\Advisory\PreloadCheck;
use ImpressCMS\Module\Protector\Advisory\StartupHooksCheck;
use ImpressCMS\Module\Protector\Advisory\TrustPathCheck;
use PHPUnit\Framework\Attributes\DataProvider;

final class AdvisoryTest extends UnitTestCase
{
    public function testIniFlagThatIsOffIsFine(): void
    {
        $result = (new IniFlagCheck('allow_url_fopen', false, 'Not secure', 'advice'))->run();

        $this->assertTrue($result->ok);
        $this->assertSame('off', $result->value);
        $this->assertSame('ok', $result->statusText);
    }

    public function testIniFlagThatIsOnIsReported(): void
    {
        $result = (new IniFlagCheck('allow_url_fopen', true, 'Not secure', 'advice'))->run();

        $this->assertFalse($result->ok);
        $this->assertSame('on', $result->value);
        $this->assertSame('Not secure', $result->statusText);
        $this->assertSame('advice', $result->advice);
    }

    public function testDefaultDatabasePrefixIsReportedInAnyCase(): void
    {
        $this->assertFalse((new DatabasePrefixCheck('XOOPS', 'Not secure', 'advice'))->run()->ok);
        $this->assertFalse((new DatabasePrefixCheck('xoops', 'Not secure', 'advice'))->run()->ok);
        $this->assertTrue((new DatabasePrefixCheck('x7k2', 'Not secure', 'advice'))->run()->ok);
    }

    /** @return array<string, array{bool, bool, bool, string}> */
    public static function startupStates(): array
    {
        return [
            'both ran' => [true, true, true, 'patched'],
            'no precheck' => [false, false, false, 'missing precheck'],
            'no postcheck' => [true, false, false, 'missing postcheck'],
        ];
    }

    #[DataProvider('startupStates')]
    public function testStartupHooksAreReported(bool $pre, bool $post, bool $ok, string $value): void
    {
        $result = (new StartupHooksCheck($pre, $post, 'Not secure', 'advice'))->run();

        $this->assertSame($ok, $result->ok);
        $this->assertSame($value, $result->value);
        $this->assertSame('mainfile.php', $result->subject);
    }

    public function testDatabaseLayerIsReadyForPdoOrForTheTrap(): void
    {
        $this->assertTrue((new DatabaseLayerCheck('Icms\Db\Legacy\PdoDatabase', 'pdo.mysql', 'ready', 'not ready'))->run()->ok);
        $this->assertTrue((new DatabaseLayerCheck('ProtectorMysqlDatabase', 'mysql', 'ready', 'not ready'))->run()->ok);

        $result = (new DatabaseLayerCheck('SomethingElse', 'mysql', 'ready', 'not ready'))->run();

        $this->assertFalse($result->ok);
        $this->assertSame('not ready', $result->statusText);
        $this->assertSame('databasefactory.php', $result->subject);
    }

    public function testDataDirectoryMustBeWritable(): void
    {
        $this->assertTrue((new DataDirectoryCheck($this->directory, '/trust', 'Not secure', 'advice'))->run()->ok);
        $this->assertFalse((new DataDirectoryCheck($this->directory . '/missing', '/trust', 'Not secure', 'advice'))->run()->ok);
    }

    public function testPreloadMustBePresent(): void
    {
        $this->assertFalse((new PreloadCheck($this->directory, 'Not secure', 'advice'))->run()->ok);

        file_put_contents($this->directory . '/protector.php', '<?php');

        $this->assertTrue((new PreloadCheck($this->directory, 'Not secure', 'advice'))->run()->ok);
    }

    public function testPhpVersionMustBeSupported(): void
    {
        $this->assertTrue((new PhpVersionCheck('8.2.0', 'Not secure', 'advice'))->run()->ok);
        $this->assertTrue((new PhpVersionCheck('8.4.1', 'Not secure', 'advice'))->run()->ok);
        $this->assertFalse((new PhpVersionCheck('8.1.30', 'Not secure', 'advice'))->run()->ok);
    }

    public function testTrustPathLinksAreRelativeToTheSiteAddress(): void
    {
        $check = new TrustPathCheck('/var/www/site', '/var/www/trust', 'http://example.test', 'advice', 'link');

        $result = $check->run();

        $this->assertSame('../trust', $check->relativeTrustPath());
        $this->assertSame('http://example.test/../trust/modules/protector/public_check.png', $result->imageUrl);
        $this->assertSame('http://example.test/../trust/modules/protector/public_check.php', $result->linkUrl);
        $this->assertSame('ICMS_TRUST_PATH', $result->subject);
    }

    public function testTrustPathInsideTheSiteRootNeedsNoParentSteps(): void
    {
        $check = new TrustPathCheck('/var/www/site', '/var/www/site/private', 'http://example.test', 'advice', 'link');

        $this->assertSame('private', $check->relativeTrustPath());
    }
}
