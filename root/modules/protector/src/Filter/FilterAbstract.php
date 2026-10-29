<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Filter;

use ImpressCMS\Module\Protector\Legacy\ProtectorFacade;

class FilterAbstract
{
    public ?ProtectorFacade $protector = null;

    public function __construct()
    {
        $this->protector = ProtectorFacade::getInstance();
        $language = empty($GLOBALS['icmsConfig']['language']) ? ($this->protector->getConf()['default_lang'] ?? '') : $GLOBALS['icmsConfig']['language'];
        $languageDirectory = dirname(__DIR__, 2) . '/language';

        @include_once "{$languageDirectory}/{$language}/main.php";

        if (!defined('_MD_PROTECTOR_YOUAREBADIP')) {
            include_once "{$languageDirectory}/english/main.php";
        }
    }

    public function isMobile(): bool
    {
        if (class_exists('Wizin_User')) {
            return \Wizin_User::getSingleton()->bIsMobile;
        }

        return defined('HYP_K_TAI_RENDER') && HYP_K_TAI_RENDER;
    }
}

class_alias(FilterAbstract::class, 'ProtectorFilterAbstract');
