<?php
$mydirname = basename(__DIR__);

eval(' function xoops_module_update_' . $mydirname . '( $module ) { return protector_onupdate_base( $module , "' . $mydirname . '" ) ; } ');

if (!function_exists('protector_onupdate_base')) {

	function protector_onupdate_base($module, $mydirname) {
		// transations on module update
		global $msgs; // TODO :-D

		if (!is_array($msgs)) $msgs = array ();

		$db = \Icms\Db\Factory::instance();
		$mid = $module->getVar('mid');

		// TABLES (write here ALTER TABLE etc. if necessary)

		// configs (Though I know it is not a recommended way...)
		$check_sql = "SHOW COLUMNS FROM " . $db->prefix("config") . " LIKE 'conf_title'";
		if (($result = $db->query($check_sql)) && ($myrow = $db->fetchArray($result)) && @$myrow['Type'] == 'varchar(30)') {
			$db->queryF("ALTER TABLE " . $db->prefix("config") . " MODIFY `conf_title` varchar(255) NOT NULL default '', MODIFY `conf_desc` varchar(255) NOT NULL default ''");
		}
		list(, $create_string) = $db->fetchRow($db->query("SHOW CREATE TABLE " . $db->prefix("config")));
		foreach (explode('KEY', $create_string) as $line) {
			if (preg_match('/(\`conf\_title_\d+\`) \(\`conf\_title\`\)/', $line, $regs)) {
				$db->query("ALTER TABLE " . $db->prefix("config") . " DROP KEY " . $regs[1]);
			}
		}
		$db->query("ALTER TABLE " . $db->prefix("config") . " ADD KEY `conf_title` (`conf_title`)");

		// 2.x -> 3.0
		list(, $create_string) = $db->fetchRow($db->query("SHOW CREATE TABLE " . $db->prefix($mydirname . "_log")));
		if (preg_match('/timestamp\(/i', $create_string)) {
			$db->query("ALTER TABLE " . $db->prefix($mydirname . "_log") . " MODIFY `timestamp` DATETIME");
		}

		if ((defined('ICMS_PRELOAD_PATH') && !file_exists(ICMS_PRELOAD_PATH . '/protector.php')) && (!defined('PROTECTOR_POSTCHECK_INCLUDED') || !defined('PROTECTOR_PRECHECK_INCLUDED')) && function_exists('icms_copyr')) {
			\Icms\Core\Filesystem::copyRecursive(__DIR__ . '/preload/protector.php', ICMS_PRELOAD_PATH . '/protector.php');
		}

		// Remove the prefix_manager page - no longer relevant, especially in this module
		\Icms\Core\Filesystem::deleteFile(ICMS_TRUST_PATH . '/modules/protector/admin/prefix_manager.php');

		\Icms\View\Tpl::template_clear_module_cache($mid);

		return true;
	}

	function protector_message_append_onupdate(&$module_obj, &$log) {
		if (is_array(@$GLOBALS['msgs'])) {
			foreach ($GLOBALS['msgs'] as $message) {
				$log->add(strip_tags($message));
			}
		}
	}
}
