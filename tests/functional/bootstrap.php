<?php

declare(strict_types=1);

use ImpressCMS\Module\Protector\Tests\Functional\Config;
use ImpressCMS\Module\Protector\Tests\Functional\Layout;
use ImpressCMS\Module\Protector\Tests\Functional\Site\Site;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$config = Config::load();
$layout = new Layout($config);
$site = new Site($config, $layout);
$applied = $site->restore();

fwrite(STDERR, '[functional] site restored from snapshot, profile: ' . $config->get('PROFILE') . PHP_EOL);

foreach ($applied as $patch) {
    fwrite(STDERR, "[functional]   patched copy: {$patch}" . PHP_EOL);
}

$GLOBALS['protector_functional'] = ['config' => $config, 'layout' => $layout, 'site' => $site];
