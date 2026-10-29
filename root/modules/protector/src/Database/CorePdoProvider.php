<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Database;

final class CorePdoProvider implements PdoProvider
{
    public function connection(): ?\PDO
    {
        try {
            $connection = \icms_db_Factory::pdoInstance();
        } catch (\Throwable $exception) {
            return null;
        }

        return $connection instanceof \PDO ? $connection : null;
    }
}
