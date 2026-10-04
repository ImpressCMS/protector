<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/root/modules/protector/src/autoload.php';
require_once __DIR__ . '/src/UnitTestCase.php';

define('ICMS_URL', 'http://example.test');
define('ICMS_ROOT_PATH', sys_get_temp_dir());
