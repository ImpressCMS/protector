<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Tests\Unit;

use ImpressCMS\Module\Protector\Config\ProtectorConfig;
use ImpressCMS\Module\Protector\Request\DirectoryTraversalGuard;
use ImpressCMS\Module\Protector\Request\DoubtfulValue;
use ImpressCMS\Module\Protector\Request\IdValueSanitiser;
use ImpressCMS\Module\Protector\Request\IsolatedCommentGuard;
use ImpressCMS\Module\Protector\Request\RequestMutator;
use ImpressCMS\Module\Protector\Request\RequestScanner;
use ImpressCMS\Module\Protector\Request\UnionGuard;
use ImpressCMS\Module\Protector\Request\UploadGuard;

final class RequestGuardsTest extends UnitTestCase
{
    public function testMutatorReplacesTheValueEverywhereItIsKnown(): void
    {
        $_GET = ['a' => ['b' => 'old']];
        $_REQUEST = ['a' => ['b' => 'old']];

        (new RequestMutator())->replace('G', ['a', 'b'], 'new');

        $this->assertSame('new', $_GET['a']['b']);
        $this->assertSame('new', $_REQUEST['a']['b']);
    }

    public function testMutatorLeavesRequestAloneWhenItDiffers(): void
    {
        $_POST = ['a' => 'old'];
        $_REQUEST = ['a' => 'other'];

        (new RequestMutator())->replace('P', ['a'], 'new');

        $this->assertSame('new', $_POST['a']);
        $this->assertSame('other', $_REQUEST['a']);
    }

    public function testScannerFindsNullBytesContaminationAndDoubtfulValues(): void
    {
        $_GET = ['plain' => 'abc', 'quoted' => "it's", 'nul' => "a\0b", 'xoopsConfig' => ['x' => '1']];
        $_POST = [];
        $_COOKIE = [];
        $_REQUEST = $_GET;
        $log = $this->auditLog();

        $scanner = new RequestScanner(new ProtectorConfig(['san_nullbyte' => '1']), $log, new RequestMutator());
        $scanner->scan();

        $this->assertSame('a b', $_GET['nul']);
        $this->assertTrue($scanner->isContaminated());
        $this->assertSame('CONTAMI', $log->lastType());
        $this->assertStringContainsString("Attempt to inject 'xoopsConfig' was found.", $log->message());
        $this->assertStringContainsString("Injecting Null-byte 'a b' found.", $log->message());

        $found = array_map(static fn (DoubtfulValue $value): string => $value->value, $scanner->doubtfulValues());
        $this->assertContains("it's", $found);
        $this->assertContains('a b', $found);
        $this->assertNotContains('abc', $found);
    }

    public function testScannerLeavesNullBytesAloneWhenTheCheckIsOff(): void
    {
        $_GET = ['nul' => "a\0b"];
        $_POST = $_COOKIE = [];

        (new RequestScanner(new ProtectorConfig(), $this->auditLog(), new RequestMutator()))->scan();

        $this->assertSame("a\0b", $_GET['nul']);
    }

    public function testIsolatedCommentIsDetectedAndClosedWhenSanitising(): void
    {
        $_GET = ['q' => 'x /* y'];
        $_REQUEST = $_GET;
        $doubtful = [new DoubtfulValue('G', ['q'], 'x /* y')];
        $guard = new IsolatedCommentGuard($this->auditLog(), new RequestMutator());

        $this->assertFalse($guard->isSafe($doubtful, true));
        $this->assertSame('x /* y*/', $_GET['q']);
    }

    public function testClosedCommentIsSafe(): void
    {
        $guard = new IsolatedCommentGuard($this->auditLog(), new RequestMutator());

        $this->assertTrue($guard->isSafe([new DoubtfulValue('G', ['q'], 'x /* y */ z')], true));
    }

    public function testIsolatedCommentIsNotSanitisedWhenNotRequested(): void
    {
        $_GET = ['q' => 'x /* y'];
        $_REQUEST = $_GET;
        $guard = new IsolatedCommentGuard($this->auditLog(), new RequestMutator());

        $this->assertFalse($guard->isSafe([new DoubtfulValue('G', ['q'], 'x /* y')], false));
        $this->assertSame('x /* y', $_GET['q']);
    }

    public function testUnionIsDetectedAndNeutralised(): void
    {
        $_GET = ['q' => '1 UNION SELECT 2'];
        $_REQUEST = $_GET;
        $doubtful = [new DoubtfulValue('G', ['q'], '1 UNION SELECT 2')];
        $log = $this->auditLog();

        $this->assertFalse((new UnionGuard($log, new RequestMutator()))->isSafe($doubtful, true));
        $this->assertSame('1 uni-on SELECT 2', $_GET['q']);
        $this->assertSame('UNION', $log->lastType());
    }

    public function testUnionHiddenBehindCommentsIsStillDetected(): void
    {
        $guard = new UnionGuard($this->auditLog(), new RequestMutator());

        $this->assertFalse($guard->isSafe([new DoubtfulValue('G', ['q'], '1 UNION/**/ SELECT 2')], false));
    }

    public function testHarmlessTextIsNotAUnion(): void
    {
        $guard = new UnionGuard($this->auditLog(), new RequestMutator());

        $this->assertTrue($guard->isSafe([new DoubtfulValue('G', ['q'], 'the trade union')], false));
    }

    public function testIdValuesAreReducedToSafeCharacters(): void
    {
        $_GET = ['topic_id' => '12 OR 1=1', 'name' => 'a b', 'list' => ['id' => 'x']];
        $_POST = ['id' => "5'"];
        $_COOKIE = [];
        $_REQUEST = ['topic_id' => '12 OR 1=1'];

        (new IdValueSanitiser())->apply();

        $this->assertSame('12OR11', $_GET['topic_id']);
        $this->assertSame('a b', $_GET['name']);
        $this->assertSame(['id' => 'x'], $_GET['list']);
        $this->assertSame('5', $_POST['id']);
    }

    public function testRequestReceivesTheSanitisedIdValue(): void
    {
        $_GET = ['topic_id' => '12 OR 1=1'];
        $_POST = $_COOKIE = [];
        $_REQUEST = ['topic_id' => '12 OR 1=1'];

        (new IdValueSanitiser())->apply();

        $this->assertSame('12OR11', $_GET['topic_id']);
        $this->assertSame('12OR11', $_REQUEST['topic_id']);
    }

    public function testRequestIsNotTouchedWhenItHoldsAnotherValueOrNone(): void
    {
        $_GET = ['topic_id' => '1 2'];
        $_POST = ['user_id' => '3 4'];
        $_COOKIE = [];
        $_REQUEST = ['topic_id' => 'other'];

        (new IdValueSanitiser())->apply();

        $this->assertSame('other', $_REQUEST['topic_id']);
        $this->assertArrayNotHasKey('user_id', $_REQUEST);
    }

    public function testRequestReceivesTheRewrittenTraversalValue(): void
    {
        $_GET = ['file' => '../../etc/passwd'];
        $_REQUEST = ['file' => '../../etc/passwd'];

        (new DirectoryTraversalGuard($this->auditLog()))->apply();

        $this->assertSame('../../etc/passwd .', $_REQUEST['file']);
    }

    public function testDirectoryTraversalIsRewrittenInTheQueryString(): void
    {
        $_GET = ['file' => '../../etc/passwd', 'ok' => 'readme.txt'];
        $_REQUEST = $_GET;

        (new DirectoryTraversalGuard($this->auditLog()))->apply();

        $this->assertSame('../../etc/passwd .', $_GET['file']);
        $this->assertSame('readme.txt', $_GET['ok']);
    }

    public function testUploadWithForbiddenExtensionIsRefused(): void
    {
        $files = ['f' => ['name' => 'shell.php', 'tmp_name' => '/nonexistent', 'error' => 0]];

        $this->assertFalse((new UploadGuard($this->auditLog()))->isSafe($files));
    }

    public function testUploadWithTwoDotsIsRefused(): void
    {
        $files = ['f' => ['name' => 'a.php.txt', 'tmp_name' => '/nonexistent', 'error' => 0]];

        $this->assertFalse((new UploadGuard($this->auditLog()))->isSafe($files));
    }

    public function testCamouflagedImageIsRefusedAndGenuineImageAccepted(): void
    {
        $png = $this->directory . '/real.png';
        file_put_contents($png, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));
        $fake = $this->directory . '/fake.png';
        file_put_contents($fake, 'not an image');
        $guard = new UploadGuard($this->auditLog());

        $this->assertTrue($guard->isSafe(['f' => ['name' => 'real.png', 'tmp_name' => $png, 'error' => 0]]));
        $this->assertFalse($guard->isSafe(['f' => ['name' => 'fake.png', 'tmp_name' => $fake, 'error' => 0]]));
        $this->assertFalse($guard->isSafe(['f' => ['name' => 'real.gif', 'tmp_name' => $png, 'error' => 0]]));
    }

    public function testFailedUploadsAreSkipped(): void
    {
        $files = ['f' => ['name' => 'shell.php', 'tmp_name' => '', 'error' => UPLOAD_ERR_NO_FILE]];

        $this->assertTrue((new UploadGuard($this->auditLog()))->isSafe($files));
    }
}
