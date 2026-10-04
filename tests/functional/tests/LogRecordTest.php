<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Tests\Functional\Scenario;

use ImpressCMS\Module\Protector\Tests\Functional\Scenario;
use ImpressCMS\Module\Protector\Tests\Functional\SiteTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('log')]
final class LogRecordTest extends SiteTestCase
{
    #[Scenario('LOG-01', 'default preferences', 'a guest from 127.0.0.2 with a known User-Agent triggers the isolated-comment check', 'one record is written with type ISOCOM, user 0, the address, the User-Agent, a description naming the value, and a time within the last minute')]
    public function testRecordContentForAGuest(): void
    {
        $this->probe($this->client(self::ATTACKER, 'RecordTest/9.9'), ['cid' => ',password /*']);

        $rows = $this->logRows();
        $this->assertCount(1, $rows);
        $this->assertSame('ISOCOM', $rows[0]['type']);
        $this->assertSame('0', (string) $rows[0]['uid']);
        $this->assertSame(self::ATTACKER, $rows[0]['ip']);
        $this->assertSame('RecordTest/9.9', $rows[0]['agent']);
        $this->assertStringContainsString(',password /*', $rows[0]['description']);

        $prefix = self::config()->get('DB_PREFIX');
        $age = (int) self::config()->pdo(self::config()->get('DB_NAME'))
            ->query("SELECT TIMESTAMPDIFF(SECOND, `timestamp`, NOW()) FROM `{$prefix}_protector_log`")->fetchColumn();
        $this->assertLessThan(60, abs($age));
    }

    #[Scenario('LOG-02', 'default preferences', 'an administrator triggers the isolated-comment check', 'the record carries the administrator\'s user id')]
    public function testRecordCarriesTheUserId(): void
    {
        $admin = $this->admin(self::ATTACKER);

        $admin->client()->get('/probe.php', ['cid' => ',password /*']);

        $this->assertSame('1', (string) $this->logRows()[0]['uid']);
    }

    #[Scenario('LOG-03', 'log level 15 (kinds 1, 2, 4 and 8 only)', 'a guest triggers the isolated-comment check (kind 32)', 'nothing is logged')]
    public function testLogLevelSuppressesOtherKinds(): void
    {
        $this->configure(['log_level' => 15]);

        $this->probe($this->client(), ['cid' => ',password /*']);

        $this->assertSame([], $this->logRows());
    }

    #[Scenario('LOG-04', 'log level 63 (kinds up to 32)', 'a guest triggers the isolated-comment check (kind 32)', 'it is logged')]
    public function testLogLevelIncludingTheKindLogsIt(): void
    {
        $this->configure(['log_level' => 63]);

        $this->probe($this->client(), ['cid' => ',password /*']);

        $this->assertSame(['ISOCOM'], $this->logTypes());
    }

    #[Scenario('LOG-05', 'default preferences', 'the same address triggers the isolated-comment check in two consecutive requests', 'only one record is written (an event identical to the newest record in address and type is not repeated)')]
    public function testConsecutiveIdenticalEventsAreLoggedOnce(): void
    {
        $client = $this->client();

        $this->probe($client, ['cid' => ',password /*']);
        $this->probe($client, ['cid' => ',password /*']);

        $this->assertSame(['ISOCOM'], $this->logTypes());
    }

    #[Scenario('LOG-06', 'default preferences', 'two different addresses trigger the isolated-comment check one after the other', 'both are logged')]
    public function testSameEventFromDifferentAddressesIsLoggedForEach(): void
    {
        $this->probe($this->client('127.0.0.2'), ['cid' => ',password /*']);
        $this->probe($this->client('127.0.0.3'), ['cid' => ',password /*']);

        $this->assertSame(['127.0.0.2', '127.0.0.3'], array_column($this->logRows(), 'ip'));
    }
}
