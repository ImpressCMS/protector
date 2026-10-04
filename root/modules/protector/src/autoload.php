<?php

/*
 * PSR-4 autoloader for ImpressCMS\Module\Protector\ -> this directory.
 *
 * The core registers module namespaces only after the preload stage, and Protector has to run in that stage, so the
 * module registers its own prefix. It also resolves the global class names that third-party filters and the core's
 * SQL hook (XOOPS_DB_ALTERNATIVE) still use; those names are aliases declared by the namespaced class files.
 */
if (!defined('PROTECTOR_AUTOLOAD_REGISTERED')) {
	define('PROTECTOR_AUTOLOAD_REGISTERED', true);

	spl_autoload_register(static function (string $class): void {
		static $legacy = array(
			'protector' => 'Protector',
			'protectorfilterabstract' => 'Filter\\FilterAbstract',
			'protectorfilterhandler' => 'Filter\\FilterHandler',
			'protectormysqldatabase' => 'Database\\SqlInjectionGuard',
		);

		$prefix = 'ImpressCMS\\Module\\Protector\\';

		if (isset($legacy[strtolower($class)])) {
			$relative = $legacy[strtolower($class)];
		} elseif (strncmp($class, $prefix, strlen($prefix)) === 0) {
			$relative = substr($class, strlen($prefix));
		} else {
			return;
		}

		$file = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';

		if (is_file($file)) {
			require_once $file;
		}
	});
}
