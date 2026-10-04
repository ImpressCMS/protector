<?php
// Test-only fixture. Runs one query built from ?q= either unsafely (value pasted into the SQL) or safely
// (value escaped inside quotes) to exercise Protector's database-layer trap.
require __DIR__ . '/mainfile.php';

header('Content-Type: application/json');

$database = icms::$xoopsDB;
$value = (string) ($_GET['q'] ?? '');
$table = $database->prefix('users');

$sql = ($_GET['mode'] ?? 'unsafe') === 'safe'
    ? "SELECT uid FROM {$table} WHERE uname = '" . $database->escape($value) . "'"
    : "SELECT uid FROM {$table} WHERE uid = {$value}";

$result = $database->query($sql);

echo json_encode([
    'executed' => $result !== false,
    'sql_seen_by_page' => $sql,
    'rows' => $result ? $database->getRowsNum($result) : null,
]);
