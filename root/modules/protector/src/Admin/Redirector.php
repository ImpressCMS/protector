<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Admin;

interface Redirector
{
    /**
     * @return never
     */
    public function redirect(string $url, string $message);
}
