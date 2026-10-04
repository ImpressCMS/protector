<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Admin;

interface Redirector
{
    public function redirect(string $url, string $message): never;
}
