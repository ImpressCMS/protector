<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Admin;

final class CoreCsrfTokens implements CsrfTokens
{
    private const TOKEN_NAME = 'protector_admin';

    public function field(): string
    {
        return \icms::$security->getTokenHTML(self::TOKEN_NAME);
    }

    public function isValid(): bool
    {
        return \icms::$security->check(true, false, self::TOKEN_NAME);
    }
}
