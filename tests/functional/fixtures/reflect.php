<?php
// Test-only fixture. Reflects ?x= into an HTML page without escaping, to exercise Protector's output check.
require __DIR__ . '/mainfile.php';

if (($_GET['type'] ?? 'html') === 'json') {
    header('Content-Type: application/json');
    echo json_encode(['x' => $_GET['x'] ?? '']);

    return;
}

header('Content-Type: text/html; charset=utf-8');
echo '<html><body><div id="reflected">' . ($_GET['x'] ?? '') . '</div></body></html>';
