<?php

require_once dirname(__DIR__) . '/src/autoload.php';

function icms_module_update_protector($module, $previousVersion = null, $previousDatabaseVersion = null)
{
    return \ImpressCMS\Module\Protector\Install\Updater::forCurrentSite()->update() ?: true;
}
