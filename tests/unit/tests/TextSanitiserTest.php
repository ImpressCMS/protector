<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Tests\Unit;

use ImpressCMS\Module\Protector\Http\ServerRequest;
use ImpressCMS\Module\Protector\Http\TextSanitiser;
use PHPUnit\Framework\TestCase;

final class TextSanitiserTest extends TestCase
{
    public function testTagsAreStrippedAndQuotesAndBackslashesEncoded(): void
    {
        $this->assertSame('alert(&#39;x&#39;)&#34;&#92;', TextSanitiser::sanitise("<script>alert('x')</script>\"\\"));
    }

    public function testNonStringsBecomeEmpty(): void
    {
        $this->assertSame('', TextSanitiser::sanitise(null));
        $this->assertSame('', TextSanitiser::sanitise(['a']));
    }

    public function testRequestValuesAreSanitisedAndMissingOnesAreEmpty(): void
    {
        $saved = $_SERVER;
        $_SERVER['REQUEST_URI'] = "/a.php?x='1'";
        unset($_SERVER['HTTP_USER_AGENT']);

        $this->assertSame('/a.php?x=&#39;1&#39;', ServerRequest::uri());
        $this->assertSame('', ServerRequest::userAgent());

        $_SERVER = $saved;
    }

    public function testClientIpIsValidatedWithoutFilterInput(): void
    {
        $saved = $_SERVER;
        $_SERVER['REMOTE_ADDR'] = '203.0.113.9';
        $this->assertSame('203.0.113.9', ServerRequest::clientIp());

        $_SERVER['REMOTE_ADDR'] = 'not-an-ip';
        $this->assertSame('', ServerRequest::clientIp());

        unset($_SERVER['REMOTE_ADDR']);
        $this->assertSame('', ServerRequest::clientIp());

        $_SERVER = $saved;
    }
}
