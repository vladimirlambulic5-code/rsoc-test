<?php
// --- Security headers ---
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Lokalni timezone da CSV ide u pravi (današnji) fajl
date_default_timezone_set('Europe/Belgrade');

// --- SOFT GUARD & NOINDEX (non-breaking) ---
// (ovo ne blokira redirect — samo dodaje noindex i loguje sigurnosne signale)
header('X-Robots-Tag: noindex, nofollow', true);
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0', true);

$__rsoc_debug = [
  'time' => date('c'),
  'ip'   => $_SERVER['REMOTE_ADDR'] ?? '',
];

// Referrer check (isti host)
$refHost = '';
$refOk = null;
if (!empty($_SERVER['HTTP_REFERER'])) {
  $refUrl  = parse_url($_SERVER['HTTP_REFERER']);
  $refHost = $refUrl['host'] ?? '';
  $myHost  = $_SERVER['HTTP_HOST'] ?? '';
  $refOk   = (strcasecmp($refHost, $myHost) === 0) ? 1 : 0;
}

// Cookie token check (format 32 hexa)
$cookieToken = $_COOKIE['rsoc_token'] ?? '';
$tokOk = preg_match('/^[a-f0-9]{32}$/', $cookieToken) ? 1 : 0;

// Zapiši rezultat u softguard log (bez uticaja na redirect)
$sgDir = __DIR__ . '/logs';
if (!is_dir($sgDir)) { @mkdir($sgDir, 0755, true); }
$sgFile = $sgDir . '/softguard_' . date('Y-m-d') . '.csv';

$sgFields = [
  'time' => $__rsoc_debug['time'],
  'ip'   => $__rsoc_debug['ip'],
  'ref_host' => $refHost,
  'ref_ok'   => is_null($refOk) ? '' : $refOk,
  'tok_ok'   => $tokOk,
  'kw'       => $_GET['kw'] ?? '',
  'ts'       => $_GET['ts'] ?? ''
];

$sgLine = '"' . implode('","', array_map(fn($v)=>str_replace('"','""',(string)$v), $sgFields)) . '"' . PHP_EOL;
$sgH = @fopen($sgFile, 'a');
if ($sgH) {
  if (flock($sgH, LOCK_EX)) {
    if (0 === filesize($sgFile)) {
      fwrite($sgH, "\"time\",\"ip\",\"ref_host\",\"ref_ok\",\"tok_ok\",\"kw\",\"ts\"\n");
    }
    fwrite($sgH, $sgLine);
    flock($sgH, LOCK_UN);
  }
  fclose($sgH);
}
// --- kraj SOFT GUARD-a ---


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

// prenesi poznate tracking parametre ako su stigli u naš redirect (non-breaking)
$passthrough = ['src','subid','sub_id','clickid','fbclid','gclid','utm_source','utm_medium','utm_campaign','utm_term','utm_content'];
$u = parse_url($target);
parse_str($u['query'] ?? '', $q);
foreach ($passthrough as $k) {
  if (isset($_GET[$k])) { $q[$k] = $_GET[$k]; }
}
// sastavi nazad URL
$u['query'] = http_build_query($q);
$rebuilt = (isset($u['scheme'])?$u['scheme'].'://':'')
         . ($u['host']??'')
         . ($u['path']??'')
         . ($u['query'] ? '?'.$u['query'] : '')
         . (isset($u['fragment']) ? '#'.$u['fragment'] : '');

header('Location: ' . $rebuilt, true, 302);
exit;
