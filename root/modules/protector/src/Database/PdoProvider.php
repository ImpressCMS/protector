<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Database;

interface PdoProvider
{
    public function connection(): ?\PDO;
}
