<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Tests\Functional\Scenario;

use ImpressCMS\Module\Protector\Tests\Functional\Scenario;
use ImpressCMS\Module\Protector\Tests\Functional\Site\AdminSession;
use ImpressCMS\Module\Protector\Tests\Functional\SiteTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('admin')]
final class AdminPagesTest extends SiteTestCase
{
    private const START_PAGE = '/modules/protector/admin/index.php';

    private AdminSession $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedLog();
        $this->admin = $this->admin();
    }

    #[Scenario('ADM-01', 'three log records exist (one of them with markup in its description)', 'the administrator opens the module\'s admin start page', 'the page lists all three records with user, address, type and description, shows the two IP list text areas, and escapes the markup')]
    public function testStartPageListsTheLog(): void
    {
        $page = $this->admin->page(self::START_PAGE);
        $text = $this->visibleText($page);

        $this->assertSame(200, $page->status);
        $this->assertStringContainsString('TESTA', $text);
        $this->assertStringContainsString('TESTB', $text);
        $this->assertStringContainsString('Guests', $text);
        $this->assertStringContainsString('10.0.0.1', $text);
        $this->assertStringContainsString("name='bad_ips'", $page->body);
        $this->assertStringContainsString("name='group1_ips'", $page->body);
        $this->assertStringContainsString('third &lt;b&gt;', $page->body);
        $this->assertStringNotContainsString('third <b>', $page->body);
        $this->assertSame(3, preg_match_all("/name='ids\\[\\]'/", $page->body));
    }

    #[Scenario('ADM-02', 'three log records exist', 'the administrator asks for 1 record per page starting at the second', 'exactly the second record is listed')]
    public function testStartPagePaging(): void
    {
        $page = $this->admin->page(self::START_PAGE, ['num' => 1, 'pos' => 1]);

        preg_match_all("/name='ids\\[\\]' value='(\\d+)'/", $page->body, $matches);
        $this->assertSame(['2'], $matches[1]);
    }

    #[Scenario('ADM-03', 'three log records exist', 'the administrator ticks records 1 and 3 and submits "Remove"', 'those two records are deleted and record 2 remains')]
    public function testDeleteSelectedRecords(): void
    {
        $page = $this->admin->page(self::START_PAGE);

        $this->admin->submitForm($page, 'MainForm', ['action' => 'delete', 'ids' => [1, 3]]);

        $this->assertSame([2], $this->logIds());
    }

    #[Scenario('ADM-04', 'three log records exist, two of them with the same address and type', 'the administrator submits "Compact log"', 'duplicates are removed keeping the newest of each address/type pair')]
    public function testCompactTheLog(): void
    {
        $page = $this->admin->page(self::START_PAGE);

        $this->admin->submitForm($page, 'MainForm', ['action' => 'compactlog']);

        $this->assertSame([2, 3], $this->logIds());
    }

    #[Scenario('ADM-05', 'three log records exist', 'the administrator submits "Remove all"', 'the log is empty')]
    public function testDeleteAllRecords(): void
    {
        $page = $this->admin->page(self::START_PAGE);

        $this->admin->submitForm($page, 'MainForm', ['action' => 'deleteall']);

        $this->assertSame([], $this->logIds());
    }

    #[Scenario('ADM-06', 'three log records exist', 'a delete-all form is submitted without its security ticket', 'nothing is deleted')]
    public function testSubmissionWithoutTicketChangesNothing(): void
    {
        $page = $this->admin->page(self::START_PAGE);
        $form = AdminSession::extractForm($page->body, 'MainForm');
        unset($form['fields']['XOOPS_G_TICKET']);
        $form['fields']['action'] = 'deleteall';

        $this->admin->client()->follow($this->admin->client()->post($page->url, $form['fields']));

        $this->assertSame([1, 2, 3], $this->logIds());
    }

    #[Scenario('ADM-07', 'three log records exist', 'a delete-all form is submitted with an invalid ticket', 'a "GTicket Error" page offering to repost is shown and nothing is deleted')]
    public function testSubmissionWithInvalidTicketIsRefused(): void
    {
        $page = $this->admin->page(self::START_PAGE);
        $form = AdminSession::extractForm($page->body, 'MainForm');
        $form['fields']['XOOPS_G_TICKET'] = 'not-a-valid-ticket';
        $form['fields']['action'] = 'deleteall';

        $response = $this->admin->client()->follow($this->admin->client()->post($page->url, $form['fields']));

        $this->assertStringContainsString('GTicket Error', $response->body);
        $this->assertSame([1, 2, 3], $this->logIds());
    }

    #[Scenario('ADM-08', 'three log records exist', 'a guest opens the admin start page', 'access is refused with "only admin can access this area"')]
    public function testGuestsCannotOpenTheAdminPage(): void
    {
        $response = $this->client()->get(self::START_PAGE);

        $this->assertStringContainsString('only admin can access this area', $response->body);
        $this->assertStringNotContainsString('TESTA', $response->body);
    }

    #[Scenario('ADM-09', 'default preferences', 'the administrator opens the advisory page', 'it lists the security advisories (trust path, allow_url_fopen, session.use_trans_sid, database prefix, mainfile and database layer patches) and the two attack-simulation links')]
    public function testAdvisoryPage(): void
    {
        $page = $this->admin->page(self::START_PAGE, ['page' => 'advisory']);
        $text = $this->visibleText($page);

        $this->assertSame(200, $page->status);
        $this->assertStringContainsString("ICMS_TRUST_PATH", $text);
        $this->assertStringContainsString('allow_url_fopen', $text);
        $this->assertStringContainsString('session.use_trans_sid', $text);
        $this->assertStringContainsString('XOOPS_DB_PREFIX', $text);
        $this->assertStringContainsString('mainfile.php', $text);
        $this->assertStringContainsString('databasefactory.php', $text);
        $this->assertStringContainsString('index.php?xoopsConfig%5Bnocommon%5D=1', $page->body);
        $this->assertStringContainsString('index.php?cid=%2Cpassword+%2F%2A', $page->body);
    }

    #[Scenario('ADM-10', 'default preferences', 'the administrator opens the module\'s admin pages', 'the admin menu offers the start page, the advisory page and the preferences page')]
    public function testAdminMenu(): void
    {
        $page = $this->admin->page(self::START_PAGE);

        $this->assertStringContainsString('admin/index.php', $page->body);
        $this->assertStringContainsString('page=advisory', $page->body);
        $this->assertStringContainsString('fct=preferences', $page->body);
    }

    #[Scenario('ADM-11', 'default preferences', 'the administrator opens the module\'s preferences in the control panel', 'the page lists the module\'s settings (by their labels)')]
    public function testPreferencesPageListsTheSettings(): void
    {
        $modules = self::config()->pdo(self::config()->get('DB_NAME'))
            ->query('SELECT mid FROM `' . self::config()->get('DB_PREFIX') . "_modules` WHERE dirname = 'protector'")->fetchColumn();

        $page = $this->admin->page('/modules/system/admin.php', ['fct' => 'preferences', 'op' => 'showmod', 'mod' => $modules]);
        $text = $this->visibleText($page);

        foreach (['Temporary disabled', 'Reliable IPs', 'Logging level', 'Anti Brute Force', 'Bad counts for F5 Attack'] as $label) {
            $this->assertStringContainsString($label, $text, "Missing preference label: {$label}");
        }

        $this->assertGreaterThanOrEqual(30, preg_match_all('/name=[\'"]conf_ids\[\][\'"]/', $page->body));
    }

    private function seedLog(): void
    {
        $prefix = self::config()->get('DB_PREFIX');
        self::config()->pdo(self::config()->get('DB_NAME'))->exec(
            "INSERT INTO `{$prefix}_protector_log` (uid, ip, type, agent, description, `timestamp`) VALUES "
            . "(0, '10.0.0.1', 'TESTA', 'UA-one/1.0', 'first', NOW()),"
            . "(0, '10.0.0.1', 'TESTA', 'UA-one/1.0', 'dup', NOW()),"
            . "(0, '10.0.0.2', 'TESTB', 'UA-two/1.0', 'third <b>', NOW())",
        );
    }

    /**
     * @return list<int>
     */
    private function logIds(): array
    {
        $prefix = self::config()->get('DB_PREFIX');

        return array_map('intval', self::config()->pdo(self::config()->get('DB_NAME'))
            ->query("SELECT lid FROM `{$prefix}_protector_log` ORDER BY lid")->fetchAll(\PDO::FETCH_COLUMN));
    }
}
