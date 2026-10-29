<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AutoloadTest extends TestCase
{
    private const MODULE = __DIR__ . '/../../../root/modules/protector';

    public function testTheNamespaceInComposerJsonIsTheOneTheAutoloaderResolves(): void
    {
        $composer = json_decode((string) file_get_contents(self::MODULE . '/composer.json'), true);
        $psr4 = $composer['autoload']['psr-4'];

        self::assertSame(['ImpressCMS\\Module\\Protector\\' => 'src/'], $psr4);

        foreach ($psr4 as $prefix => $directory) {
            self::assertTrue(class_exists($prefix . 'Http\\ServerRequest'), "{$prefix} is not resolved from {$directory}");
        }
    }

    public function testLegacyGlobalNamesStillResolve(): void
    {
        self::assertTrue(class_exists('ProtectorFilterAbstract'));
        self::assertTrue(class_exists('ProtectorFilterHandler'));
        self::assertTrue(class_exists('Protector'));
    }
}
