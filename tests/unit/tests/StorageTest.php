<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Tests\Unit;

use ImpressCMS\Module\Protector\Ban\BanList;
use ImpressCMS\Module\Protector\Ban\GroupOneIpList;
use ImpressCMS\Module\Protector\Dos\BandwidthLimiter;
use ImpressCMS\Module\Protector\Storage\AtomicFile;
use ImpressCMS\Module\Protector\Storage\DataPaths;
use ImpressCMS\Module\Protector\Storage\StoredArray;

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

    public function testAtomicWriteReplacesTheFileAndLeavesNoTemporaryFile(): void
    {
        $path = $this->directory . '/state';

        $this->assertTrue(AtomicFile::write($path, 'first'));
        $this->assertTrue(AtomicFile::write($path, 'second'));

        $this->assertSame('second', file_get_contents($path));
        $this->assertSame([$path], glob($this->directory . '/state*'));
    }

    public function testAtomicWriteCreatesMissingDirectories(): void
    {
        $path = $this->directory . '/nested/deeper/state';

        $this->assertTrue(AtomicFile::write($path, 'x'));
        $this->assertSame('x', file_get_contents($path));

        unlink($path);
        rmdir($this->directory . '/nested/deeper');
        rmdir($this->directory . '/nested');
    }

    public function testAtomicWriteFailsWhenTheDirectoryCannotBeCreated(): void
    {
        file_put_contents($this->directory . '/plainfile', 'x');

        $this->assertFalse(AtomicFile::write($this->directory . '/plainfile/state', 'x'));
    }

    public function testStoredArraysNeverInstantiateObjects(): void
    {
        $payload = serialize(['safe' => 1, 'object' => new \ArrayObject([1])]);

        $decoded = StoredArray::decode($payload);

        $this->assertSame(1, $decoded['safe']);
        $this->assertInstanceOf(\__PHP_Incomplete_Class::class, $decoded['object']);
    }

    public function testStoredArrayRejectsNonArraysAndGarbage(): void
    {
        $this->assertNull(StoredArray::decode(''));
        $this->assertNull(StoredArray::decode('garbage'));
        $this->assertNull(StoredArray::decode(serialize('text')));
        $this->assertSame([1, 2], StoredArray::decode(StoredArray::encode([1, 2]) . "\n"));
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
