<?php

declare(strict_types=1);

/**
 * Runs the functional suite. Options: --profile=enabled|as-is, any other argument is passed on to PHPUnit.
 */
$arguments = array_slice($argv, 1);
$passThrough = [];

foreach ($arguments as $argument) {
    if (str_starts_with($argument, '--profile=')) {
        putenv('PROTECTOR_PROFILE=' . substr($argument, strlen('--profile=')));

        continue;
    }

    $passThrough[] = $argument;
}

$root = dirname(__DIR__, 3);
$command = array_merge(
    [PHP_BINARY, $root . '/vendor/phpunit/phpunit/phpunit', '-c', $root . '/tests/functional/phpunit.xml'],
    $passThrough,
);

$process = proc_open($command, [STDIN, STDOUT, STDERR], $pipes, $root);

exit(is_resource($process) ? proc_close($process) : 1);
