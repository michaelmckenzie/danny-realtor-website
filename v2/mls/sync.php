<?php
/**
 * mls/sync.php — MLS synchronisation runner
 *
 * Run on a cron schedule (recommended: every hour):
 *   0 * * * * php /path/to/danny-zillow-site/mls/sync.php >> /tmp/mls-sync.log 2>&1
 *
 * Or trigger manually from the admin sidebar (admin-only, checked below).
 */

// Allow CLI or admin session runs
$isCli = (php_sapi_name() === 'cli');
if (!$isCli) {
    // When called via browser, verify admin session
    require_once __DIR__ . '/../includes/auth.php';
    startSecureSession();
    if (!isAdminLoggedIn()) {
        http_response_code(403);
        exit('Forbidden');
    }
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../mls/MlsConnector.php';

// Respect the enabled flag
if (!MLS_ENABLED) {
    $msg = 'MLS sync skipped: MLS_ENABLED is false in config.php.';
    echo ($isCli ? $msg . PHP_EOL : '<p>' . htmlspecialchars($msg) . '</p>');
    exit;
}

// Respect sync interval (avoid hammering the MLS server)
$lastSync = db()->query("SELECT MAX(started_at) FROM mls_sync_log WHERE status='success'")->fetchColumn();
if ($lastSync && !isset($_GET['force'])) {
    $elapsed = time() - strtotime($lastSync);
    if ($elapsed < MLS_SYNC_INTERVAL) {
        $msg = "MLS sync skipped: last success was {$elapsed}s ago (interval " . MLS_SYNC_INTERVAL . "s). Use ?force=1 to override.";
        echo ($isCli ? $msg . PHP_EOL : '<p>' . htmlspecialchars($msg) . '</p>');
        exit;
    }
}

// Log start
$logId = db()->prepare("INSERT INTO mls_sync_log (status) VALUES ('running')")->execute()
    ? (int)db()->lastInsertId() : 0;

$added = $updated = $removed = 0;
$error = null;

try {
    // Instantiate the correct adapter
    $adapter = match(MLS_PROVIDER) {
        'rets'  => new RetsAdapter(),
        'reso'  => (function () {
                        require_once __DIR__ . '/ResoAdapter.php';
                        return new ResoAdapter();
                    })(),
        default => throw new RuntimeException('Unknown MLS_PROVIDER: ' . MLS_PROVIDER),
    };

    // If we loaded ResoAdapter above, make sure RetsAdapter is loaded too for rets case
    if (MLS_PROVIDER === 'rets') {
        require_once __DIR__ . '/RetsAdapter.php';
        $adapter = new RetsAdapter();
    }

    $log = fn(string $msg) => print(date('[Y-m-d H:i:s] ') . $msg . PHP_EOL);

    $log('Connecting to MLS (' . MLS_PROVIDER . ')...');
    $adapter->connect();
    $log('Connected. Fetching active listings...');

    $listings = $adapter->fetchAllActive();
    $log('Fetched ' . count($listings) . ' listings.');

    $pdo          = db();
    $fetchedMlsIds = [];

    foreach ($listings as $row) {
        $result = $adapter->upsert($pdo, $row);
        if ($result === 'inserted') $added++;
        if ($result === 'updated')  $updated++;
        if ($row['mls_id'])         $fetchedMlsIds[] = $row['mls_id'];
    }

    // Mark off-market any MLS listings no longer in the feed
    if (!empty($fetchedMlsIds)) {
        $placeholders = implode(',', array_fill(0, count($fetchedMlsIds), '?'));
        $stmt = $pdo->prepare(
            "UPDATE properties SET status='off-market'
             WHERE source='mls' AND mls_id NOT IN ($placeholders) AND status='active'"
        );
        $stmt->execute($fetchedMlsIds);
        $removed = $stmt->rowCount();
    }

    $adapter->disconnect();

    $log("Sync complete — added: $added, updated: $updated, removed: $removed");

    // Log success
    if ($logId) {
        $pdo->prepare("UPDATE mls_sync_log SET status='success', finished_at=NOW(), added=:a, updated=:u, removed=:r WHERE id=:id")
            ->execute([':a'=>$added,':u'=>$updated,':r'=>$removed,':id'=>$logId]);
    }

    if (!$isCli) {
        echo "<p style='font-family:monospace;'>Sync complete — added: $added, updated: $updated, removed: $removed</p>";
        echo "<p><a href='/admin/'>Back to Admin</a></p>";
    }

} catch (Throwable $e) {
    $error = $e->getMessage();
    $msg   = 'MLS sync ERROR: ' . $error;
    echo ($isCli ? $msg . PHP_EOL : '<p style="color:red;">' . htmlspecialchars($msg) . '</p>');
    error_log($msg);

    if ($logId) {
        db()->prepare("UPDATE mls_sync_log SET status='error', finished_at=NOW(), message=:m WHERE id=:id")
           ->execute([':m'=>$error,':id'=>$logId]);
    }

    if (!$isCli) {
        echo "<p><a href='/admin/'>Back to Admin</a></p>";
    }
    exit(1);
}
