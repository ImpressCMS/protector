<?php

function icms_module_update_protector($module, $previousVersion = null, $previousDatabaseVersion = null)
{
    require_once dirname(__DIR__) . '/src/autoload.php';

    return \ImpressCMS\Module\Protector\Install\Updater::forCurrentSite()->update() ?: true;
}
