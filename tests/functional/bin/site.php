<?php

declare(strict_types=1);

use ImpressCMS\Module\Protector\Tests\Functional\Config;
use ImpressCMS\Module\Protector\Tests\Functional\Layout;
use ImpressCMS\Module\Protector\Tests\Functional\Site\Site;

$autoload = dirname(__DIR__, 3) . '/vendor/autoload.php';

if (!is_file($autoload)) {
    fwrite(STDERR, "Run 'composer install' in the repository root first.\n");
    exit(1);
}

require $autoload;

$command = $argv[1] ?? 'help';
$config = Config::load();
$site = new Site($config, new Layout($config));
$say = static function (string $message): void {
    fwrite(STDOUT, '[' . date('H:i:s') . "] {$message}\n");
};

switch ($command) {
    case 'pristine':
        $site->pristine();
        $say('site reset to pristine (uninstalled) state');
        break;

    case 'install':
        $site->install($say);
        break;

    case 'snapshot':
        $site->snapshot($say);
        break;

    case 'restore':
        $applied = $site->restore();
        $say('site restored from snapshot, profile: ' . $config->get('PROFILE'));

        foreach ($applied as $patch) {
            $say("  patched: {$patch}");
        }

        break;

    default:
        fwrite(STDOUT, "Usage: php tests/functional/bin/site.php pristine|install|snapshot|restore\n");
        exit($command === 'help' ? 0 : 1);
}
