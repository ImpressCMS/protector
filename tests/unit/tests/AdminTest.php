<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Tests\Unit;

use ImpressCMS\Module\Protector\Admin\CsrfTokens;
use ImpressCMS\Module\Protector\Admin\IpListParser;
use ImpressCMS\Module\Protector\Admin\Redirector;
use ImpressCMS\Module\Protector\Admin\StartPage;
use ImpressCMS\Module\Protector\Admin\UserAgentLabel;
use ImpressCMS\Module\Protector\Ban\BanList;
use ImpressCMS\Module\Protector\Ban\GroupOneIpList;
use ImpressCMS\Module\Protector\Database\PdoProvider;
use ImpressCMS\Module\Protector\Log\LogRepository;

final class AdminTest extends UnitTestCase
{
    private \PDO $database;

    private bool $tokenIsValid = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->database = new \PDO('sqlite::memory:', null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        $this->database->sqliteCreateFunction('UNIX_TIMESTAMP', static fn (string $value): int => (int) strtotime($value));
        $this->database->exec('CREATE TABLE test_protector_log (lid INTEGER PRIMARY KEY AUTOINCREMENT, uid INTEGER, ip TEXT, agent TEXT, type TEXT, description TEXT, `timestamp` TEXT)');
        $this->database->exec('CREATE TABLE test_users (uid INTEGER, uname TEXT)');
        $this->database->exec("INSERT INTO test_users VALUES (1, 'admin')");
    }

    public function testParserKeepsOnlyValidBadIpLines(): void
    {
        $parsed = (new IpListParser())->parseBadIps("10.0.0.1\n10.0.0.2:1893456000\r\nnot an ip\n\n300.1.1.1.1.1.1.1.1.1\n10.0.0.3 ");

        $this->assertSame(['10.0.0.1' => 0x7fffffff, '10.0.0.2' => 1893456000, '10.0.0.3' => 0x7fffffff], $parsed);
    }

    public function testParserKeepsOnlyValidGroupOneLinesWithoutDuplicates(): void
    {
        $parsed = (new IpListParser())->parseGroupOneIps("192.168.\n10.0.0.1\n10.0.0.1\n<script>\n");

        $this->assertSame(['192.168.', '10.0.0.1'], $parsed);
    }

    public function testParserFormatsListsNumericallyAndHidesTheForeverMarker(): void
    {
        $parser = new IpListParser();

        $this->assertSame("2.0.0.1\n10.0.0.2:1893456000\n10.0.0.10\n", $parser->formatBadIps(['10.0.0.10' => 0x7fffffff, '2.0.0.1' => 0x7fffffff, '10.0.0.2' => 1893456000]));
        $this->assertSame("2.0.0.1\n10.0.0.10", $parser->formatGroupOneIps(['10.0.0.10', '2.0.0.1']));
        $this->assertSame('', $parser->formatBadIps([]));
    }

    public function testUserAgentsAreShortened(): void
    {
        $this->assertSame('IE 8.0', UserAgentLabel::shorten('Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 6.1)'));
        $this->assertSame(' Firefox/120.0', UserAgentLabel::shorten('Mozilla/5.0 (X11; Linux) Gecko/20100101 Firefox/120.0'));
        $this->assertSame('curl/8', UserAgentLabel::shorten('curl/8'));
        $this->assertSame('Opera/9.80', UserAgentLabel::shorten('Opera/9.80 (Windows NT 6.1)'));
        $this->assertSame('', UserAgentLabel::shorten(''));
    }

    public function testRepositoryListsNewestFirstWithUserNamesAndPaging(): void
    {
        $this->insertLog(0, '10.0.0.1', 'A', '2030-01-01 10:00:00');
        $this->insertLog(1, '10.0.0.2', 'B', '2030-01-01 10:00:00');
        $this->insertLog(0, '10.0.0.3', 'C', '2030-01-01 09:00:00');
        $repository = $this->repository();

        $first = $repository->page(0, 2);
        $second = $repository->page(2, 2);

        $this->assertSame(3, $repository->count());
        $this->assertSame(['B', 'A'], array_map(static fn ($entry): string => $entry->type, $first));
        $this->assertSame('admin', $first[0]->userName);
        $this->assertNull($first[1]->userName);
        $this->assertSame(['C'], array_map(static fn ($entry): string => $entry->type, $second));
    }

    public function testRepositoryDeletesSelectedRecordsAndIgnoresGarbageIds(): void
    {
        $this->seedThreeRecords();
        $repository = $this->repository();

        $repository->delete(['1', '3', 'x', '99']);
        $repository->delete([]);

        $this->assertSame([2], $this->logIds());
    }

    public function testRepositoryCompactsDuplicatesKeepingTheNewest(): void
    {
        $this->seedThreeRecords();

        $removed = $this->repository()->compact();

        $this->assertSame(1, $removed);
        $this->assertSame([2, 3], $this->logIds());
    }

    public function testCompactingWithoutDuplicatesChangesNothing(): void
    {
        $this->insertLog(0, '10.0.0.1', 'A', '2030-01-01 10:00:00');

        $this->assertSame(0, $this->repository()->compact());
        $this->assertSame([1], $this->logIds());
    }

    public function testRepositoryDeletesEverything(): void
    {
        $this->seedThreeRecords();

        $this->repository()->deleteAll();

        $this->assertSame([], $this->logIds());
    }

    public function testStartPageRefusesActionsWithoutAValidToken(): void
    {
        $this->seedThreeRecords();
        $this->tokenIsValid = false;

        $this->expectExceptionMessage('redirect:http://home:token refused');
        try {
            $this->startPage()->handle(['action' => 'deleteall']);
        } finally {
            $this->assertSame([1, 2, 3], $this->logIds());
        }
    }

    public function testStartPageDoesNothingWithoutAnAction(): void
    {
        $this->seedThreeRecords();
        $this->tokenIsValid = false;

        $this->startPage()->handle([]);

        $this->assertSame([1, 2, 3], $this->logIds());
    }

    public function testStartPageRunsTheRequestedActionsWithAValidToken(): void
    {
        $this->seedThreeRecords();

        $this->expectExceptionMessage('redirect:index.php:removed');
        try {
            $this->startPage()->handle(['action' => 'delete', 'ids' => [1, 3]]);
        } finally {
            $this->assertSame([2], $this->logIds());
        }
    }

    public function testStartPageIgnoresDeleteWithoutIdsAndUnknownActions(): void
    {
        $this->seedThreeRecords();
        $page = $this->startPage();

        $page->handle(['action' => 'delete']);
        $page->handle(['action' => 'something else']);

        $this->assertSame([1, 2, 3], $this->logIds());
    }

    public function testStartPageSavesBothIpLists(): void
    {
        $this->expectExceptionMessage('redirect:index.php:ips updated');
        try {
            $this->startPage()->handle(['action' => 'update_ips', 'bad_ips' => "10.0.0.9\nbad line", 'group1_ips' => "127.0.0.1\n127.0.0.2"]);
        } finally {
            $this->assertSame(['10.0.0.9'], (new BanList($this->paths()))->addresses());
            $this->assertSame(['127.0.0.1', '127.0.0.2'], (new GroupOneIpList($this->paths()))->entries());
        }
    }

    public function testStartPageVariablesDescribeTheLogAndTheLists(): void
    {
        $this->seedThreeRecords();
        (new BanList($this->paths()))->register('10.0.0.9');

        $variables = $this->startPage()->variables(['num' => '2', 'pos' => '1']);

        $this->assertSame("10.0.0.9\n", $variables['badIps']);
        $this->assertSame('', $variables['groupOneIps']);
        $this->assertCount(2, $variables['rows']);
        $this->assertSame('even', $variables['rows'][0]['rowClass']);
        $this->assertSame('odd', $variables['rows'][1]['rowClass']);
        $this->assertSame('nav:3:2:1', $variables['navigation']);
        $this->assertTrue($variables['dataDirectoryWritable']);
        $this->assertSame([20, 100, 500, 2000], array_column($variables['pageSizes'], 'value'));
        $this->assertSame('field', $variables['tokenForLog']);
    }

    public function testStartPageFallsBackToDefaultsForBadPagingValues(): void
    {
        $this->seedThreeRecords();

        $variables = $this->startPage()->variables(['num' => '-5', 'pos' => '-3']);

        $this->assertSame('nav:3:20:0', $variables['navigation']);
    }

    private function startPage(): StartPage
    {
        $tokens = new class ($this) implements CsrfTokens {
            public function __construct(private readonly AdminTest $test)
            {
            }

            public function field(): string
            {
                return 'field';
            }

            public function isValid(): bool
            {
                return $this->test->tokenIsValid();
            }
        };

        $redirector = new class implements Redirector {
            public function redirect(string $url, string $message): never
            {
                throw new \RuntimeException("redirect:{$url}:{$message}");
            }
        };

        return new StartPage(
            $tokens,
            $redirector,
            $this->repository(),
            new BanList($this->paths()),
            new GroupOneIpList($this->paths()),
            new IpListParser(),
            [
                'ipsUpdated' => 'ips updated',
                'badIpsCannotOpen' => 'bad ips cannot open',
                'groupOneCannotOpen' => 'group one cannot open',
                'removed' => 'removed',
                'invalidToken' => 'token refused',
                'guests' => 'Guests',
            ],
            static fn (int $timestamp): string => date('Y-m-d', $timestamp),
            static fn (int $total, int $size, int $offset): string => "nav:{$total}:{$size}:{$offset}",
            'http://home',
            $this->directory,
            '/trust',
        );
    }

    public function tokenIsValid(): bool
    {
        return $this->tokenIsValid;
    }

    private function repository(): LogRepository
    {
        $database = $this->database;

        return new LogRepository(new class ($database) implements PdoProvider {
            public function __construct(private readonly \PDO $connection)
            {
            }

            public function connection(): ?\PDO
            {
                return $this->connection;
            }
        }, 'test');
    }

    private function seedThreeRecords(): void
    {
        $this->insertLog(0, '10.0.0.1', 'TESTA', '2030-01-01 10:00:00');
        $this->insertLog(0, '10.0.0.1', 'TESTA', '2030-01-01 10:00:00');
        $this->insertLog(0, '10.0.0.2', 'TESTB', '2030-01-01 10:00:00');
    }

    private function insertLog(int $uid, string $ip, string $type, string $moment): void
    {
        $this->database->prepare("INSERT INTO test_protector_log (uid, ip, agent, type, description, `timestamp`) VALUES (?, ?, 'UA', ?, 'd', ?)")
            ->execute([$uid, $ip, $type, $moment]);
    }

    /** @return list<int> */
    private function logIds(): array
    {
        return array_map('intval', $this->database->query('SELECT lid FROM test_protector_log ORDER BY lid')->fetchAll(\PDO::FETCH_COLUMN));
    }
}
