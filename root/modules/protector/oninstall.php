<?php
$mydirname = basename(__DIR__);

eval(' function xoops_module_install_' . $mydirname . '( $module ) { return protector_oninstall_base( $module , "' . $mydirname . '" ) ; } ');

if (!function_exists('protector_oninstall_base')) {

	function protector_oninstall_base($module, $mydirname) {
		// transations on module install
		global $ret; // TODO :-D

		if (!is_array($ret)) $ret = array ();

		$db = \Icms\Db\Factory::instance();
		$mid = $module->getVar('mid');

		// TABLES (loading mysql.sql)
		$sql_file_path = __DIR__ . '/sql/mysql.sql';
		$prefix_mod = $db->prefix() . '_' . $mydirname;
		if (file_exists($sql_file_path)) {
			$ret[] = "SQL file found at <b>" . htmlspecialchars($sql_file_path) . "</b>.<br /> Creating tables...";

			$sqlutil = new \Icms\Db\Legacy\Mysql\Utility();
			$sql_query = trim(file_get_contents($sql_file_path));
			$sqlutil->splitMySqlFile($pieces, $sql_query);
			$created_tables = array ();
			foreach ($pieces as $piece) {
				$prefixed_query = $sqlutil->prefixQuery($piece, $prefix_mod);
				if (!$prefixed_query) {
					$ret[] = "Invalid SQL <b>" . htmlspecialchars($piece) . "</b><br />";
					return false;
				}
				if (!$db->query($prefixed_query[0])) {
					$ret[] = '<b>' . htmlspecialchars($db->error()) . '</b><br />';
					// var_dump( $db->error() ) ;
					return false;
				} else {
					if (!in_array($prefixed_query[4], $created_tables)) {
						$ret[] = 'Table <b>' . htmlspecialchars($prefix_mod . '_' . $prefixed_query[4]) . '</b> created.<br />';
						$created_tables[] = $prefixed_query[4];
					} else {
						$ret[] = 'Data inserted to table <b>' . htmlspecialchars($prefix_mod . '_' . $prefixed_query[4]) . '</b>.</br />';
					}
				}
			}
		}

		/*
		 * Fixes Bug #619 : parse Error
		 */
		if ((defined('ICMS_PRELOAD_PATH') && !file_exists(ICMS_PRELOAD_PATH . '/protector.php')) && (!defined('PROTECTOR_POSTCHECK_INCLUDED') || !defined('PROTECTOR_PRECHECK_INCLUDED'))) {
			if (\Icms\Core\Filesystem::copyRecursive(__DIR__ . '/preload/protector.php', ICMS_PRELOAD_PATH . '/protector.php')) {
				$ret[] = 'Successfully moved protector preload<br />';
			} else {
				$ret[] = \Icms\Core\Message::error("Failed to move protector preload - your site is not protected.", "", FALSE);
			}
		}

		\Icms\View\Tpl::template_clear_module_cache($mid);

		return true;
	}

	function protector_message_append_oninstall(&$module_obj, &$log) {
		if (is_array(@$GLOBALS['ret'])) {
			foreach ($GLOBALS['ret'] as $message) {
				$log->add(strip_tags($message));
			}
		}

		// use mLog->addWarning() or mLog->addError() if necessary
	}
}
