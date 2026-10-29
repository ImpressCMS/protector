<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Http;

final class ExitResponder implements Responder
{
    /**
     * @return never
     */
    public function halt(string $message = '')
    {
        if ($message === '') {
            exit();
        }

        exit($message);
    }
}
