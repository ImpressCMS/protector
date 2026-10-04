<?php
// The preload shipped with ImpressCMS 2.1 still includes this file from the trust path.
// The code now lives in the module directory; this file only forwards to it.
$protector_postcheck = ICMS_ROOT_PATH . '/modules/protector/include/postcheck.inc.php';
if (file_exists($protector_postcheck)) {
	require $protector_postcheck;
}
unset($protector_postcheck);
