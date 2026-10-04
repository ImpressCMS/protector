<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Session;

use ImpressCMS\Module\Protector\Filter\FilterHandler;
use ImpressCMS\Module\Protector\Http\Responder;

final class SessionPurger
{
    private const EXPIRED = 3600;

    public function __construct(
        private readonly FilterHandler $filters,
        private readonly Responder $responder,
    ) {
    }

    public function purge(bool $redirectToTop = false): void
    {
        $this->clearSessionValues();
        $this->expireCookies();

        if ($redirectToTop) {
            header('Location: ' . ICMS_URL . '/');
            $this->responder->halt();
        }

        $this->filters->run('prepurge_exit', 'Protector detects attacking actions');
    }

    private function clearSessionValues(): void
    {
        if (!isset($_SESSION)) {
            return;
        }

        foreach ($_SESSION as $key => $value) {
            $_SESSION[$key] = '';

            if (isset($GLOBALS[$key])) {
                $GLOBALS[$key] = '';
            }
        }
    }

    private function expireCookies(): void
    {
        if (headers_sent()) {
            return;
        }

        $past = time() - self::EXPIRED;

        setcookie('PHPSESSID', '', $past, '/', '', false);

        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', $past, '/', '', false);
        }

        $path = $this->cookiePath();
        setcookie('autologin_uname', '', $past, $path, '', false);
        setcookie('autologin_pass', '', $past, $path, '', false);
    }

    private function cookiePath(): string
    {
        $path = defined('ICMS_COOKIE_PATH') ? ICMS_COOKIE_PATH : preg_replace('?http://[^/]+(/.*)$?', '$1', ICMS_URL);

        return $path === ICMS_URL ? '/' : $path;
    }
}
