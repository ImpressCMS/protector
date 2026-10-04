<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Tests\Functional\Scenario;

use ImpressCMS\Module\Protector\Tests\Functional\KnownDefect;
use ImpressCMS\Module\Protector\Tests\Functional\Scenario;
use ImpressCMS\Module\Protector\Tests\Functional\SiteTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('sanitising')]
final class RequestSanitisingTest extends SiteTestCase
{
    private const CONTAMINATION = '/probe.php?xoopsConfig%5Bnocommon%5D=1';

    private const BLOCK_MESSAGE = 'Protector detects attacking actions';

    private const BAD_IP_MESSAGE = 'You are registered as BAD_IP by Protector.';

    #[Scenario('SAN-01', 'default preferences', 'a request tries to inject xoopsConfig[nocommon]', 'the request is terminated with the Protector message and a CONTAMI record is logged (D9 fixed)')]
    public function testContaminationAtDefaultLogLevelIsLoggedAndStopped(): void
    {
        $response = $this->client()->get(self::CONTAMINATION);

        $this->assertStringContainsString(self::BLOCK_MESSAGE, $response->body);
        $this->assertSame(['CONTAMI'], $this->logTypes());
    }

    #[Scenario('SAN-02', 'logging off, contamination action "none"', 'a request tries to inject xoopsConfig[nocommon]', 'Protector lets the request through and the core itself answers with a redirect')]
    public function testContaminationWithActionNoneIsLeftToTheCore(): void
    {
        $this->configure(['log_level' => 0, 'contami_action' => 0]);

        $response = $this->client()->get(self::CONTAMINATION);

        $this->assertSame(302, $response->status);
        $this->assertStringNotContainsString(self::BLOCK_MESSAGE, $response->body);
    }

    #[Scenario('SAN-03', 'logging off, contamination action "exit"', 'a request tries to inject xoopsConfig[nocommon]', 'the request is terminated with the Protector message')]
    public function testContaminationWithActionExitTerminatesTheRequest(): void
    {
        $this->configure(['log_level' => 0, 'contami_action' => 3]);

        $response = $this->client()->get(self::CONTAMINATION);

        $this->assertStringContainsString(self::BLOCK_MESSAGE, $response->body);
    }

    #[Scenario('SAN-04', 'logging off, contamination action "exit + temporary ban"', 'a request tries to inject xoopsConfig[nocommon], then the same address requests a normal page', 'the first request is terminated and the address is banned, so the second request gets the jail message (D10 fixed)')]
    public function testContaminationWithBanActionBansTheVisitor(): void
    {
        $this->configure(['log_level' => 0, 'contami_action' => 7]);
        $client = $this->client();

        $first = $client->get(self::CONTAMINATION);
        $second = $this->probe($client, ['next' => 1]);

        $this->assertStringContainsString(self::BLOCK_MESSAGE, $first->body);
        $this->assertStringContainsString('You are registered as BAD_IP by Protector.', $second->body);
    }

    #[Scenario('SAN-05', 'isolated-comment action "none"', 'a request carries a value ending in an unterminated "/*"', 'the value is passed on unchanged and an ISOCOM record is logged')]
    public function testIsolatedCommentIsLoggedButPassedOnForActionNone(): void
    {
        $this->configure(['isocom_action' => 0]);

        $response = $this->probe($this->client(), ['cid' => ',password /*']);

        $this->assertSame(',password /*', $response->json()['get']['cid']);
        $this->assertSame(['ISOCOM'], $this->logTypes());
    }

    #[Scenario('SAN-06', 'isolated-comment action "sanitize"', 'a request carries a value ending in an unterminated "/*"', 'the comment is closed ("*/" appended) and an ISOCOM record is logged')]
    public function testIsolatedCommentIsClosedForActionSanitize(): void
    {
        $this->configure(['isocom_action' => 1]);

        $response = $this->probe($this->client(), ['cid' => ',password /*']);

        $this->assertSame(',password /**/', $response->json()['get']['cid']);
        $this->assertSame(['ISOCOM'], $this->logTypes());
    }

    #[Scenario('SAN-07', 'isolated-comment action "exit"', 'a request carries a value ending in an unterminated "/*"', 'the request is terminated with the Protector message and logged')]
    public function testIsolatedCommentTerminatesTheRequestForActionExit(): void
    {
        $this->configure(['isocom_action' => 3]);

        $response = $this->probe($this->client(), ['cid' => ',password /*']);

        $this->assertStringContainsString(self::BLOCK_MESSAGE, $response->body);
        $this->assertSame(['ISOCOM'], $this->logTypes());
    }

    #[Scenario('SAN-08', 'isolated-comment action "exit + temporary ban"', 'the attacker triggers it, then both the attacker and another address request a page', 'the attacker is shown the BAD_IP message with an expiry time; the other address is served normally')]
    public function testIsolatedCommentWithTemporaryBanBlocksOnlyTheAttacker(): void
    {
        $this->configure(['isocom_action' => 7]);
        $attacker = $this->client('127.0.0.2');

        $attacker->get('/probe.php?cid=' . urlencode(',password /*'));
        $blocked = $this->probe($attacker, ['next' => 1]);
        $bystander = $this->probe($this->client('127.0.0.3'), ['next' => 1]);

        $this->assertStringContainsString(self::BAD_IP_MESSAGE, $blocked->body);
        $this->assertStringContainsString('expired on', $blocked->body);
        $this->assertSame('1', $bystander->json()['get']['next']);
    }

    #[Scenario('SAN-09', 'isolated-comment action "exit + permanent ban"', 'the attacker triggers it, then requests a page', 'the attacker is shown the BAD_IP message')]
    public function testIsolatedCommentWithPermanentBanBlocksTheAttacker(): void
    {
        $this->configure(['isocom_action' => 15]);
        $attacker = $this->client('127.0.0.2');

        $attacker->get('/probe.php?cid=' . urlencode(',password /*'));
        $blocked = $this->probe($attacker, ['next' => 1]);

        $this->assertStringContainsString(self::BAD_IP_MESSAGE, $blocked->body);
    }

    #[Scenario('SAN-10', 'union action "none"', 'a request carries "1 UNION SELECT 1"', 'the value is passed on unchanged and a UNION record is logged')]
    public function testUnionIsLoggedButPassedOnForActionNone(): void
    {
        $this->configure(['union_action' => 0]);

        $response = $this->probe($this->client(), ['id' => '1 UNION SELECT 1']);

        $this->assertSame('1 UNION SELECT 1', $response->json()['get']['id']);
        $this->assertSame(['UNION'], $this->logTypes());
    }

    #[Scenario('SAN-11', 'union action "sanitize"', 'a request carries "1 UNION SELECT 1"', 'the word UNION is rewritten to "uni-on" and a UNION record is logged')]
    public function testUnionIsDefusedForActionSanitize(): void
    {
        $this->configure(['union_action' => 1]);

        $response = $this->probe($this->client(), ['id' => '1 UNION SELECT 1']);

        $this->assertSame('1 uni-on SELECT 1', $response->json()['get']['id']);
        $this->assertSame(['UNION'], $this->logTypes());
    }

    #[Scenario('SAN-12', 'union action "exit"', 'a request carries "1 UNION SELECT 1"', 'the request is terminated with the Protector message and logged')]
    public function testUnionTerminatesTheRequestForActionExit(): void
    {
        $this->configure(['union_action' => 3]);

        $response = $this->probe($this->client(), ['id' => '1 UNION SELECT 1']);

        $this->assertStringContainsString(self::BLOCK_MESSAGE, $response->body);
        $this->assertSame(['UNION'], $this->logTypes());
    }

    #[Scenario('SAN-13', 'default preferences', 'a request carries a NUL byte', 'the NUL byte is replaced by a space, the page is served and a NullByte record is logged (D9 fixed)')]
    public function testNullByteAtDefaultLogLevelIsLogged(): void
    {
        $response = $this->client()->get('/probe.php?a=x%00y');

        $this->assertSame('x y', $response->json()['get']['a']);
        $this->assertSame(['NullByte'], $this->logTypes());
    }

    #[Scenario('SAN-14', 'logging off, NUL-byte sanitising on', 'a request carries a NUL byte', 'the NUL byte is replaced by a space')]
    public function testNullByteIsReplacedBySpace(): void
    {
        $this->configure(['log_level' => 0]);

        $response = $this->client()->get('/probe.php?a=x%00y');

        $this->assertSame('x y', $response->json()['get']['a']);
    }

    #[Scenario('SAN-15', 'NUL-byte sanitising off', 'a request carries a NUL byte', 'the value is passed on unchanged')]
    public function testNullByteIsLeftAloneWhenSanitisingIsOff(): void
    {
        $this->configure(['san_nullbyte' => 0]);

        $response = $this->client()->get('/probe.php?a=x%00y');

        $this->assertSame("x\0y", $response->json()['get']['a']);
    }

    #[Scenario('SAN-16', 'default preferences', 'a request carries "../../etc/passwd"', 'the value is rewritten, the page is served and a DirTraversal record is logged (D9 fixed)')]
    public function testDirectoryTraversalAtDefaultLogLevelIsLogged(): void
    {
        $response = $this->client()->get('/probe.php?file=' . urlencode('../../etc/passwd'));

        $this->assertSame('../../etc/passwd .', $response->json()['get']['file']);
        $this->assertSame(['DirTraversal'], $this->logTypes());
    }

    #[Scenario('SAN-17', 'logging off, "../" elimination on', 'a request carries "../../etc/passwd"', 'the value is rewritten with a trailing " ."')]
    public function testDirectoryTraversalIsRewritten(): void
    {
        $this->configure(['log_level' => 0]);

        $response = $this->client()->get('/probe.php?file=' . urlencode('../../etc/passwd'));

        $this->assertSame('../../etc/passwd .', $response->json()['get']['file']);
    }

    #[Scenario('SAN-18', '"../" elimination off', 'a request carries "../../etc/passwd"', 'the value is passed on unchanged')]
    public function testDirectoryTraversalIsLeftAloneWhenEliminationIsOff(): void
    {
        $this->configure(['file_dotdot' => 0]);

        $response = $this->client()->get('/probe.php?file=' . urlencode('../../etc/passwd'));

        $this->assertSame('../../etc/passwd', $response->json()['get']['file']);
    }

    #[Scenario('SAN-19', 'the visitor\'s address matches "reliable IPs"', 'a request carries "../../etc/passwd"', 'the value is passed on unchanged')]
    public function testReliableAddressesSkipDirectoryTraversalElimination(): void
    {
        $this->configure(['log_level' => 0, 'reliable_ips' => ['^127\.0\.0\.2$']]);

        $response = $this->client('127.0.0.2')->get('/probe.php?file=' . urlencode('../../etc/passwd'));

        $this->assertSame('../../etc/passwd', $response->json()['get']['file']);
    }

    #[Scenario('SAN-20', '"force integer on *id parameters" off', 'a request carries topic_id=12abc;--', 'the value is passed on unchanged')]
    public function testIdParametersAreLeftAloneByDefault(): void
    {
        $response = $this->probe($this->client(), ['topic_id' => '12abc;--']);

        $this->assertSame('12abc;--', $response->json()['get']['topic_id']);
    }

    #[Scenario('SAN-21', '"force integer on *id parameters" on', 'a request carries topic_id=12abc;-- and name=zz', 'only characters [0-9a-zA-Z_-] remain in the *id parameter; other parameters are untouched')]
    public function testIdParametersAreReducedToSafeCharacters(): void
    {
        $this->configure(['id_forceintval' => 1]);

        $response = $this->probe($this->client(), ['topic_id' => '12abc;--', 'name' => 'zz;--']);

        $this->assertSame('12abc--', $response->json()['get']['topic_id']);
        $this->assertSame('zz;--', $response->json()['get']['name']);
    }

    #[Scenario('SAN-23', '"force integer on *id parameters" on', 'a request carries topic_id=12abc;--', 'the combined request array holds the same cleaned value as the query string (D12)')]
    public function testRequestArrayFollowsTheCleanedIdParameter(): void
    {
        $this->configure(['id_forceintval' => 1]);

        $json = $this->probe($this->client(), ['topic_id' => '12abc;--'])->json();

        $this->assertSame('12abc--', $json['get']['topic_id']);
        $this->assertSame('12abc--', $json['request']['topic_id']);
    }

    #[Scenario('SAN-24', '"../" elimination on', 'a request carries "../../etc/passwd"', 'the combined request array holds the same rewritten value as the query string (D12)')]
    public function testRequestArrayFollowsTheRewrittenTraversal(): void
    {
        $this->configure(['log_level' => 0]);

        $json = $this->client()->get('/probe.php?file=' . urlencode('../../etc/passwd'))->json();

        $this->assertSame('../../etc/passwd .', $json['get']['file']);
        $this->assertSame('../../etc/passwd .', $json['request']['file']);
    }

    #[Scenario('SAN-22', 'Protector switched off globally', 'requests carry a NUL byte, an isolated comment and a UNION', 'all values are passed on unchanged and nothing is logged')]
    public function testGloballyDisabledProtectorChangesNothing(): void
    {
        $this->configure(['global_disabled' => 1, 'union_action' => 3, 'isocom_action' => 3]);

        $response = $this->probe($this->client(), ['a' => "x\0y", 'cid' => ',password /*', 'id' => '1 UNION SELECT 1']);

        $get = $response->json()['get'];
        $this->assertSame("x\0y", $get['a']);
        $this->assertSame(',password /*', $get['cid']);
        $this->assertSame('1 UNION SELECT 1', $get['id']);
        $this->assertSame([], $this->logRows());
    }
}
