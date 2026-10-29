<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Tests\Unit;

use ImpressCMS\Module\Protector\Filter\FilterHandler;
use ImpressCMS\Module\Protector\Request\SpamGuard;

final class SpamGuardTest extends UnitTestCase
{
    public function testExternalLinksCountAsPoints(): void
    {
        $this->assertSame(2, $this->guard()->score('see http://spam.example/a and http://spam.example/b'));
    }

    public function testLinksToTheOwnSiteAreFree(): void
    {
        $this->assertSame(0, $this->guard()->score('read http://example.test/page'));
    }

    public function testBbCodeLinksWithoutAProtocolCount(): void
    {
        $this->assertSame(1, $this->guard()->score('[url=www.spam.example]x[/url]'));
        $this->assertSame(0, $this->guard()->score('[url=http://example.test/x]x[/url]'));
    }

    public function testNestedPostValuesAreAdded(): void
    {
        $post = ['a' => 'http://spam.example/x', 'b' => ['c' => 'http://spam.example/y', 'd' => 'clean']];

        $this->assertSame(2, $this->guard()->score($post));
    }

    private function guard(): SpamGuard
    {
        $config = $this->configStore();
        $responder = $this->throwingResponder();

        return new SpamGuard($this->auditLog($config), new FilterHandler($config, $responder, $this->directory), $responder);
    }
}
