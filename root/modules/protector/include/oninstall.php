<?php

require_once dirname(__DIR__) . '/src/autoload.php';

function icms_module_install_protector($module)
{
    return \ImpressCMS\Module\Protector\Install\Installer::forCurrentSite()->install() ?: true;
}
