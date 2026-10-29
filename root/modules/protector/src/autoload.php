<?php

/*
 * Autoloading for ImpressCMS\Module\Protector\ -> this directory.
 *
 * Protector has to run in the preload stage, before the core has registered the classes of any module, so the module
 * resolves its own prefix. It is the one in the module's composer.json.
 *
 * The global class names that third-party filters and the core's SQL hook (XOOPS_DB_ALTERNATIVE) still use are
 * resolved here too; they are aliases declared by the namespaced class files.
 */
if (!defined('PROTECTOR_AUTOLOAD_REGISTERED')) {
	define('PROTECTOR_AUTOLOAD_REGISTERED', true);

	spl_autoload_register(static function (string $class): void {
		static $legacy = array(
			'protector' => 'Legacy\\ProtectorFacade',
			'protectorfilterabstract' => 'Filter\\FilterAbstract',
			'protectorfilterhandler' => 'Filter\\FilterHandler',
			'protectormysqldatabase' => 'Database\\SqlInjectionGuard',
		);

		$prefix = 'ImpressCMS\\Module\\Protector\\';

		if (isset($legacy[strtolower($class)])) {
			$relative = $legacy[strtolower($class)];
		} elseif (strncmp($class, $prefix, strlen($prefix)) === 0) {
			$relative = (string) substr($class, strlen($prefix));
		} else {
			return;
		}

		$file = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';

		if (is_file($file)) {
			require_once $file;
		}
	});
}
