<?php
// --- Security headers ---
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

// --- Read & sanitize input ---
$kw = isset($_GET['kw']) ? trim($_GET['kw']) : '';
$kw = mb_substr($kw, 0, 120, 'UTF-8');                                   // max 120 znakova
$kw = preg_replace('/[^\p{L}\p{N}\s\+\-\(\),\.]/u', '', $kw);            // dozvoli slova, brojeve, razmak, + - ( ) , .
if ($kw === '') { $kw = 'Braces'; }                                      // fallback

$ts = isset($_GET['ts']) ? preg_replace('/[^0-9]/', '', $_GET['ts']) : '';
$ts = mb_substr($ts, 0, 16, 'UTF-8');                                    // kratko i čisto

// --- Client info (ip/ua) ---
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
$ua = mb_substr($ua, 0, 300, 'UTF-8');                                   // limit dužine
$ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? ($_SERVER['REMOTE_ADDR'] ?? '');
$ip = explode(',', $ip)[0];
$ip = trim($ip);

// --- Ensure logs dir & append CSV ---
$logDir  = __DIR__ . '/logs';
if (!is_dir($logDir)) { @mkdir($logDir, 0755, true); }
$logFile = $logDir . '/clicks_' . date('Y-m-d') . '.csv';

if (!file_exists($logFile)) {
  @file_put_contents($logFile, "\"time\",\"ip\",\"ua\",\"kw\",\"ts\"" . PHP_EOL, FILE_APPEND);
}

$csv = [
  date('c'),
  $ip,
  $ua,
  $kw,
  $ts
];
$csv = array_map(function($v){ return '"'.str_replace('"','""',$v).'"'; }, $csv);
@file_put_contents($logFile, implode(',', $csv) . PHP_EOL, FILE_APPEND);

// --- Redirect target (za sada Google; kasnije feed mapping) ---
$target = 'https://www.google.com/search?q=' . urlencode($kw);
header('Location: ' . $target, true, 302);
exit;
