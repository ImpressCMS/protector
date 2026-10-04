<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Tests\Functional\Scenario;

use ImpressCMS\Module\Protector\Tests\Functional\Scenario;
use ImpressCMS\Module\Protector\Tests\Functional\SiteTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('database-trap')]
#[Group('output-check')]
final class DatabaseTrapAndOutputCheckTest extends SiteTestCase
{
    private const UNION = '1 UNION SELECT 1';

    private const SCRIPT = '<script>alert(1)</script>0123456789';

    #[Scenario('DBT-01', 'default preferences', 'a request value containing UNION SELECT reaches an unescaped query', 'the query is stopped with "SQL Injection found", before it runs; the earlier UNION check has logged the request')]
    public function testInjectionReachingAnUnescapedQueryIsStopped(): void
    {
        $response = $this->client()->get('/sqlq.php', ['q' => self::UNION]);

        $this->assertStringContainsString('SQL Injection found', $response->body);
        $this->assertContains('UNION', $this->logTypes());
    }

    #[Scenario('DBT-02', 'the visitor\'s address matches "reliable IPs"', 'the same request is made', 'the request-level checks are skipped but the query is still stopped, and the trap logs it as "SQL Injection"')]
    public function testReliableAddressesAreStillCoveredByTheTrap(): void
    {
        $this->configure(['reliable_ips' => ['^127\.0\.0\.2$']]);

        $response = $this->client('127.0.0.2')->get('/sqlq.php', ['q' => self::UNION]);

        $this->assertStringContainsString('SQL Injection found', $response->body);
        $this->assertSame(['SQL Injection'], $this->logTypes());
    }

    #[Scenario('DBT-03', 'default preferences', 'a hostile value is escaped and quoted by the page before it is used in a query', 'the query runs normally')]
    public function testEscapedValuesPassThroughTheTrap(): void
    {
        $response = $this->client()->get('/sqlq.php', ['mode' => 'safe', 'q' => "x' OR '1'='1"]);

        $this->assertTrue($response->json()['executed']);
    }

    #[Scenario('DBT-04', 'default preferences', 'a plain number is used in the unescaped query', 'the query runs normally')]
    public function testPlainValuesPassThroughTheTrap(): void
    {
        $response = $this->client()->get('/sqlq.php', ['q' => '1']);

        $this->assertTrue($response->json()['executed']);
        $this->assertSame(1, $response->json()['rows']);
    }

    #[Scenario('DBT-05', 'default preferences', 'an injection that contains none of the trap\'s trigger words ("1 or 1=1 -- ") is used in the unescaped query', 'the trap does not notice it and the query runs (documented limit of the heuristic)')]
    public function testInjectionWithoutTriggerWordsIsNotDetected(): void
    {
        $response = $this->client()->get('/sqlq.php', ['q' => '1 or 1=1 -- ']);

        $this->assertTrue($response->json()['executed']);
        $this->assertSame([], $this->logRows());
    }

    #[Scenario('DBT-06', '"enable DB layer trap" off', 'a request value containing UNION SELECT reaches an unescaped query', 'the query runs; the earlier UNION check still logs the request')]
    public function testTrapCanBeSwitchedOff(): void
    {
        $this->configure(['enable_dblayertrap' => 0]);

        $response = $this->client()->get('/sqlq.php', ['q' => self::UNION]);

        $this->assertTrue($response->json()['executed']);
        $this->assertSame(['UNION'], $this->logTypes());
    }

    #[Scenario('DBT-07', 'Protector switched off globally', 'a request value containing UNION SELECT reaches an unescaped query', 'the query runs and nothing is logged')]
    public function testGloballyDisabledProtectorDoesNotTrapQueries(): void
    {
        $this->configure(['global_disabled' => 1]);

        $response = $this->client()->get('/sqlq.php', ['q' => self::UNION]);

        $this->assertTrue($response->json()['executed']);
        $this->assertSame([], $this->logRows());
    }

    #[Scenario('DBT-08', 'default preferences', 'a harmless request, then a request with a suspicious value, hit a page', 'the anti-injection marker constant is defined for both; the database alternative is installed only for the suspicious one')]
    public function testDatabaseAlternativeIsInstalledOnlyForSuspiciousRequests(): void
    {
        $harmless = $this->probe($this->client(), ['a' => 'plain'])->json()['constants'];
        $suspicious = $this->probe($this->client(), ['a' => "it's a quote"])->json()['constants'];

        $this->assertTrue($harmless['PROTECTOR_ENABLED_ANTI_SQL_INJECTION']);
        $this->assertNull($harmless['XOOPS_DB_ALTERNATIVE']);
        $this->assertTrue($suspicious['PROTECTOR_ENABLED_ANTI_SQL_INJECTION']);
        $this->assertSame('ProtectorMysqlDatabase', $suspicious['XOOPS_DB_ALTERNATIVE']);
    }

    #[Scenario('OUT-01', 'default preferences', 'a script tag is passed in the query string and echoed unescaped into an HTML page', 'the whole page is replaced by "XSS found by Protector."')]
    public function testReflectedScriptInHtmlIsReplaced(): void
    {
        $response = $this->client()->get('/reflect.php', ['x' => self::SCRIPT]);

        $this->assertSame('XSS found by Protector.', trim($response->body));
    }

    #[Scenario('OUT-02', 'default preferences', 'a payload shorter than 15 characters after "<" is echoed into an HTML page', 'the page is served (documented limit of the heuristic)')]
    public function testShortPayloadIsNotDetected(): void
    {
        $response = $this->client()->get('/reflect.php', ['x' => '<b>x</b>']);

        $this->assertStringContainsString('<b>x</b>', $response->body);
    }

    #[Scenario('OUT-03', 'default preferences', 'a script tag is echoed into a JSON response', 'non-HTML content types are not checked, so the response is served')]
    public function testNonHtmlResponsesAreNotChecked(): void
    {
        $response = $this->client()->get('/reflect.php', ['type' => 'json', 'x' => self::SCRIPT]);

        $this->assertStringContainsString('alert(1)', $response->body);
        $this->assertStringNotContainsString('XSS found', $response->body);
    }

    #[Scenario('OUT-04', '"enable Big Umbrella" off', 'a script tag is echoed into an HTML page', 'the page is served')]
    public function testOutputCheckCanBeSwitchedOff(): void
    {
        $this->configure(['enable_bigumbrella' => 0]);

        $response = $this->client()->get('/reflect.php', ['x' => self::SCRIPT]);

        $this->assertStringContainsString('<script>alert(1)</script>', $response->body);
    }

    #[Scenario('OUT-05', 'default preferences', 'a quote-based attribute injection payload is echoed into an HTML page', 'the whole page is replaced by "XSS found by Protector."')]
    public function testAttributeInjectionIsReplaced(): void
    {
        $response = $this->client()->get('/reflect.php', ['x' => '"onmouseover="alert(1)" x=0123456789']);

        $this->assertSame('XSS found by Protector.', trim($response->body));
    }

    #[Scenario('OUT-06', 'a page defines BIGUMBRELLA_DISABLED before the core boots', 'a script tag is echoed into that page', 'the page is served')]
    public function testPagesCanOptOutOfTheOutputCheck(): void
    {
        $response = $this->client()->get('/reflect_nobu.php', ['x' => self::SCRIPT]);

        $this->assertStringContainsString('<script>alert(1)</script>', $response->body);
    }

    #[Scenario('OUT-07', 'default preferences', 'a script tag is passed but the page does not echo it', 'the page is served normally')]
    public function testPagesThatDoNotEchoTheValueAreUnaffected(): void
    {
        $response = $this->probe($this->client(), ['x' => self::SCRIPT]);

        $this->assertSame(200, $response->status);
        $this->assertSame(self::SCRIPT, $response->json()['get']['x']);
    }
}
