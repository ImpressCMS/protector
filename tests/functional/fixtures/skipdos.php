<?php
// Test-only fixture. A module "controls DoS skipping" by defining this constant before the core boots.
define('PROTECTOR_SKIP_DOS_CHECK', 1);

require __DIR__ . '/probe.php';
