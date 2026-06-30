<?php
/**
 * track.php — A/B test event collector
 * Called via navigator.sendBeacon() from both v1 and v2.
 */
$allowed_events   = ['pageview', 'contact', 'exit'];
$allowed_versions = ['a', 'b'];

$event   = $_POST['event']   ?? $_GET['event']   ?? '';
$version = $_POST['v']       ?? $_GET['v']       ?? '';
$time    = (int)($_POST['time'] ?? $_GET['time'] ?? 0);

if (!in_array($event, $allowed_events, true) || !in_array($version, $allowed_versions, true)) {
    http_response_code(400);
    exit;
}

$log_dir  = __DIR__ . '/ab_logs';
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0750, true);
    file_put_contents($log_dir . '/.htaccess', "Deny from all\n");
}

$log_file = $log_dir . '/events.csv';

if (!file_exists($log_file)) {
    file_put_contents($log_file, "date,version,event,time_sec,ip_hash\n");
}

$row = implode(',', [
    date('Y-m-d H:i:s'),
    $version,
    $event,
    $time > 0 ? $time : '',
    hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . 'danny-ab-salt'),
]) . "\n";

file_put_contents($log_file, $row, FILE_APPEND | LOCK_EX);
http_response_code(204);
