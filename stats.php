<?php
/**
 * stats.php — Password-protected A/B test results dashboard
 * Password is set via STATS_PASSWORD GitHub secret → ab_logs/stats_password.php
 */
$pw_file = __DIR__ . '/ab_logs/stats_password.php';
$correct = file_exists($pw_file) ? (require $pw_file) : 'changeme';

session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pw'])) {
    if ($_POST['pw'] === $correct) {
        $_SESSION['ab_auth'] = true;
    } else {
        $error = 'Wrong password.';
    }
}

if (!($_SESSION['ab_auth'] ?? false)) {
    ?><!DOCTYPE html>
    <html lang="en"><head><meta charset="UTF-8"><title>A/B Stats Login</title>
    <style>body{font-family:system-ui;display:flex;align-items:center;justify-content:center;min-height:100vh;background:#f4f4f4;margin:0}
    .box{background:#fff;padding:32px 40px;border-radius:8px;box-shadow:0 2px 12px rgba(0,0,0,.1);width:300px}
    h2{margin:0 0 20px;font-size:1.1rem}input{width:100%;padding:9px 12px;border:1px solid #ddd;border-radius:4px;font-size:1rem;box-sizing:border-box;margin-bottom:12px}
    button{width:100%;padding:10px;background:#1a6edb;color:#fff;border:none;border-radius:4px;font-size:1rem;cursor:pointer}
    .err{color:#c0392b;font-size:.9rem;margin-bottom:10px}</style></head>
    <body><div class="box"><h2>A/B Test Dashboard</h2>
    <?php if (!empty($error)): ?><p class="err"><?= htmlspecialchars($error) ?></p><?php endif; ?>
    <form method="post"><input type="password" name="pw" placeholder="Password" autofocus>
    <button type="submit">Sign In</button></form></div></body></html>
    <?php
    exit;
}

// ─── Load events ──────────────────────────────────────────────────────────────
$log_file = __DIR__ . '/ab_logs/events.csv';
$events   = [];

if (file_exists($log_file)) {
    $rows = array_map('str_getcsv', file($log_file));
    $header = array_shift($rows);
    foreach ($rows as $r) {
        if (count($r) >= 4) {
            $events[] = array_combine(['date','version','event','time_sec','ip_hash'], array_pad($r, 5, ''));
        }
    }
}

// ─── Aggregate ────────────────────────────────────────────────────────────────
$stats = ['a' => ['pageviews'=>0,'contacts'=>0,'times'=>[]], 'b' => ['pageviews'=>0,'contacts'=>0,'times'=>[]]];
$unique = ['a' => [], 'b' => []];

foreach ($events as $e) {
    $v = $e['version'];
    if (!isset($stats[$v])) continue;
    $ip = $e['ip_hash'];
    if ($e['event'] === 'pageview') {
        $stats[$v]['pageviews']++;
        $unique[$v][$ip] = true;
    } elseif ($e['event'] === 'contact') {
        $stats[$v]['contacts']++;
    } elseif ($e['event'] === 'exit' && is_numeric($e['time_sec'])) {
        $stats[$v]['times'][] = (int)$e['time_sec'];
    }
}

function rate($contacts, $pageviews) {
    return $pageviews > 0 ? round($contacts / $pageviews * 100, 1) : 0;
}
function avg_time($times) {
    if (empty($times)) return 0;
    $t = array_sum($times) / count($times);
    return round($t);
}
function fmt_time($secs) {
    return $secs >= 60 ? floor($secs/60).'m '.($secs%60).'s' : $secs.'s';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>A/B Test Results — Danny Realtor</title>
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:system-ui,sans-serif;background:#f0f2f5;color:#1a1a2e;min-height:100vh}
  header{background:#1a6edb;color:#fff;padding:20px 32px;display:flex;justify-content:space-between;align-items:center}
  header h1{font-size:1.2rem;font-weight:700}
  header a{color:rgba(255,255,255,.8);font-size:.85rem;text-decoration:none}
  .container{max-width:900px;margin:32px auto;padding:0 20px}
  .grid{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:28px}
  .card{background:#fff;border-radius:10px;padding:24px 28px;box-shadow:0 1px 4px rgba(0,0,0,.08)}
  .card-label{font-size:.72rem;font-weight:600;text-transform:uppercase;letter-spacing:.1em;color:#888;margin-bottom:8px}
  .card h2{font-size:1rem;font-weight:700;margin-bottom:16px;padding-bottom:12px;border-bottom:2px solid #f0f2f5}
  .metric{display:flex;justify-content:space-between;align-items:baseline;padding:8px 0;border-bottom:1px solid #f5f5f5}
  .metric:last-child{border-bottom:none}
  .metric .label{font-size:.9rem;color:#555}
  .metric .val{font-size:1.3rem;font-weight:700;color:#1a1a2e}
  .metric .val.good{color:#16a34a}
  .metric .val.muted{color:#888;font-size:1rem}
  .winner-banner{background:#16a34a;color:#fff;padding:14px 20px;border-radius:8px;text-align:center;font-weight:600;margin-bottom:20px}
  .tie-banner{background:#6b7280;color:#fff;padding:14px 20px;border-radius:8px;text-align:center;font-weight:600;margin-bottom:20px}
  .events-count{text-align:center;color:#888;font-size:.85rem;margin-top:20px}
  .logout{margin-top:28px;text-align:right}
  .logout a{color:#1a6edb;font-size:.85rem;text-decoration:none}
  @media(max-width:600px){.grid{grid-template-columns:1fr}}
</style>
</head>
<body>
<header>
  <h1>A/B Test — danielhernandezhaygood.com</h1>
  <a href="?logout=1">Sign out</a>
</header>

<?php
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: /stats.php');
    exit;
}
?>

<div class="container">

<?php
$a_rate = rate($stats['a']['contacts'], $stats['a']['pageviews']);
$b_rate = rate($stats['b']['contacts'], $stats['b']['pageviews']);
$a_uniq = count($unique['a']);
$b_uniq = count($unique['b']);
$total  = count($events);

if ($stats['a']['pageviews'] > 30 || $stats['b']['pageviews'] > 30):
    if ($a_rate > $b_rate): ?>
      <div class="winner-banner">Version A (Elegant Dark) is leading — <?= $a_rate ?>% vs <?= $b_rate ?>% contact rate</div>
    <?php elseif ($b_rate > $a_rate): ?>
      <div class="winner-banner">Version B (Zillow Style) is leading — <?= $b_rate ?>% vs <?= $a_rate ?>% contact rate</div>
    <?php else: ?>
      <div class="tie-banner">Too close to call — <?= $a_rate ?>% contact rate for both</div>
    <?php endif;
endif; ?>

<div class="grid">
  <!-- Version A -->
  <div class="card">
    <div class="card-label">Version A</div>
    <h2>Elegant Dark Site</h2>
    <div class="metric">
      <span class="label">Page views</span>
      <span class="val"><?= number_format($stats['a']['pageviews']) ?></span>
    </div>
    <div class="metric">
      <span class="label">Unique visitors</span>
      <span class="val"><?= number_format($a_uniq) ?></span>
    </div>
    <div class="metric">
      <span class="label">Contact actions</span>
      <span class="val"><?= number_format($stats['a']['contacts']) ?></span>
    </div>
    <div class="metric">
      <span class="label">Contact rate</span>
      <span class="val <?= $a_rate >= $b_rate ? 'good' : '' ?>"><?= $a_rate ?>%</span>
    </div>
    <div class="metric">
      <span class="label">Avg. time on page</span>
      <span class="val muted"><?= fmt_time(avg_time($stats['a']['times'])) ?></span>
    </div>
  </div>

  <!-- Version B -->
  <div class="card">
    <div class="card-label">Version B</div>
    <h2>Zillow-Style Site</h2>
    <div class="metric">
      <span class="label">Page views</span>
      <span class="val"><?= number_format($stats['b']['pageviews']) ?></span>
    </div>
    <div class="metric">
      <span class="label">Unique visitors</span>
      <span class="val"><?= number_format($b_uniq) ?></span>
    </div>
    <div class="metric">
      <span class="label">Contact actions</span>
      <span class="val"><?= number_format($stats['b']['contacts']) ?></span>
    </div>
    <div class="metric">
      <span class="label">Contact rate</span>
      <span class="val <?= $b_rate >= $a_rate ? 'good' : '' ?>"><?= $b_rate ?>%</span>
    </div>
    <div class="metric">
      <span class="label">Avg. time on page</span>
      <span class="val muted"><?= fmt_time(avg_time($stats['b']['times'])) ?></span>
    </div>
  </div>
</div>

<p class="events-count">Total events logged: <?= number_format($total) ?></p>

</div>
</body>
</html>
