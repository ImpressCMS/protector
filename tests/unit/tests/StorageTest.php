<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Tests\Unit;

use ImpressCMS\Module\Protector\Ban\BanList;
use ImpressCMS\Module\Protector\Ban\GroupOneIpList;
use ImpressCMS\Module\Protector\Dos\BandwidthLimiter;
use ImpressCMS\Module\Protector\Storage\DataPaths;

final class StorageTest extends UnitTestCase
{
    public function testDataPathsAreDerivedFromDirectoryAndSiteSuffix(): void
    {
        $paths = new DataPaths('/data', 'abc123');

        $this->assertSame('/data/badips' . 'abc123', $paths->badIps());
        $this->assertSame('/data/group1ips' . 'abc123', $paths->groupOneIps());
        $this->assertSame('/data/bwlimit' . 'abc123', $paths->bandwidthLimit());
        $this->assertSame('/data/configcache' . 'abc123', $paths->configCache());
    }

    public function testBanListIsEmptyWithoutAFile(): void
    {
        $this->assertSame([], (new BanList($this->paths()))->entries());
    }

    public function testRegisteredAddressIsBannedUntilTheGivenTime(): void
    {
        $bans = new BanList($this->paths());
        $until = time() + 600;

        $this->assertTrue($bans->register('10.0.0.9', $until));

        $this->assertSame(['10.0.0.9' => $until], $bans->entries());
        $this->assertSame(['10.0.0.9'], $bans->addresses());
    }

    public function testAddressWithoutTimeIsBannedForever(): void
    {
        $bans = new BanList($this->paths());

        $bans->register('10.0.0.9');

        $this->assertSame(0x7fffffff, $bans->entries()['10.0.0.9']);
    }

    public function testExpiredEntriesAreDropped(): void
    {
        $bans = new BanList($this->paths());
        $bans->write(['10.0.0.1' => time() - 10, '10.0.0.2' => time() + 100]);

        $this->assertSame(['10.0.0.2'], $bans->addresses());
    }

    public function testEntriesAreStoredSortedByExpiry(): void
    {
        $bans = new BanList($this->paths());
        $soon = time() + 10;
        $later = time() + 1000;

        $bans->write(['10.0.0.1' => $later, '10.0.0.2' => $soon]);

        $this->assertSame(['10.0.0.2', '10.0.0.1'], $bans->addresses());
    }

    public function testListFormatOfOlderVersionsIsIgnored(): void
    {
        file_put_contents($this->paths()->badIps(), serialize(['10.0.0.1', '10.0.0.2']) . "\n");

        $this->assertSame([], (new BanList($this->paths()))->entries());
    }

    public function testRegisteringAnEmptyAddressIsRefused(): void
    {
        $this->assertFalse((new BanList($this->paths()))->register(''));
    }

    public function testGroupOneListRoundTripsAndFlipsForMatching(): void
    {
        $list = new GroupOneIpList($this->paths());

        $this->assertSame([], $list->entries());

        $list->write(['10.0.0.1', '10.0.0.2']);

        $this->assertSame(['10.0.0.1', '10.0.0.2'], $list->entries());
        $this->assertSame(['10.0.0.1' => 0, '10.0.0.2' => 1], (new GroupOneIpList($this->paths()))->entriesWithInfo());
    }

    public function testBandwidthLimitIsCappedToFiveMinutes(): void
    {
        $limiter = new BandwidthLimiter($this->paths());

        $this->assertFalse($limiter->isLimited());

        $limiter->limitUntil(time() + 86400);

        $this->assertTrue($limiter->isLimited());
        $this->assertLessThanOrEqual(time() + 300, $limiter->expiresAt());
    }

    public function testBandwidthLimitInThePastIsNotLimiting(): void
    {
        $limiter = new BandwidthLimiter($this->paths());

        $limiter->limitUntil(time() - 5);

        $this->assertFalse($limiter->isLimited());
    }
}
