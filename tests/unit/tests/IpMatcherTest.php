<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Tests\Unit;

use ImpressCMS\Module\Protector\Ban\IpMatcher;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class IpMatcherTest extends TestCase
{
    /** @return array<string, array{array<string, int>, string, ?string}> */
    public static function matchingCases(): array
    {
        return [
            'full address' => [['10.0.0.5' => 1], '10.0.0.5', '10.0.0.5'],
            'different address' => [['10.0.0.5' => 1], '10.0.0.6', null],
            'forward match' => [['192.168.' => 1], '192.168.4.9', '192.168.'],
            'forward match miss' => [['192.168.' => 1], '192.169.4.9', null],
            'regular expression' => [['/^10\.0\./' => 1], '10.0.7.7', '/^10\.0\./'],
            'regular expression miss' => [['/^10\.0\./' => 1], '10.1.7.7', null],
            'later entry matches' => [['1.1.1.1' => 1, '2.2.2.' => 2], '2.2.2.2', '2.2.2.'],
            'empty client address' => [['10.0.0.5' => 1], '', null],
        ];
    }

    /** @param array<string, int> $entries */
    #[DataProvider('matchingCases')]
    public function testFindsTheFirstMatchingEntry(array $entries, string $ip, ?string $expectedPattern): void
    {
        $match = (new IpMatcher())->find($entries, $ip);

        $this->assertSame($expectedPattern, $match?->pattern);
    }

    public function testReturnsTheInformationStoredWithTheEntry(): void
    {
        $match = (new IpMatcher())->find(['10.0.0.5' => 1893456000], '10.0.0.5');

        $this->assertSame(1893456000, $match?->info);
    }

    public function testIgnoresEmptyAndZeroPatterns(): void
    {
        $this->assertNull((new IpMatcher())->find(['' => 1, '0' => 2], '0'));
    }

    public function testNumericKeysAreTreatedAsAddresses(): void
    {
        $this->assertNotNull((new IpMatcher())->find([5 => 'x'], '5'));
    }
}
