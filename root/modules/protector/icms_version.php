<?php

$modversion = array(
	'name' => _MI_PROTECTOR_NAME,
	'description' => _MI_PROTECTOR_DESC,
	'version' => file_get_contents(__DIR__ . '/include/version.txt'),
	'status' => 'RC',
	'credits' => "PEAK Corp.",
	'author' => "GIJ=CHECKMATE<br />PEAK Corp.(http://www.peak.ne.jp/)",
	'help' => "",
	'license' => "GPL",
	'official' => 0,
	'image' => file_exists(__DIR__ . '/module_icon.png') ? 'module_icon.png' : 'module_icon.php',
	'iconbig' => 'module_icon.php?file=iconbig',
	'iconsmall' => 'module_icon.php?file=iconsmall',
	'dirname' => 'protector');

// Tables are created from this file and dropped on uninstall by the core
$modversion['sqlfile'] = array('mysql' => 'sql/mysql.sql');
$modversion['tables'] = array('protector_access', 'protector_log');

// Admin things
$modversion['hasAdmin'] = 1;
$modversion['adminindex'] = "admin/index.php";
$modversion['adminmenu'] = "admin/admin_menu.php";

// Templates
$modversion['templates'] = array(
	array(
		'file' => 'protector_admin_index.html',
		'description' => 'Bad IPs, allowed IPs for group 1 and the log of blocked requests',
	),
	array(
		'file' => 'protector_admin_advisory.html',
		'description' => 'Security advisories and attack simulation links',
	),
);

// Blocks
$modversion['blocks'] = array();

// Menu
$modversion['hasMain'] = 0;

// Search
$modversion['hasSearch'] = false;

// Comments and notification
$modversion['hasComments'] = 0;
$modversion['hasNotification'] = 0;

// Preferences
$modversion['config'] = require __DIR__ . '/config/preferences.php';

// onInstall, onUpdate, onUninstall
$modversion['onInstall'] = 'include/oninstall.php';
$modversion['onUpdate'] = 'include/onupdate.php';
$modversion['onUninstall'] = 'include/onuninstall.php';
