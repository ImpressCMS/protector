<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Tests\Functional\Scenario;

use ImpressCMS\Module\Protector\Tests\Functional\Scenario;
use ImpressCMS\Module\Protector\Tests\Functional\SiteTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('session')]
final class SessionAndGroupAccessTest extends SiteTestCase
{
    #[Scenario('SES-01', 'default preferences (24 significant address bits, group 1 protected)', 'an administrator logs in from 127.0.0.2 and the same browser then arrives from 127.0.1.2 (another /24 network)', 'the session is purged, the visitor is redirected to the home page and is a guest afterwards')]
    public function testAdministratorSessionIsPurgedWhenTheNetworkChanges(): void
    {
        $admin = $this->admin('127.0.0.2');
        $this->assertSame(1, $admin->client()->get('/probe.php')->json()['uid']);

        $moved = $admin->client()->withSourceIp('127.0.1.2');
        $response = $moved->get('/probe.php');
        $back = $admin->client()->get('/probe.php');

        $this->assertSame(302, $response->status);
        $this->assertSame('http://202.test/', $response->location());
        $this->assertSame(0, $back->json()['uid']);
    }

    #[Scenario('SES-02', 'default preferences', 'an administrator logs in from 127.0.0.2 and the same browser then arrives from 127.0.0.77 (same /24 network)', 'the session is kept')]
    public function testAdministratorSessionSurvivesAMoveWithinTheNetwork(): void
    {
        $admin = $this->admin('127.0.0.2');

        $moved = $admin->client()->withSourceIp('127.0.0.77');

        $this->assertSame(1, $moved->get('/probe.php')->json()['uid']);
    }

    #[Scenario('SES-03', 'the list of protected groups is emptied', 'an administrator moves to another /24 network', 'the session is kept')]
    public function testSessionPurgeAppliesOnlyToTheProtectedGroups(): void
    {
        $this->configure(['groups_denyipmove' => []]);
        $admin = $this->admin('127.0.0.2');

        $moved = $admin->client()->withSourceIp('127.0.1.2');

        $this->assertSame(1, $moved->get('/probe.php')->json()['uid']);
    }

    #[Scenario('SES-04', '"significant address bits" set to 0', 'an administrator moves to another /24 network', 'the check is off and the session is kept')]
    public function testSessionBindingCanBeSwitchedOff(): void
    {
        $this->configure(['session_fixed_topbit' => 0]);
        $admin = $this->admin('127.0.0.2');

        $moved = $admin->client()->withSourceIp('127.0.1.2');

        $this->assertSame(1, $moved->get('/probe.php')->json()['uid']);
    }

    #[Scenario('SES-05', 'the allowed-IPs list for group 1 contains only 127.0.0.9 (written in the module\'s storage format, because the form cannot store it, see BAN-10/11)', 'an administrator logged in earlier requests a page from 127.0.0.2, and again from 127.0.0.9', 'from the unlisted address the account is disabled with a message; from the listed address the administrator is recognised')]
    public function testGroupOneAccountsAreRestrictedToTheListedAddresses(): void
    {
        $admin = $this->admin('127.0.0.2');
        self::layout()->writeGroupOneIps(['127.0.0.9']);

        $fromOther = $admin->client()->get('/probe.php');
        $fromListed = $admin->client()->withSourceIp('127.0.0.9')->get('/probe.php');

        $this->assertStringContainsString('This account is disabled for your IP by Protector.', $fromOther->body);
        $this->assertSame(1, $fromListed->json()['uid']);
    }

    #[Scenario('SES-06', 'the allowed-IPs list for group 1 ends with a dot (127.0.0.), meaning a prefix match', 'an administrator requests a page from 127.0.0.2', 'the administrator is recognised')]
    public function testGroupOneListSupportsPrefixMatching(): void
    {
        $admin = $this->admin('127.0.0.2');
        self::layout()->writeGroupOneIps(['127.0.0.']);

        $this->assertSame(1, $admin->client()->get('/probe.php')->json()['uid']);
    }

    #[Scenario('SES-07', 'the allowed-IPs list for group 1 is empty', 'an administrator requests a page', 'the administrator is recognised (an empty list means "all addresses")')]
    public function testEmptyGroupOneListAllowsEveryAddress(): void
    {
        $admin = $this->admin('127.0.0.2');
        self::layout()->writeGroupOneIps([]);

        $this->assertSame(1, $admin->client()->get('/probe.php')->json()['uid']);
    }
}
