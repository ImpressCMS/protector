<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Tests\Unit;

use ImpressCMS\Module\Protector\Config\ProtectorConfig;
use ImpressCMS\Module\Protector\Dos\DosAction;
use ImpressCMS\Module\Protector\Policy\ViolationPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PolicyAndConfigTest extends TestCase
{
    /** @return array<string, array{int, bool, bool, bool, bool}> */
    public static function policies(): array
    {
        return [
            'nothing' => [0, false, false, false, false],
            'sanitise' => [1, true, false, false, false],
            'exit' => [2, false, true, false, false],
            'temporary ban' => [4, false, false, true, false],
            'permanent ban' => [8, false, false, false, true],
            'everything' => [15, true, true, true, true],
            'sanitise and exit' => [3, true, true, false, false],
        ];
    }

    #[DataProvider('policies')]
    public function testPolicyDecodesTheStoredBitmask(int $flags, bool $sanitises, bool $exits, bool $temporary, bool $permanent): void
    {
        $policy = new ViolationPolicy($flags);

        $this->assertSame($sanitises, $policy->sanitizes());
        $this->assertSame($exits, $policy->exits());
        $this->assertSame($temporary, $policy->bansTemporarily());
        $this->assertSame($permanent, $policy->bansPermanently());
    }

    public function testDosActionReadsStoredValuesAndDefaultsToExit(): void
    {
        $this->assertSame(DosAction::BanTemporarily, DosAction::fromStored('biptime0'));
        $this->assertSame(DosAction::DenyByHtaccess, DosAction::fromStored('hta'));
        $this->assertSame(DosAction::Exit, DosAction::fromStored('something else'));
        $this->assertSame(DosAction::Exit, DosAction::fromStored(''));
    }

    public function testConfigAccessorsFollowPhpTruthiness(): void
    {
        $config = new ProtectorConfig(['a' => '0', 'b' => '3', 'c' => 'text', 'd' => '']);

        $this->assertFalse($config->enabled('a'));
        $this->assertTrue($config->enabled('b'));
        $this->assertFalse($config->enabled('missing'));
        $this->assertSame(3, $config->int('b'));
        $this->assertSame(0, $config->int('missing'));
        $this->assertSame('text', $config->string('c'));
        $this->assertSame('', $config->string('missing'));
    }

    public function testGlobalDisabledAndEmptiness(): void
    {
        $this->assertTrue((new ProtectorConfig())->isEmpty());
        $this->assertFalse((new ProtectorConfig(['x' => '1']))->isEmpty());
        $this->assertTrue((new ProtectorConfig(['global_disabled' => '1']))->isGloballyDisabled());
        $this->assertFalse((new ProtectorConfig(['global_disabled' => '0']))->isGloballyDisabled());
    }

    public function testStoredListsAreReadFromSerializedValues(): void
    {
        $config = new ProtectorConfig([
            'plain' => serialize([1, 3]),
            'escaped' => addslashes(serialize(['a b'])),
            'broken' => 'not serialized',
        ]);

        $this->assertSame([1, 3], $config->storedList('plain'));
        $this->assertSame(['a b'], $config->storedList('escaped'));
        $this->assertSame([], $config->storedList('broken'));
        $this->assertSame([], $config->storedList('missing'));
    }

    public function testReliableIpsAreRegularExpressions(): void
    {
        $config = new ProtectorConfig(['reliable_ips' => serialize(['^192\.168\.', '^127\.0\.0\.1$', ''])]);

        $this->assertTrue($config->isReliableIp('192.168.1.1'));
        $this->assertTrue($config->isReliableIp('127.0.0.1'));
        $this->assertFalse($config->isReliableIp('127.0.0.2'));
        $this->assertFalse((new ProtectorConfig())->isReliableIp('127.0.0.1'));
    }
}
