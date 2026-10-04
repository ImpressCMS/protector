<?php
// Test-only fixture. Boots ImpressCMS like a normal front-end page and reports what a page would see
// AFTER the preloads (Protector) have run, so sanitising is observable from outside.
require __DIR__ . '/mainfile.php';

header('Content-Type: application/json');

$files = [];

foreach ($_FILES as $name => $file) {
    $files[$name] = ['name' => $file['name'] ?? null, 'error' => $file['error'] ?? null, 'size' => $file['size'] ?? null];
}

echo json_encode([
    'get' => $_GET,
    'post' => $_POST,
    'request' => $_REQUEST,
    'cookie_names' => array_keys($_COOKIE),
    'files' => $files,
    'remote_addr_server' => $_SERVER['REMOTE_ADDR'] ?? null,
    'remote_addr_filter_input' => filter_input(INPUT_SERVER, 'REMOTE_ADDR', FILTER_VALIDATE_IP) ?: null,
    'constants' => [
        'XOOPS_DB_ALTERNATIVE' => defined('XOOPS_DB_ALTERNATIVE') ? XOOPS_DB_ALTERNATIVE : null,
        'PROTECTOR_ENABLED_ANTI_SQL_INJECTION' => defined('PROTECTOR_ENABLED_ANTI_SQL_INJECTION'),
        'PROTECTOR_ENABLED_ANTI_XSS' => defined('PROTECTOR_ENABLED_ANTI_XSS'),
        'XOOPS_DB_PROXY' => defined('XOOPS_DB_PROXY'),
    ],
    'protector_loaded' => class_exists('Protector', false),
    'protector_conf_keys' => class_exists('Protector', false) ? count(Protector::getInstance()->getConf()) : null,
    'postcheck_guard_class_exists' => class_exists('Icms\Db\Legacy\icms_db_legacy_Factory'),
    'real_factory_class_exists' => class_exists('Icms\Db\Legacy\Factory'),
    'uid' => is_object(icms::$user) ? (int) icms::$user->getVar('uid') : 0,
    'script' => basename($_SERVER['SCRIPT_NAME'] ?? ''),
], JSON_UNESCAPED_SLASHES);
