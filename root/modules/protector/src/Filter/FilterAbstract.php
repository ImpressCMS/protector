<?php

namespace ImpressCMS\Module\Protector\Filter;

use ImpressCMS\Module\Protector\Protector;

// Abstract of each filter classes
class FilterAbstract {
	var $protector = null;

	function __construct() {
		$this->protector = &Protector::getInstance();
		$lang = empty($GLOBALS['icmsConfig']['language']) ? @$this->protector->_conf['default_lang'] : $GLOBALS['icmsConfig']['language'];
		@include_once dirname(__DIR__, 2) . '/language/' . $lang . '/main.php';
		if (!defined('_MD_PROTECTOR_YOUAREBADIP')) {
			include_once dirname(__DIR__, 2) . '/language/english/main.php';
		}
	}

	function isMobile() {
		if (class_exists('Wizin_User')) {
			// WizMobile (gusagi)
			$user = &\Wizin_User::getSingleton();
			return $user->bIsMobile;
		} else if (defined('HYP_K_TAI_RENDER') && HYP_K_TAI_RENDER) {
			// hyp_common ktai-renderer (nao-pon)
			return true;
		} else {
			return false;
		}
	}
}

class_alias(FilterAbstract::class, 'ProtectorFilterAbstract');
