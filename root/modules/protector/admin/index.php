<?php

use ImpressCMS\Module\Protector\Admin\AdminApplication;
use ImpressCMS\Module\Protector\Kernel;

require '../../../mainfile.php';
require_once dirname(__DIR__) . '/src/autoload.php';

$moduleDirectory = basename(dirname(__DIR__));

$xoopsModule = icms::handler('icms_module')->getByDirname($moduleDirectory);

$permissions = icms::handler('icms_member_groupperm');
if (!is_object(icms::$user) || !$permissions->checkRight('module_admin', $xoopsModule->getVar('mid'), icms::$user->getGroups())) {
	die('only admin can access this area');
}

$xoopsOption['pagetype'] = 'admin';
require ICMS_ROOT_PATH . '/include/cp_functions.php';

icms_loadLanguageFile($moduleDirectory, 'admin');
icms_loadLanguageFile($moduleDirectory, 'main');

$application = AdminApplication::create(Kernel::boot());
$application->handle($_POST);

$page = $application->page(preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($_GET['page'] ?? '')), $_GET);

icms_cp_header();

$rightToLeft = defined('_ADM_USE_RTL') && _ADM_USE_RTL == 1;

$icmsAdminTpl->assign([
	'moduleName' => $xoopsModule->getVar('name'),
	'alignLeft' => $rightToLeft ? 'right' : 'left',
	'alignRight' => $rightToLeft ? 'left' : 'right',
	'imagesUrl' => ICMS_URL . '/modules/' . $moduleDirectory . '/images',
	'configsNotWritable' => sprintf(_AM_FMT_CONFIGSNOTWRITABLE, Kernel::boot()->paths()->directory()),
]);
$icmsAdminTpl->assign($page['variables']);
$icmsAdminTpl->display($page['template']);

icms_cp_footer();
