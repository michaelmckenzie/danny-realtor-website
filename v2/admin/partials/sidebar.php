<aside class="admin-sidebar" role="navigation" aria-label="Admin navigation">
  <a href="/v2/admin/" class="admin-sidebar-logo">
    <svg width="24" height="24" viewBox="0 0 32 32" fill="none">
      <path d="M16 2L2 14H6V28H13V20H19V28H26V14H30L16 2Z" fill="#006AFF"/>
    </svg>
    Danny Admin
  </a>

  <nav class="admin-nav">
    <p class="admin-nav-label">Listings</p>
    <a href="/v2/admin/" class="admin-nav-link <?= basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : '' ?>">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
      Dashboard
    </a>
    <a href="/v2/admin/add-listing.php" class="admin-nav-link <?= basename($_SERVER['PHP_SELF']) === 'add-listing.php' ? 'active' : '' ?>">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
      Add Listing
    </a>

    <p class="admin-nav-label" style="margin-top:12px;">Site</p>
    <a href="/v2/" target="_blank" class="admin-nav-link">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
      View Website
    </a>
    <a href="/v2/listings.php" target="_blank" class="admin-nav-link">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      All Listings Page
    </a>

    <?php if (defined('MLS_ENABLED') && MLS_ENABLED): ?>
    <p class="admin-nav-label" style="margin-top:12px;">MLS</p>
    <a href="/v2/mls/sync.php?manual=1" class="admin-nav-link">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
      Sync MLS Now
    </a>
    <?php endif; ?>
  </nav>

  <div class="admin-sidebar-footer">
    <a href="/v2/admin/logout.php">Sign out</a>
    <span style="color:rgba(255,255,255,.25); margin: 0 8px;">|</span>
    <a href="/v2/" target="_blank" style="color:rgba(255,255,255,.4); font-size:12px;"><?= e(SITE_NAME) ?></a>
  </div>
</aside>
