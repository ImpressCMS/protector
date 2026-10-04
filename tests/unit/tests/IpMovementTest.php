<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Tests\Unit;

use ImpressCMS\Module\Protector\Session\IpMovement;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class IpMovementTest extends TestCase
{
    /** @return array<string, array{string, string, int, bool}> */
    public static function movements(): array
    {
        return [
            'same ipv4 address' => ['192.168.1.10', '192.168.1.10', 24, false],
            'same /24 network' => ['192.168.1.10', '192.168.1.200', 24, false],
            'other /24 network' => ['192.168.1.10', '192.168.2.10', 24, true],
            'partial byte, same' => ['10.0.0.1', '10.0.0.100', 25, false],
            'partial byte, moved' => ['10.0.0.1', '10.0.0.200', 25, true],
            'full address required' => ['10.0.0.1', '10.0.0.2', 32, true],
            'check switched off with zero' => ['10.0.0.1', '99.0.0.1', 0, false],
            'check switched off above 32' => ['10.0.0.1', '99.0.0.1', 33, false],
            'ipv6 same /48' => ['2001:db8:1::1', '2001:db8:1:ffff::2', 24, false],
            'ipv6 other /48' => ['2001:db8:1::1', '2001:db8:2::1', 24, true],
            'ipv6 uses twice the bits' => ['2001:db8:1:1::1', '2001:db8:1:2::1', 32, true],
            'families differ' => ['10.0.0.1', '2001:db8::1', 24, false],
            'invalid previous address' => ['not an ip', '10.0.0.1', 24, false],
            'invalid current address' => ['10.0.0.1', '', 24, false],
        ];
    }

    #[DataProvider('movements')]
    public function testReportsWhetherTheClientLeftItsNetwork(string $previous, string $current, int $topBits, bool $expected): void
    {
        $this->assertSame($expected, (new IpMovement())->hasMoved($previous, $current, $topBits));
    }
}
