<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Tests\Functional\Scenario;

use ImpressCMS\Module\Protector\Tests\Functional\KnownDefect;
use ImpressCMS\Module\Protector\Tests\Functional\Scenario;
use ImpressCMS\Module\Protector\Tests\Functional\SiteTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('content')]
final class ContentChecksTest extends SiteTestCase
{
    #[Scenario('SPM-01', 'default preferences (guests may post up to 4 links)', 'a guest posts a message with 4 external links', 'the post reaches the page')]
    public function testGuestPostBelowTheLinkLimitIsAccepted(): void
    {
        $response = $this->client()->post('/probe.php', ['msg' => $this->links(4)]);

        $this->assertTrue($this->isServed($response));
    }

    #[Scenario('SPM-02', 'default preferences (guest limit 5)', 'a guest posts a message with 6 external links', 'the request is terminated with an empty page and a "URI SPAM" record with the score is logged')]
    public function testGuestPostAtTheLinkLimitIsRejected(): void
    {
        $response = $this->client()->post('/probe.php', ['msg' => $this->links(6)]);

        $this->assertSame('', trim($response->body));
        $this->assertSame(['URI SPAM'], $this->logTypes());
        $this->assertStringContainsString('SPAM POINT: 6', $this->logRows()[0]['description']);
    }

    #[Scenario('SPM-03', 'default preferences', 'a guest posts a message with 6 BBCode links like [url=www.spam.example]', 'the request is terminated and logged')]
    public function testBbCodeLinksAreCounted(): void
    {
        $message = str_repeat('[url=www.spam.example]x[/url] ', 6);

        $response = $this->client()->post('/probe.php', ['msg' => $message]);

        $this->assertSame('', trim($response->body));
        $this->assertSame(['URI SPAM'], $this->logTypes());
    }

    #[Scenario('SPM-04', 'default preferences', 'a guest posts a message with 10 links to the site itself', 'links to the own host are not counted and the post is accepted')]
    public function testLinksToTheOwnSiteAreNotCounted(): void
    {
        $response = $this->client()->post('/probe.php', ['msg' => str_repeat('http://202.test/page ', 10)]);

        $this->assertTrue($this->isServed($response));
    }

    #[Scenario('SPM-05', 'guest link limit set to 0 (off)', 'a guest posts a message with 20 external links', 'the post is accepted')]
    public function testGuestLinkLimitCanBeSwitchedOff(): void
    {
        $this->configure(['spamcount_uri4guest' => 0]);

        $response = $this->client()->post('/probe.php', ['msg' => $this->links(20)]);

        $this->assertTrue($this->isServed($response));
    }

    #[Scenario('SPM-06', 'default preferences (the limit for logged-in users is off)', 'an administrator posts a message with 20 external links', 'the post is accepted')]
    public function testAdministratorPostsAreNotLimitedByDefault(): void
    {
        $admin = $this->admin(self::ATTACKER);

        $response = $admin->client()->post('/probe.php', ['msg' => $this->links(20)]);

        $this->assertTrue($this->isServed($response));
    }

    #[Scenario('FLT-01', 'the filter "postcommon_post_need_multibyte" is enabled', 'a guest posts 150 plain ASCII characters', 'the post is rejected with the "no multibyte characters" message and a "Singlebyte SPAM" record is logged')]
    public function testMultibyteFilterRejectsLongAsciiPostsFromGuests(): void
    {
        $this->configure(['filters' => 'postcommon_post_need_multibyte']);

        $response = $this->client()->post('/probe.php', ['msg' => str_repeat('a', 150)]);

        $this->assertStringContainsString('does not contain any multibyte characters', $response->body);
        $this->assertSame(['Singlebyte SPAM'], $this->logTypes());
    }

    #[Scenario('FLT-02', 'the filter "postcommon_post_need_multibyte" is enabled', 'a guest posts 150 characters that include multibyte characters', 'the post is accepted')]
    public function testMultibyteFilterAcceptsPostsWithMultibyteCharacters(): void
    {
        $this->configure(['filters' => 'postcommon_post_need_multibyte']);

        $response = $this->client()->post('/probe.php', ['msg' => str_repeat('é', 150)]);

        $this->assertTrue($this->isServed($response));
    }

    #[Scenario('FLT-03', 'the filter "prepurge_exit_message" is enabled and contamination ends the request', 'a request injects xoopsConfig[nocommon]', 'the filter\'s message is shown')]
    public function testPrepurgeFilterProvidesTheExitMessage(): void
    {
        $this->configure(['log_level' => 0, 'contami_action' => 3, 'filters' => 'prepurge_exit_message']);

        $response = $this->client()->get('/probe.php?xoopsConfig%5Bnocommon%5D=1');

        $this->assertStringContainsString('Protector detects attacking actions', $response->body);
    }

    #[Scenario('FLT-04', 'a third-party filter written as a function (protector_postcommon_post_zzmarker) is dropped into the filters directory and listed in the preferences', 'a guest posts a form', 'the filter ran and could change the posted data')]
    public function testDropInFunctionFilterIsExecuted(): void
    {
        $file = self::layout()->customFilterDir() . '/postcommon_post_zzmarker.php';
        file_put_contents($file, "<?php\nfunction protector_postcommon_post_zzmarker() { \$_POST['marker'] = 'set-by-function-filter'; return true; }\n");

        try {
            $this->configure(['filters' => 'postcommon_post_zzmarker']);
            $response = $this->client()->post('/probe.php', ['msg' => 'hello']);
        } finally {
            unlink($file);
        }

        $this->assertSame('set-by-function-filter', $response->json()['post']['marker']);
    }

    #[Scenario('FLT-05', 'a third-party filter written as a class (protector_postcommon_post_zzclass extends ProtectorFilterAbstract) is dropped into the filters directory and listed in the preferences', 'a guest posts a form', 'the filter ran and could change the posted data')]
    public function testDropInClassFilterIsExecuted(): void
    {
        $file = self::layout()->customFilterDir() . '/postcommon_post_zzclass.php';
        file_put_contents($file, "<?php\nclass protector_postcommon_post_zzclass extends ProtectorFilterAbstract {\n    function execute() { \$_POST['marker'] = 'set-by-class-filter'; return true; }\n}\n");

        try {
            $this->configure(['filters' => 'postcommon_post_zzclass']);
            $response = $this->client()->post('/probe.php', ['msg' => 'hello']);
        } finally {
            unlink($file);
        }

        $this->assertSame('set-by-class-filter', $response->json()['post']['marker']);
    }

    #[Scenario('FLT-06', 'the filter "postcommon_post_htmlpurify4everyone" is enabled', 'a guest posts a message longer than 32 characters', 'the page is served and the posted HTML has been purified: the script is gone, the harmless markup stays (D11 fixed)')]
    public function testHtmlPurifierFilterCleansPostedHtml(): void
    {
        $this->configure(['filters' => 'postcommon_post_htmlpurify4everyone']);

        $response = $this->client()->post('/probe.php', ['msg' => '<p>hello world hello world hello world</p><script>alert(1)</script>']);

        $this->assertTrue($this->isServed($response));
        $this->assertStringContainsString('<p>hello world hello world hello world</p>', $response->json()['post']['msg']);
        $this->assertStringNotContainsString('<script', $response->json()['post']['msg']);
    }

    #[Scenario('FLT-07', 'the filter "postcommon_post_htmlpurify4guest" is enabled', 'a guest posts a message longer than 32 characters', 'the posted HTML has been purified')]
    public function testGuestHtmlPurifierFilterCleansPostedHtml(): void
    {
        $this->configure(['filters' => 'postcommon_post_htmlpurify4guest']);

        $response = $this->client()->post('/probe.php', ['msg' => '<p>hello world hello world hello world</p><script>alert(1)</script>']);

        $this->assertTrue($this->isServed($response));
        $this->assertStringNotContainsString('<script', $response->json()['post']['msg']);
    }

    #[Scenario('FEA-01', '"disable features" at its default (XML-RPC and the old criteria bug)', 'a request posts uname=0, logging off', 'the request is terminated with an empty page')]
    public function testCriteriaBugProbeIsStopped(): void
    {
        $this->configure(['log_level' => 0]);

        $response = $this->client()->post('/probe.php', ['uname' => '0']);

        $this->assertSame('', $response->body);
    }

    #[Scenario('FEA-02', '"disable features" set to none', 'a request posts uname=0', 'the request is served')]
    public function testCriteriaBugProbeIsNotStoppedWhenFeatureIsOff(): void
    {
        $this->configure(['disable_features' => 0]);

        $response = $this->client()->post('/probe.php', ['uname' => '0']);

        $this->assertTrue($this->isServed($response));
    }

    #[Scenario('FEA-03', '"disable features" at its default, logging at its default', 'a request asks for /xmlrpc.php', 'the log write needs the database, so the page dies with "No DB connection"')]
    #[KnownDefect('D9', 'events raised before the database service exists cannot be logged')]
    public function testXmlRpcRequestAtDefaultLogLevelDiesWithoutDatabase(): void
    {
        $response = $this->client()->get('/xmlrpc.php');

        $this->assertStringContainsString('No DB connection', $response->body);
    }

    #[Scenario('MAN-01', 'the manipulation check is on', 'the site\'s front page is requested twice', 'the first request stores a fingerprint of the web root and index.php in the preferences; it stays unchanged on the second request')]
    public function testManipulationCheckStoresAFingerprint(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped('Cannot run on Windows: the module compares $_SERVER["SCRIPT_FILENAME"] (backslashes) with ICMS_ROOT_PATH (slashes), so the check never fires here.');
        }

        $this->configure(['enable_manip_check' => 1]);

        $this->client()->get('/index.php');
        $first = $this->preference('manip_value');
        $this->client()->get('/index.php');

        $this->assertNotSame('', $first);
        $this->assertSame($first, $this->preference('manip_value'));
    }

    private function links(int $count): string
    {
        return implode(' ', array_map(static fn (int $i): string => "http://spam{$i}.example/x", range(1, $count)));
    }
}
