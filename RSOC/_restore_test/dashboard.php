<?php
// RSOC mini dashboard (čita logs/*.csv i prikazuje sumarne i sirove klikove)

// Sigurnosni headere (ne remete ništa, samo “higijena”)
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

$logDir = __DIR__ . '/logs';

// Skupi sve fajlove logs/clicks_YYYY-MM-DD.csv
$files = glob($logDir . '/clicks_*.csv');
if (!$files) {
  echo "<!doctype html><meta charset='utf-8'><title>RSOC Dashboard</title><style>body{font-family:system-ui,Arial,sans-serif;padding:24px;background:#0b0b0b;color:#e9e9e9}</style>";
  echo "<h1>RSOC Dashboard</h1><p>Nema log fajlova još.</p>";
  exit;
}

// Mapiraj datume → putanje i izaberi traženi ili najnoviji
$byDate = [];
foreach ($files as $f) {
  if (preg_match('~/clicks_(\d{4}-\d{2}-\d{2})\.csv$~', $f, $m)) {
    $byDate[$m[1]] = $f;
  }
}
krsort($byDate); // najnoviji prvi

$requested = $_GET['date'] ?? '';
$selectedDate = isset($byDate[$requested]) ? $requested : array_key_first($byDate);
$selectedFile = $byDate[$selectedDate];

// Čitanje CSV-a
$rows = [];
if (($h = fopen($selectedFile, 'r')) !== false) {
  $header = fgetcsv($h); // "time","ip","ua","kw","ts"
  while (($r = fgetcsv($h)) !== false) {
    if (count($r) < 5) continue;
    $rows[] = ['time'=>$r[0], 'ip'=>$r[1], 'ua'=>$r[2], 'kw'=>$r[3], 'ts'=>$r[4]];
  }
  fclose($h);
}

// Statistike
$total = count($rows);
$kwCounts = [];
$ipSet = [];
foreach ($rows as $row) {
  $kwCounts[$row['kw']] = ($kwCounts[$row['kw']] ?? 0) + 1;
  $ipSet[$row['ip']] = true;
}
arsort($kwCounts);
$uniqueKW = count($kwCounts);
$uniqueIP = count($ipSet);

// Helper za HTML escape
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="en">
<meta charset="utf-8">
<title>RSOC Dashboard</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  :root{--bg:#0b0b0b;--fg:#e9e9e9;--card:#161616;--brand:#ec154a;--muted:#a0a0a0;}
  *{box-sizing:border-box} body{margin:0;background:var(--bg);color:var(--fg);font:14px/1.5 system-ui,Arial}
  .wrap{max-width:1100px;margin:32px auto;padding:0 16px}
  .grid{display:grid;grid-template-columns:1fr;gap:16px}
  @media(min-width:900px){.grid{grid-template-columns:1fr 1fr}}
  .card{background:var(--card);border-radius:16px;padding:16px;box-shadow:0 1px 0 rgba(255,255,255,0.04) inset}
  h1{margin:0 0 12px;font-size:24px}
  .row{display:flex;gap:12px;flex-wrap:wrap;align-items:center;margin-bottom:12px}
  select, input{background:#0f0f0f;color:var(--fg);border:1px solid #2a2a2a;border-radius:10px;padding:8px 10px;outline:none}
  table{width:100%;border-collapse:collapse;font-size:13px}
  th,td{padding:8px 10px;border-bottom:1px solid #2a2a2a;vertical-align:top}
  th{text-align:left;color:#cfcfcf}
  .badge{display:inline-block;background:var(--brand);color:#fff;border-radius:999px;padding:2px 8px;font-weight:600}
  .muted{color:var(--muted)}
  .kw{font-weight:600}
</style>

<div class="wrap">
  <h1>RSOC Dashboard</h1>

  <div class="card">
    <div class="row">
      <form method="get">
        <label for="date">Date:&nbsp;</label>
        <select name="date" id="date" onchange="this.form.submit()">
          <?php foreach ($byDate as $d => $_): ?>
            <option value="<?=e($d)?>" <?= $d===$selectedDate ? 'selected':'' ?>><?=e($d)?></option>
          <?php endforeach; ?>
        </select>
      </form>
      <div class="badge">Total: <?=$total?></div>
      <div class="badge">Unique KW: <?=$uniqueKW?></div>
      <div class="badge">Unique IP: <?=$uniqueIP?></div>
      <span class="muted">File: <?=e(basename($selectedFile))?></span>
    </div>
  </div>

  <div class="grid">
    <div class="card">
      <h3>Top keywords</h3>
      <table>
        <thead><tr><th>KW</th><th>Clicks</th></tr></thead>
        <tbody>
        <?php $i=0; foreach ($kwCounts as $kw=>$count): if (++$i>20) break; ?>
          <tr><td class="kw"><?=e($kw)?></td><td><?=$count?></td></tr>
        <?php endforeach; if ($i===0): ?>
          <tr><td colspan="2" class="muted">No data.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>

    <div class="card">
      <h3>Raw clicks (latest first)</h3>
      <div class="row">
        <input id="filter" placeholder="Filter by keyword/IP/UA..." oninput="filterRows()">
      </div>
      <table id="raw">
        <thead><tr><th>Time</th><th>KW</th><th>IP</th><th>User-Agent</th><th>ts</th></tr></thead>
        <tbody>
        <?php
          $slice = array_slice(array_reverse($rows), 0, 200); // najnovijih 200
          foreach ($slice as $r):
        ?>
          <tr>
            <td class="muted"><?=e($r['time'])?></td>
            <td class="kw"><?=e($r['kw'])?></td>
            <td><?=e($r['ip'])?></td>
            <td class="muted"><?=e($r['ua'])?></td>
            <td class="muted"><?=e($r['ts'])?></td>
          </tr>
        <?php endforeach; if (!$slice): ?>
          <tr><td colspan="5" class="muted">No data.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
function filterRows(){
  const q = document.getElementById('filter').value.toLowerCase();
  const rows = document.querySelectorAll('#raw tbody tr');
  rows.forEach(tr=>{
    const t = tr.innerText.toLowerCase();
    tr.style.display = t.includes(q) ? '' : 'none';
  });
}
</script>
