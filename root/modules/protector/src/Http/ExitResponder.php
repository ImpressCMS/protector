<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Http;

final class ExitResponder implements Responder
{
    public function halt(string $message = ''): never
    {
        if ($message === '') {
            exit();
        }

        exit($message);
    }
}
