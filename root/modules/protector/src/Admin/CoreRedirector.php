<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Admin;

final class CoreRedirector implements Redirector
{
    private const DELAY_SECONDS = 2;

    public function redirect(string $url, string $message): never
    {
        redirect_header($url, self::DELAY_SECONDS, $message);

        exit();
    }
}
