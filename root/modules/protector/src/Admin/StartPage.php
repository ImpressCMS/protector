<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Admin;

use ImpressCMS\Module\Protector\Ban\BanList;
use ImpressCMS\Module\Protector\Ban\GroupOneIpList;
use ImpressCMS\Module\Protector\Log\LogRepository;

final class StartPage
{
    /**
     * @readonly
     */
    private CsrfTokens $tokens;
    /**
     * @readonly
     */
    private Redirector $redirector;
    /**
     * @readonly
     */
    private LogRepository $log;
    /**
     * @readonly
     */
    private BanList $banList;
    /**
     * @readonly
     */
    private GroupOneIpList $groupOneIps;
    /**
     * @readonly
     */
    private IpListParser $parser;
    /**
     * @var array{ipsUpdated: string, badIpsCannotOpen: string, groupOneCannotOpen: string, removed: string, invalidToken: string, guests: string}
     * @readonly
     */
    private array $messages;
    /**
     * @var \Closure(int): string
     * @readonly
     */
    private \Closure $formatTime;
    /**
     * @var \Closure(int, int, int): string
     * @readonly
     */
    private \Closure $navigation;
    /**
     * @readonly
     */
    private string $homeUrl;
    /**
     * @readonly
     */
    private string $dataDirectory;
    /**
     * @readonly
     */
    private string $trustPath;
    private const PAGE_SIZES = [20, 100, 500, 2000];

    private const DEFAULT_PAGE_SIZE = 20;

    /**
     * @param array{
     *     ipsUpdated: string,
     *     badIpsCannotOpen: string,
     *     groupOneCannotOpen: string,
     *     removed: string,
     *     invalidToken: string,
     *     guests: string
     * } $messages
     * @param \Closure(int): string $formatTime
     * @param \Closure(int, int, int): string $navigation
     */
    public function __construct(CsrfTokens $tokens, Redirector $redirector, LogRepository $log, BanList $banList, GroupOneIpList $groupOneIps, IpListParser $parser, array $messages, \Closure $formatTime, \Closure $navigation, string $homeUrl, string $dataDirectory, string $trustPath)
    {
        $this->tokens = $tokens;
        $this->redirector = $redirector;
        $this->log = $log;
        $this->banList = $banList;
        $this->groupOneIps = $groupOneIps;
        $this->parser = $parser;
        $this->messages = $messages;
        $this->formatTime = $formatTime;
        $this->navigation = $navigation;
        $this->homeUrl = $homeUrl;
        $this->dataDirectory = $dataDirectory;
        $this->trustPath = $trustPath;
    }

    /** @param array<string, mixed> $post */
    public function handle(array $post): void
    {
        $action = (string) ($post['action'] ?? '');

        if ($action === '') {
            return;
        }

        if (!$this->tokens->isValid()) {
            $this->redirector->redirect($this->homeUrl, $this->messages['invalidToken']);
        }

        switch ($action) {
            case 'update_ips':
                $this->updateIpLists($post);
                break;
            case 'delete':
                $this->deleteRecords($post);
                break;
            case 'deleteall':
                $this->deleteAllRecords();
                break;
            case 'compactlog':
                $this->compactLog();
                break;
            default:
                null;
                break;
        }
    }

    /**
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    public function variables(array $query): array
    {
        $offset = max(0, (int) ($query['pos'] ?? 0));
        $size = (int) ($query['num'] ?? 0) >= 1 ? (int) $query['num'] : self::DEFAULT_PAGE_SIZE;
        $total = $this->log->count();
        $navigation = ($this->navigation)($total, $size, $offset);

        return [
            'dataDirectory' => $this->dataDirectory,
            'dataDirectoryWritable' => is_writable($this->dataDirectory),
            'badIps' => $this->parser->formatBadIps($this->banList->entries()),
            'badIpsPath' => $this->shortPath($this->banList->path()),
            'groupOneIps' => $this->parser->formatGroupOneIps($this->groupOneIps->entries()),
            'groupOneIpsPath' => $this->shortPath($this->groupOneIps->path()),
            'pageSizes' => array_map(static fn (int $option): array => ['value' => $option, 'selected' => $option === $size], self::PAGE_SIZES),
            'navigation' => $navigation,
            'rows' => $this->rows($offset, $size),
            'tokenForConfig' => $this->tokens->field(),
            'tokenForLog' => $this->tokens->field(),
        ];
    }

    /**
     * @param array<string, mixed> $post
     * @return never
     */
    private function updateIpLists(array $post)
    {
        $problems = '';

        if (!$this->banList->write($this->parser->parseBadIps((string) ($post['bad_ips'] ?? '')))) {
            $problems .= $this->messages['badIpsCannotOpen'];
        }

        if (!$this->groupOneIps->write($this->parser->parseGroupOneIps((string) ($post['group1_ips'] ?? '')))) {
            $problems .= $this->messages['groupOneCannotOpen'];
        }

        $this->redirector->redirect('index.php', $problems === '' ? $this->messages['ipsUpdated'] : $problems);
    }

    /** @param array<string, mixed> $post */
    private function deleteRecords(array $post): void
    {
        if (!isset($post['ids']) || !is_array($post['ids'])) {
            return;
        }

        $this->log->delete($post['ids']);
        $this->redirector->redirect('index.php', $this->messages['removed']);
    }

    /**
     * @return never
     */
    private function deleteAllRecords()
    {
        $this->log->deleteAll();
        $this->redirector->redirect('index.php', $this->messages['removed']);
    }

    /**
     * @return never
     */
    private function compactLog()
    {
        $this->log->compact();
        $this->redirector->redirect('index.php', $this->messages['removed']);
    }

    /** @return list<array<string, mixed>> */
    private function rows(int $offset, int $size): array
    {
        $rows = [];

        foreach ($this->log->page($offset, $size) as $position => $entry) {
            $short = UserAgentLabel::shorten($entry->agent);

            $rows[] = [
                'rowClass' => $position % 2 === 0 ? 'even' : 'odd',
                'id' => $entry->id,
                'time' => ($this->formatTime)($entry->timestamp),
                'user' => $entry->uid ? (string) $entry->userName : $this->messages['guests'],
                'ip' => $entry->ip,
                'agent' => $entry->agent,
                'agentShort' => $short,
                'agentIsShortened' => $short !== $entry->agent,
                'type' => $entry->type,
                'description' => $entry->description,
            ];
        }

        return $rows;
    }

    private function shortPath(string $path): string
    {
        return str_replace($this->trustPath, 'TRUSTPATH', $path);
    }
}
