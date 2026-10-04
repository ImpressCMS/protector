<?php

namespace ImpressCMS\Module\Protector\Filter;

use ImpressCMS\Module\Protector\Protector;

// Filter Handler class (singleton)
class FilterHandler {
	var $protector = null;
	var $filters_base = '';
	var $filters_byconfig = '';

	function __construct() {
		$this->protector = &Protector::getInstance();
		$this->filters_base = dirname(__DIR__, 2) . '/filters_enabled';
		$this->filters_byconfig = dirname(__DIR__, 2) . '/filters_byconfig';
	}

	public static function &getInstance() {
		static $instance;
		if (!isset($instance)) {
			$instance = new FilterHandler();
		}
		return $instance;
	}

	// return: false : execute default action
	function execute($type) {
		$ret = 0;

		$filters = array ();

		// parse $protector->_conf['filters']
		foreach (preg_split('/[\s\n,]+/', $this->protector->_conf['filters']) as $file) {
			if (substr($file, -4) != '.php') $file .= '.php';
			if (strncmp($file, $type . '_', strlen($type) + 1) === 0) {
				$filters[] = array (
					'file' => $file,
					'base' => $this->filters_byconfig
				);
			}
		}

		// search from filters_enabled/
		$dh = opendir($this->filters_base);
		while (($file = readdir($dh)) !== false) {
			if (strncmp($file, $type . '_', strlen($type) + 1) === 0) {
				$filters[] = array (
					'file' => $file,
					'base' => $this->filters_base
				);
			}
		}
		closedir($dh);

		// execute the filters
		foreach ($filters as $filter) {
			include_once $filter['base'] . '/' . $filter['file'];
			$plugin_name = 'protector_' . substr($filter['file'], 0, -4);
			if (function_exists($plugin_name)) {
				// old way
				$ret |= call_user_func($plugin_name);
			} else if (class_exists($plugin_name)) {
				// newer way
				$plugin_obj = new $plugin_name();
				$ret |= $plugin_obj->execute();
			}
		}

		return $ret;
	}
}

class_alias(FilterHandler::class, 'ProtectorFilterHandler');
