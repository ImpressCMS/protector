<?php

if (!defined('PROTECTOR_PRECHECK_INCLUDED')) {
	require __DIR__ . '/precheck.inc.php';
	return;
}

define('PROTECTOR_POSTCHECK_INCLUDED', 1);
if (!class_exists('Icms\Db\Legacy\Factory')) return;

\ImpressCMS\Module\Protector\Kernel::boot()->postcheck();
