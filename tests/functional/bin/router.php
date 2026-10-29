<?php

/*
 * Router for PHP's built-in web server, for running the suite against a site that is not served by Herd:
 *
 *   php -S 127.0.0.1:8204 -t <site directory> tests/functional/bin/router.php
 *
 * The built-in server leaves QUERY_STRING out of $_SERVER for requests without a query, and ImpressCMS 2.0 reads it
 * unguarded, which would print a warning in front of every page.
 */
$_SERVER['QUERY_STRING'] ??= '';

return false;
