<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Admin;

interface CsrfTokens
{
    public function field(): string;

    public function isValid(): bool;
}
