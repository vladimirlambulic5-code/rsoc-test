<?php
// --- Security headers (mali + sigurni dobici) ---
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

// --- Token (koristi postojeći ako je validan, inače generiši novi) ---
$cookieName = 'rsoc_token';
$existing   = $_COOKIE[$cookieName] ?? '';
if (!preg_match('/^[a-f0-9]{32}$/', $existing)) {
  $token = bin2hex(random_bytes(16)); // 32 hexa znaka
} else {
  $token = $existing;
}

// --- Detekcija HTTPS-a (za Secure flag) ---
$secure = (
  (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
  (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
);

// --- Postavi cookie sa sigurnim opcijama ---
setcookie($cookieName, $token, [
  'expires'  => 0,         // session cookie
  'path'     => '/',       // važi na cijelom sajtu
  'secure'   => $secure,   // true samo na HTTPS-u
  'httponly' => true,      // JS ne može da čita (sigurnije)
  'samesite' => 'Lax'      // sprječava većinu cross-site slanja
]);

// --- Vrati 1x1 GIF (ping odgovor) ---
header('Content-Type: image/gif');
echo base64_decode('R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==');
