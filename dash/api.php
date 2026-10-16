<?php
require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!hash_equals(VIEW_KEY, (string)($_GET['key'] ?? ''))) {
    http_response_code(403);
    exit('{"error":"forbidden"}');
}

try {
    $values = [];
    foreach (db()->query('SELECT k, v, txt, ts FROM metrics') as $r) {
        $values[$r['k']] = [
            'v'  => $r['v'] !== null ? (float)$r['v'] : $r['txt'],
            'ts' => (int)$r['ts'],
        ];
    }

    echo json_encode([
        'now'    => time(),
        'cfg'    => md5(json_encode(LAYOUT)),
        'layout' => LAYOUT,
        'values' => (object)$values,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('api.php: ' . $e->getMessage());
    http_response_code(500);
    echo '{"error":"db"}';
}
