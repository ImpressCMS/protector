<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Http;

interface Responder
{
    public function halt(string $message = ''): never;
}
