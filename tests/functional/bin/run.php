<?php

declare(strict_types=1);

/**
 * Runs the functional suite; every argument is passed on to PHPUnit (for example --filter RateLimitTest).
 */
$root = dirname(__DIR__, 3);
$command = array_merge(
    [PHP_BINARY, $root . '/vendor/phpunit/phpunit/phpunit', '-c', $root . '/tests/functional/phpunit.xml'],
    array_slice($argv, 1),
);

$process = proc_open($command, [STDIN, STDOUT, STDERR], $pipes, $root);

exit(is_resource($process) ? proc_close($process) : 1);
