<?php
/**
 * admin/index.php — Admin dashboard: listing table
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

// Quick stats
$stats = db()->query("
    SELECT
        SUM(listing_type='sale' AND status='active')  AS sale_active,
        SUM(listing_type='rent' AND status='active')  AS rent_active,
        SUM(status='pending')                          AS pending,
        SUM(source='mls')                              AS from_mls,
        COUNT(*)                                       AS total
    FROM properties
")->fetch();

// All listings, newest first
$rows = db()->query("SELECT * FROM properties ORDER BY created_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard | Admin</title>
  <link rel="stylesheet" href="/v2/assets/css/style.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body class="admin-body">
<div class="admin-wrap">

  <?php require __DIR__ . '/partials/sidebar.php'; ?>

  <div class="admin-main">
    <div class="admin-topbar">
      <h1 class="admin-page-title">Listings Dashboard</h1>
      <a href="/v2/admin/add-listing.php" class="btn btn-primary btn-sm">+ Add Listing</a>
    </div>
    <div class="admin-content">

      <?= flashHtml() ?>

      <!-- Stats -->
      <div class="admin-stats">
        <div class="admin-stat-card">
          <div class="stat-value"><?= (int)$stats['total'] ?></div>
          <div class="stat-label">Total Listings</div>
        </div>
        <div class="admin-stat-card">
          <div class="stat-value"><?= (int)$stats['sale_active'] ?></div>
          <div class="stat-label">Active For Sale</div>
        </div>
        <div class="admin-stat-card">
          <div class="stat-value"><?= (int)$stats['rent_active'] ?></div>
          <div class="stat-label">Active Rentals</div>
        </div>
        <div class="admin-stat-card">
          <div class="stat-value"><?= (int)$stats['pending'] ?></div>
          <div class="stat-label">Pending</div>
        </div>
        <div class="admin-stat-card">
          <div class="stat-value"><?= (int)$stats['from_mls'] ?></div>
          <div class="stat-label">From MLS</div>
        </div>
      </div>

      <!-- Listings table -->
      <div class="admin-card">
        <div class="admin-card-header">
          <h2 class="admin-card-title">All Listings</h2>
          <a href="/v2/admin/add-listing.php" class="btn btn-primary btn-sm">+ New Listing</a>
        </div>
        <div class="table-responsive">
          <table class="admin-table">
            <thead>
              <tr>
                <th class="col-photo">Photo</th>
                <th>Address</th>
                <th>Price</th>
                <th>Bed / Bath</th>
                <th>Type</th>
                <th>Status</th>
                <th>Source</th>
                <th>Added</th>
                <th class="col-actions">Actions</th>
              </tr>
            </thead>
            <tbody>
            <?php if (empty($rows)): ?>
              <tr><td colspan="9" style="text-align:center; color:var(--clr-text-muted); padding:40px;">No listings yet. <a href="/v2/admin/add-listing.php">Add your first listing.</a></td></tr>
            <?php else: ?>
              <?php foreach ($rows as $p): ?>
              <?php $photo = firstPhoto($p['photos']); ?>
              <tr>
                <td class="col-photo">
                  <?php if ($photo !== '/assets/img/house-placeholder.svg'): ?>
                    <img src="<?= e($photo) ?>" alt="">
                  <?php else: ?>
                    <div style="width:56px;height:42px;background:#e8eff5;border-radius:4px;display:flex;align-items:center;justify-content:center;">
                      <svg width="20" height="20" viewBox="0 0 32 32" fill="none"><path d="M16 2L2 14H6V28H13V20H19V28H26V14H30L16 2Z" fill="#9BB5CB"/></svg>
                    </div>
                  <?php endif; ?>
                </td>
                <td>
                  <strong><?= e($p['address']) ?></strong><br>
                  <span style="font-size:12px; color:var(--clr-text-muted);"><?= e($p['city']) ?>, <?= e($p['state']) ?> <?= e($p['zip']) ?></span>
                </td>
                <td class="price"><?= formatPrice((float)$p['price'], $p['listing_type']==='rent') ?></td>
                <td><?= (int)$p['beds'] ?> bd / <?= number_format((float)$p['baths'],1) ?> ba</td>
                <td><?= e($p['listing_type'] === 'rent' ? 'Rent' : 'Sale') ?></td>
                <td><?= statusBadge($p['status'], $p['listing_type']) ?></td>
                <td><span style="font-size:12px; text-transform:uppercase; font-weight:600; color:var(--clr-text-muted);"><?= e($p['source']) ?></span></td>
                <td style="font-size:12px; color:var(--clr-text-muted); white-space:nowrap;"><?= date('M j, Y', strtotime($p['created_at'])) ?></td>
                <td class="col-actions">
                  <a href="/v2/property.php?id=<?= (int)$p['id'] ?>" class="btn btn-ghost btn-sm" target="_blank" title="View on site">View</a>
                  <a href="/v2/admin/edit-listing.php?id=<?= (int)$p['id'] ?>" class="btn btn-outline btn-sm">Edit</a>
                  <a href="/v2/admin/delete-listing.php?id=<?= (int)$p['id'] ?>"
                     class="btn btn-danger btn-sm"
                     data-confirm="Delete this listing? This cannot be undone.">Delete</a>
                </td>
              </tr>
              <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div><!-- .admin-content -->
  </div><!-- .admin-main -->
</div>
<script src="/v2/assets/js/main.js"></script>
</body>
</html>
