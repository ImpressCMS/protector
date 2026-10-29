<?php

function icms_module_uninstall_protector($module)
{
    require_once dirname(__DIR__) . '/src/autoload.php';

    return \ImpressCMS\Module\Protector\Install\Uninstaller::forCurrentSite()->uninstall() ?: true;
}
