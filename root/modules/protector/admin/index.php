<?php
require '../../../mainfile.php';

$mydirname = basename(dirname(__DIR__));
$mydirpath = dirname(__DIR__);

// environment
$module_handler = icms::handler('icms_module');
$xoopsModule = $module_handler->getByDirname($mydirname);
$config_handler = icms::handler('icms_config');
$xoopsModuleConfig = &$config_handler->getConfigsByCat(0, $xoopsModule->getVar('mid'));

// check permission of 'module_admin' of this module
$moduleperm_handler = icms::handler('icms_member_groupperm');
if (!is_object(@icms::$user) || !$moduleperm_handler->checkRight('module_admin', $xoopsModule->getVar('mid'), icms::$user->getGroups())) die('only admin can access this area');

$xoopsOption['pagetype'] = 'admin';
require ICMS_ROOT_PATH . '/include/cp_functions.php';

icms_loadLanguageFile($mydirname, 'admin');
icms_loadLanguageFile($mydirname, 'main');

// fork each pages of this module
$page = preg_replace('/[^a-zA-Z0-9_-]/', '', @$_GET['page']);

if (file_exists(__DIR__ . "/pages/$page.php")) {
	include __DIR__ . "/pages/$page.php";
} else if (file_exists(__DIR__ . '/pages/index.php')) {
	include __DIR__ . '/pages/index.php';
} else {
	die('wrong request');
}
