<?php
require_once __DIR__ . '/db.php';

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');

$in = array_merge($_GET, $_POST);

if (!hash_equals(PUSH_TOKEN, (string)($in['t'] ?? ''))) {
    http_response_code(403);
    exit('forbidden');
}

// Zwei Formen: ?k=pv_power&v=4.2  oder mehrere Werte: ?pv_power=4.2&load_power=0.7
$pairs = [];
if (isset($in['k'], $in['v'])) {
    $pairs[(string)$in['k']] = (string)$in['v'];
}
foreach ($in as $k => $v) {
    if (in_array($k, ['t', 'k', 'v'], true) || !is_scalar($v)) {
        continue;
    }
    $pairs[(string)$k] = (string)$v;
}

if (!$pairs || count($pairs) > 50) {
    http_response_code(400);
    exit('bad request');
}

try {
    $db  = db();
    $now = time();
    $sel = $db->prepare('SELECT ts FROM metrics WHERE k = ?');
    $put = $db->prepare('REPLACE INTO metrics (k, v, txt, ts) VALUES (?, ?, ?, ?)');
    $his = $db->prepare('INSERT INTO history (k, v, ts) VALUES (?, ?, ?)');
    $cnt = 0;

    foreach ($pairs as $k => $raw) {
        if (!preg_match('/^[a-zA-Z0-9_]{1,40}$/', $k)) {
            continue;
        }
        $raw   = trim($raw);
        $norm  = str_replace(',', '.', $raw);
        $isNum = is_numeric($norm);

        $sel->execute([$k]);
        $prev = $sel->fetchColumn();

        $put->execute([
            $k,
            $isNum ? (float)$norm : null,
            $isNum ? null : mb_substr($raw, 0, 100),
            $now,
        ]);

        if ($isNum && ($prev === false || $now - (int)$prev >= HISTORY_INTERVAL)) {
            $his->execute([$k, (float)$norm, $now]);
        }
        $cnt++;
    }

    // Gelegentlich alte History-Einträge entfernen
    if (mt_rand(1, 200) === 1) {
        $db->prepare('DELETE FROM history WHERE ts < ?')->execute([$now - HISTORY_DAYS * 86400]);
    }

    echo 'ok ' . $cnt;
} catch (Throwable $e) {
    error_log('push.php: ' . $e->getMessage());
    http_response_code(500);
    echo 'db error';
}
