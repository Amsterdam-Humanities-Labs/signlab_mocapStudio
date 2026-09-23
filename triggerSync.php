<?php
/**
 * Same-origin proxy for the Manual Sync button on capture.html.
 *
 * Forwards the request to the viconSync control server on 127.0.0.1:8765.
 */

header('Content-Type: application/json');

$action = strtolower($_GET['action'] ?? 'trigger');
if (!in_array($action, ['trigger', 'status'], true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => "unknown action '$action'"]);
    exit;
}

$url = 'http://127.0.0.1:8765/' . $action;
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
if ($action === 'trigger') {
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, '');
}
$body = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err  = curl_error($ch);
curl_close($ch);

if ($body === false || $code === 0) {
    http_response_code(502);
    echo json_encode([
        'ok' => false,
        'error' => 'sync service unreachable on 127.0.0.1:8765',
        'curl_error' => $err,
    ]);
    exit;
}

http_response_code($code);
echo $body;
