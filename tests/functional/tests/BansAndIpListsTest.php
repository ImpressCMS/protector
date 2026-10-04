<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Tests\Functional\Scenario;

use ImpressCMS\Module\Protector\Tests\Functional\KnownDefect;
use ImpressCMS\Module\Protector\Tests\Functional\Scenario;
use ImpressCMS\Module\Protector\Tests\Functional\SiteTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('bans')]
final class BansAndIpListsTest extends SiteTestCase
{
    private const BAD_IP_MESSAGE = 'You are registered as BAD_IP by Protector.';

    #[Scenario('BAN-01', 'the administrator lists 127.0.0.50 in the "bad IPs" form', 'that address and an unlisted one request a page', 'the listed address is shown the BAD_IP message with an expiry time; the unlisted one is served')]
    public function testListedAddressIsBlockedAndOthersAreServed(): void
    {
        $this->saveIpLists($this->admin(), "127.0.0.50\n");

        $blocked = $this->probe($this->client('127.0.0.50'));
        $served = $this->probe($this->client('127.0.0.53'));

        $this->assertStringContainsString(self::BAD_IP_MESSAGE, $blocked->body);
        $this->assertStringContainsString('expired on', $blocked->body);
        $this->assertTrue($this->isServed($served));
    }

    #[Scenario('BAN-02', 'the administrator lists 127.0.0.52 with an expiry time in the future', 'that address requests a page', 'it is blocked')]
    public function testFutureExpiryStillBlocks(): void
    {
        $this->saveIpLists($this->admin(), "127.0.0.52:4102444800\n");

        $this->assertStringContainsString(self::BAD_IP_MESSAGE, $this->probe($this->client('127.0.0.52'))->body);
    }

    #[Scenario('BAN-03', 'the administrator lists 127.0.0.51 with an expiry time in the past', 'that address requests a page', 'the entry has expired and the page is served')]
    public function testExpiredEntryIsIgnored(): void
    {
        $this->saveIpLists($this->admin(), "127.0.0.51:1000\n");

        $this->assertTrue($this->isServed($this->probe($this->client('127.0.0.51'))));
    }

    #[Scenario('BAN-04', 'the administrator submits valid and invalid lines (a word, an over-long string)', 'the admin start page is shown again', 'only the valid addresses are kept in the list')]
    public function testInvalidLinesAreDroppedFromTheList(): void
    {
        $admin = $this->admin();
        $this->saveIpLists($admin, "127.0.0.50\nabc\n10.0.0.1.2.3.4.5.6\n127.0.0.52:4102444800");

        $page = $admin->page('/modules/protector/admin/index.php');

        preg_match("#<textarea name='bad_ips'[^>]*>(.*?)</textarea>#s", $page->body, $matches);
        $this->assertSame("127.0.0.50\n127.0.0.52:4102444800\n", $matches[1]);
    }

    #[Scenario('BAN-05', 'an address is banned for 2 seconds by an isolated-comment attack', 'it requests a page immediately and again after 4 seconds', 'it is blocked first and served after the ban has expired')]
    public function testTemporaryBanExpires(): void
    {
        $this->configure(['isocom_action' => 7, 'banip_time0' => 2]);
        $attacker = $this->client();

        $attacker->get('/probe.php', ['cid' => ',password /*']);
        $during = $this->probe($attacker, ['n' => 1]);
        sleep(4);
        $after = $this->probe($attacker, ['n' => 2]);

        $this->assertStringContainsString(self::BAD_IP_MESSAGE, $during->body);
        $this->assertTrue($this->isServed($after));
    }

    #[Scenario('BAN-06', 'an address is both on the bad-IP list and matches "reliable IPs"', 'it requests a page', 'the ban wins (the bad-IP check runs before the reliable-IP exemption)')]
    public function testBanWinsOverReliableAddresses(): void
    {
        $this->saveIpLists($this->admin(), "127.0.0.50\n");
        $this->configure(['reliable_ips' => ['^127\.0\.0\.50$']]);

        $response = $this->probe($this->client('127.0.0.50'));

        $this->assertStringContainsString(self::BAD_IP_MESSAGE, $response->body);
    }

    #[Scenario('BAN-07', 'Protector is switched off globally and an address is on the bad-IP list', 'that address requests a page', 'it is still blocked (the list is enforced before the global switch is looked at)')]
    public function testBadIpListIsEnforcedEvenWhenProtectorIsSwitchedOff(): void
    {
        $this->saveIpLists($this->admin(), "127.0.0.50\n");
        $this->configure(['global_disabled' => 1]);

        $this->assertStringContainsString(self::BAD_IP_MESSAGE, $this->probe($this->client('127.0.0.50'))->body);
    }

    #[Scenario('BAN-08', 'the isolated-comment action bans, and the attacker is an administrator (group 1 is exempt from bans)', 'the administrator triggers it and then requests a page', 'the administrator is not banned but is logged out')]
    public function testAdministratorsAreNotBannedButAreLoggedOut(): void
    {
        $this->configure(['isocom_action' => 15]);
        $admin = $this->admin(self::ATTACKER);

        $admin->client()->get('/probe.php', ['cid' => ',password /*']);
        $next = $admin->client()->get('/probe.php', ['n' => 1]);

        $this->assertTrue($this->isServed($next));
        $this->assertSame(0, $next->json()['uid']);
    }

    #[Scenario('BAN-09', 'a bad-IP filter that redirects is enabled ("precommon_badip_redirection")', 'a banned address requests a page', 'it is redirected to the configured address instead of seeing the message')]
    public function testBadIpRedirectionFilterRedirectsBannedAddresses(): void
    {
        $this->configure(['filters' => 'precommon_badip_redirection']);
        $this->saveIpLists($this->admin(), "127.0.0.50\n");

        $response = $this->probe($this->client('127.0.0.50'));

        $this->assertSame(302, $response->status);
        $this->assertSame('http://yahoo.com/', $response->location());
    }

    #[Scenario('BAN-10', 'the administrator saves a two-line "allowed IPs for group 1" list', 'the stored list is used on the administrator\'s next request', 'the list holds the line numbers instead of the addresses and the administrator is locked out')]
    #[KnownDefect('D1', 'the form handler iterates array_keys() of the submitted lines instead of the lines')]
    public function testSavingTheGroupOneListLocksTheAdministratorOut(): void
    {
        $admin = $this->admin();
        $this->saveIpLists($admin, '', "127.0.0.1\n127.0.0.2");

        $next = $admin->client()->get('/probe.php');

        $this->assertStringContainsString('This account is disabled for your IP by Protector.', $next->body);
    }

    #[Scenario('BAN-11', 'the administrator saves a one-line "allowed IPs for group 1" list', 'the administrator requests a page', 'the restriction is not applied at all (the stored list is just "0"), so the feature cannot be used through the form')]
    #[KnownDefect('D1', 'the form handler iterates array_keys() of the submitted lines instead of the lines')]
    public function testSavingASingleLineGroupOneListRestrictsNothing(): void
    {
        $admin = $this->admin();
        $this->saveIpLists($admin, '', '127.0.0.99');

        $next = $admin->client()->get('/probe.php');

        $this->assertSame(1, $next->json()['uid']);
    }
}
