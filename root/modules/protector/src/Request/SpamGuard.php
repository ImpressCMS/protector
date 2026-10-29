<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Request;

use ImpressCMS\Module\Protector\Filter\FilterHandler;
use ImpressCMS\Module\Protector\Http\Responder;
use ImpressCMS\Module\Protector\Http\ServerRequest;
use ImpressCMS\Module\Protector\Log\AuditLog;
use ImpressCMS\Module\Protector\Log\LogLevel;

final class SpamGuard
{
    /**
     * @readonly
     */
    private AuditLog $log;
    /**
     * @readonly
     */
    private FilterHandler $filters;
    /**
     * @readonly
     */
    private Responder $responder;
    public function __construct(AuditLog $log, FilterHandler $filters, Responder $responder)
    {
        $this->log = $log;
        $this->filters = $filters;
        $this->responder = $responder;
    }

    public function check(int $pointsToDeny, int $uid): void
    {
        $points = $this->score($_POST);

        if ($points < $pointsToDeny) {
            return;
        }

        $this->log->note(ServerRequest::uri() . " SPAM POINT: {$points}\n");
        $this->log->write('URI SPAM', $uid, false, LogLevel::SPAM);

        if ($this->filters->execute('spamcheck_overrun') === 0) {
            $this->responder->halt();
        }
    }

    /**
     * @param mixed $value
     */
    public function score($value): int
    {
        if (is_array($value)) {
            return array_sum(array_map(\Closure::fromCallable([$this, 'score']), $value));
        }

        return $this->scoreText((string) $value);
    }

    private function scoreText(string $text): int
    {
        $host = parse_url(ICMS_URL)['host'] ?? 'www.xoops.org';
        $points = 0;

        $links = -1;

        foreach (preg_split('#https?\:\/\/#i', $text) as $fragment) {
            if (strncmp($fragment, $host, strlen($host)) !== 0) {
                $links++;
            }
        }

        if ($links > 0) {
            $points += $links;
        }

        return $points + count(preg_split('/\[url=(?!http|\\"http|\\\'http|' . $host . ')/i', $text)) - 1;
    }
}
