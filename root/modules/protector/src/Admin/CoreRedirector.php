<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Admin;

final class CoreRedirector implements Redirector
{
    private const DELAY_SECONDS = 2;

    /**
     * @return never
     */
    public function redirect(string $url, string $message)
    {
        redirect_header($url, self::DELAY_SECONDS, $message);

        exit();
    }
}
