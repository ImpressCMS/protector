<?php

/**
 * Initiating Protector module
 *
 * This file is responsible for initiating the Protector module so no hacks on mainfile are required.
 * The module's install script copies it to ICMS_PRELOAD_PATH when the core has not shipped its own copy.
 *
 * @copyright	The ImpressCMS Project http://www.impresscms.org/
 * @license		http://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU General Public License (GPL)
 * @package		libraries
 * @since		1.1
 * @author		marcan <marcan@impresscms.org>
 */
class IcmsPreloadProtector extends \Icms\Preload\Item {

	function eventStartCoreBoot() {
		$filename = ICMS_ROOT_PATH . '/modules/protector/include/precheck.inc.php';
		if (file_exists($filename)) {
			include $filename;
		}
	}

	function eventFinishCoreBoot() {
		$filename = ICMS_ROOT_PATH . '/modules/protector/include/postcheck.inc.php';
		if (file_exists($filename)) {
			include $filename;
		}
	}
}
