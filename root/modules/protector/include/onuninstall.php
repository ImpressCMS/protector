<?php

require_once dirname(__DIR__) . '/src/autoload.php';

function icms_module_uninstall_protector($module)
{
    return \ImpressCMS\Module\Protector\Install\Uninstaller::forCurrentSite()->uninstall() ?: true;
}
