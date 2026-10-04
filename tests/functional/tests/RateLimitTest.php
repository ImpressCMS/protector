<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Tests\Functional\Scenario;

use ImpressCMS\Module\Protector\Tests\Functional\Http\Client;
use ImpressCMS\Module\Protector\Tests\Functional\Scenario;
use ImpressCMS\Module\Protector\Tests\Functional\SiteTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('rate-limits')]
final class RateLimitTest extends SiteTestCase
{
    private const BAD_IP_MESSAGE = 'You are registered as BAD_IP by Protector.';

    #[Scenario('DOS-01', 'F5 limit of 3 requests, action "none"', 'one address requests the same page 6 times', 'every request is served; one DoS record is logged')]
    public function testSameUriFloodWithActionNoneOnlyLogs(): void
    {
        $this->configure(['dos_f5count' => 3, 'dos_f5action' => 'none']);

        $served = $this->repeatSamePage($this->client(), 6);

        $this->assertSame(6, $served);
        $this->assertSame(['DoS'], $this->logTypes());
    }

    #[Scenario('DOS-02', 'F5 limit of 3 requests, action "exit"', 'one address requests the same page 6 times', 'the first requests are served and the later ones end with an empty page; a DoS record is logged')]
    public function testSameUriFloodWithActionExitStopsServing(): void
    {
        $this->configure(['dos_f5count' => 3, 'dos_f5action' => 'exit']);
        $client = $this->client();

        $served = $this->repeatSamePage($client, 6);
        $last = $client->get('/probe.php', ['flood' => 1]);

        $this->assertLessThan(6, $served);
        $this->assertGreaterThanOrEqual(3, $served);
        $this->assertSame('', $last->body);
        $this->assertContains('DoS', $this->logTypes());
    }

    #[Scenario('DOS-03', 'F5 limit of 3 requests, action "sleep"', 'one address requests the same page 6 times', 'every request is served but the over-limit ones are delayed by about 5 seconds each')]
    #[Group('slow')]
    public function testSameUriFloodWithActionSleepDelaysTheRequests(): void
    {
        $this->configure(['dos_f5count' => 3, 'dos_f5action' => 'sleep']);
        $client = $this->client();

        $started = microtime(true);
        $served = $this->repeatSamePage($client, 6);
        $elapsed = microtime(true) - $started;

        $this->assertSame(6, $served);
        $this->assertGreaterThan(8.0, $elapsed);
    }

    #[Scenario('DOS-04', 'F5 limit of 3 requests, action "temporary ban"', 'one address floods the same page and then requests another page', 'the address is then shown the BAD_IP message with an expiry time')]
    public function testSameUriFloodWithTemporaryBanBansTheAddress(): void
    {
        $this->configure(['dos_f5count' => 3, 'dos_f5action' => 'biptime0']);
        $client = $this->client();

        $this->repeatSamePage($client, 6);
        $after = $client->get('/probe.php', ['after' => 1]);

        $this->assertStringContainsString(self::BAD_IP_MESSAGE, $after->body);
        $this->assertStringContainsString('expired on', $after->body);
    }

    #[Scenario('DOS-05', 'F5 limit of 3 requests, action "permanent ban"', 'one address floods the same page and then requests another page', 'the address is shown the BAD_IP message with the maximum expiry date (year 2038)')]
    public function testSameUriFloodWithPermanentBanBansTheAddressForever(): void
    {
        $this->configure(['dos_f5count' => 3, 'dos_f5action' => 'bip']);
        $client = $this->client();

        $this->repeatSamePage($client, 6);
        $after = $client->get('/probe.php', ['after' => 1]);

        $this->assertStringContainsString(self::BAD_IP_MESSAGE, $after->body);
        $this->assertStringContainsString('2038-', $after->body);
    }

    #[Scenario('DOS-06', 'F5 limit of 3 requests, action ".htaccess deny"', 'one address floods the same page', 'a PROTECTOR block denying that address is written to the site\'s .htaccess, and the original is backed up (the harness removes both afterwards)')]
    public function testSameUriFloodWithHtaccessActionWritesADenyRule(): void
    {
        $this->configure(['dos_f5count' => 3, 'dos_f5action' => 'hta']);

        $this->repeatSamePage($this->client(), 6);

        $this->assertFileExists(self::layout()->siteHtaccess());
        $content = (string) file_get_contents(self::layout()->siteHtaccess());
        $this->assertStringContainsString('#PROTECTOR#', $content);
        $this->assertStringContainsString('DENY FROM 127.0.0.2', $content);
    }

    #[Scenario('DOS-07', 'crawler limit of 3 requests', 'one address requests 6 different pages', 'the first requests are served, the later ones end with an empty page; a CRAWLER record is logged')]
    public function testManyDifferentPagesAreTreatedAsACrawler(): void
    {
        $this->configure(['dos_crcount' => 3]);
        $client = $this->client();

        $served = 0;

        for ($page = 1; $page <= 6; $page++) {
            $this->isServed($client->get('/probe.php', ['page' => $page])) && $served++;
        }

        $this->assertLessThan(6, $served);
        $this->assertContains('CRAWLER', $this->logTypes());
    }

    #[Scenario('DOS-08', 'crawler limit of 3 requests, the visitor\'s User-Agent matches the "welcomed crawlers" pattern (Googlebot)', 'it requests 6 different pages and the same page 6 times', 'every request is served, nothing is logged and no access record is kept for it')]
    public function testWelcomedCrawlersAreNeverCounted(): void
    {
        $this->configure(['dos_crcount' => 3, 'dos_f5count' => 3]);
        $bot = $this->client(self::ATTACKER, 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)');

        $served = 0;

        for ($page = 1; $page <= 6; $page++) {
            $this->isServed($bot->get('/probe.php', ['page' => $page])) && $served++;
        }

        $served += $this->repeatSamePage($bot, 6);

        $this->assertSame(12, $served);
        $this->assertSame([], $this->logRows());
        $this->assertSame(0, $this->accessCount(self::ATTACKER));
    }

    #[Scenario('DOS-09', 'the site\'s directory name is listed in "modules skipped by the DoS check"', 'one address requests the same page 6 times', 'every request is served and nothing is recorded')]
    public function testSkippedLocationsAreNotLimited(): void
    {
        $this->configure(['dos_f5count' => 3, 'dos_skipmodules' => basename(self::layout()->sitePath())]);

        $served = $this->repeatSamePage($this->client(), 6);

        $this->assertSame(6, $served);
        $this->assertSame([], $this->logRows());
    }

    #[Scenario('DOS-10', 'the visitor\'s address matches "reliable IPs"', 'it requests the same page 6 times', 'every request is served and nothing is logged')]
    public function testReliableAddressesAreNotLimited(): void
    {
        $this->configure(['dos_f5count' => 3, 'reliable_ips' => ['^127\.0\.0\.2$']]);

        $served = $this->repeatSamePage($this->client('127.0.0.2'), 6);

        $this->assertSame(6, $served);
        $this->assertSame([], $this->logRows());
    }

    #[Scenario('DOS-11', 'Protector switched off globally', 'one address requests the same page 6 times', 'every request is served and nothing is logged')]
    public function testGloballyDisabledProtectorDoesNotLimit(): void
    {
        $this->configure(['dos_f5count' => 3, 'global_disabled' => 1]);

        $served = $this->repeatSamePage($this->client(), 6);

        $this->assertSame(6, $served);
        $this->assertSame([], $this->logRows());
    }

    #[Scenario('DOS-12', 'a page defines PROTECTOR_SKIP_DOS_CHECK before the core boots', 'one address requests that page 6 times', 'every request is served and nothing is logged')]
    public function testPagesCanOptOutOfTheDosCheck(): void
    {
        $this->configure(['dos_f5count' => 3]);
        $client = $this->client();

        $served = 0;

        for ($i = 0; $i < 6; $i++) {
            $this->isServed($client->get('/skipdos.php', ['flood' => 1])) && $served++;
        }

        $this->assertSame(6, $served);
        $this->assertSame([], $this->logRows());
    }

    #[Scenario('BWL-01', 'bandwidth limit of 10 recorded accesses', 'one address makes 12 requests, then a different address requests a page', 'the other address is told the site is crowded (HTTP 503)')]
    public function testBandwidthLimitRejectsEveryoneOnceExceeded(): void
    {
        $this->configure(['bwlimit_count' => 10]);
        $heavy = $this->client('127.0.0.3');

        for ($i = 0; $i < 12; $i++) {
            $heavy->get('/probe.php', ['i' => $i]);
        }

        $other = $this->probe($this->client('127.0.0.4'));

        $this->assertSame(503, $other->status);
        $this->assertStringContainsString('This site is very crowed now. try later.', $other->body);
    }

    #[Scenario('BWL-02', 'bandwidth limit left at its default (0 = off)', 'one address makes 12 requests, then a different address requests a page', 'the other address is served')]
    public function testBandwidthLimitIsOffByDefault(): void
    {
        $heavy = $this->client('127.0.0.3');

        for ($i = 0; $i < 12; $i++) {
            $heavy->get('/probe.php', ['i' => $i]);
        }

        $this->assertTrue($this->isServed($this->probe($this->client('127.0.0.4'))));
    }

    #[Scenario('BRU-01', 'brute-force limit of 3', 'one address submits 6 failed logins, then requests a page', 'after the limit the address is banned, a BRUTE FORCE record is logged and the next page shows the BAD_IP message')]
    public function testRepeatedFailedLoginsBanTheAddress(): void
    {
        $this->configure(['bf_count' => 3]);
        $client = $this->client();

        for ($attempt = 0; $attempt < 6; $attempt++) {
            $client->post('/user.php', ['uname' => 'nobody', 'pass' => 'wrong-password', 'op' => 'login']);
        }

        $after = $client->get('/probe.php', ['after' => 1]);

        $this->assertContains('BRUTE FORCE', $this->logTypes());
        $this->assertStringContainsString(self::BAD_IP_MESSAGE, $after->body);
    }

    #[Scenario('BRU-02', 'brute-force limit of 3, the visitor\'s address matches "reliable IPs"', 'it submits 6 failed logins, then requests a page', 'it is never banned')]
    public function testReliableAddressesAreNotCountedForBruteForce(): void
    {
        $this->configure(['bf_count' => 3, 'reliable_ips' => ['^127\.0\.0\.2$']]);
        $client = $this->client('127.0.0.2');

        for ($attempt = 0; $attempt < 6; $attempt++) {
            $client->post('/user.php', ['uname' => 'nobody', 'pass' => 'wrong-password', 'op' => 'login']);
        }

        $this->assertTrue($this->isServed($this->probe($client)));
        $this->assertNotContains('BRUTE FORCE', $this->logTypes());
    }

    private function repeatSamePage(Client $client, int $times): int
    {
        $served = 0;

        for ($i = 0; $i < $times; $i++) {
            $this->isServed($client->get('/probe.php', ['flood' => 1])) && $served++;
        }

        return $served;
    }
}
