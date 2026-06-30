<?php
/**
 * index.php — Homepage
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle       = 'Homes for Sale &amp; Rent';
$hideHeaderSearch = true;
$headerTransparent = false;

// Fetch featured active listings (newest 6)
$featured = getProperties(['status' => 'active'], 'newest', 1, 6);
$forSale  = getProperties(['type' => 'sale', 'status' => 'active'], 'newest', 1, 3);
$forRent  = getProperties(['type' => 'rent', 'status' => 'active'], 'newest', 1, 3);

// Quick stat counts
$stmtSale = db()->query("SELECT COUNT(*) FROM properties WHERE listing_type='sale' AND status='active'");
$countSale = (int)$stmtSale->fetchColumn();
$stmtRent = db()->query("SELECT COUNT(*) FROM properties WHERE listing_type='rent' AND status='active'");
$countRent = (int)$stmtRent->fetchColumn();

require __DIR__ . '/includes/header.php';
?>

<!-- ─── Hero ─────────────────────────────────────────────────────────────── -->
<section class="hero">
  <div class="hero-content container">
    <p class="hero-eyebrow">Your Local Real Estate Expert</p>
    <h1>Find Your Perfect Home</h1>
    <p class="hero-subtitle">Search <?= $countSale + $countRent ?> listings — homes for sale, rent, and more.</p>

    <div class="hero-search">
      <div class="hero-search-tabs">
        <a href="/v2/listings.php?type=sale" class="hero-tab active" data-type="sale">Buy</a>
        <a href="/v2/listings.php?type=rent" class="hero-tab" data-type="rent">Rent</a>
        <a href="/v2/listings.php" class="hero-tab">All</a>
      </div>
      <form class="hero-search-body" action="/v2/listings.php" method="get">
        <input type="hidden" name="type" value="sale" id="heroListingType">
        <span class="hero-search-icon">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
          </svg>
        </span>
        <input
          type="search"
          name="q"
          class="hero-search-input"
          placeholder="Enter a city, address, or ZIP code"
          aria-label="Search for a property"
          autocomplete="off"
        >
        <button type="submit" class="btn btn-primary hero-search-btn">Search</button>
      </form>
    </div>
  </div>
</section>

<!-- ─── Stats ────────────────────────────────────────────────────────────── -->
<div class="stats-bar">
  <div class="stats-bar-inner">
    <div class="stat-item">
      <div class="stat-value"><?= number_format($countSale) ?></div>
      <div class="stat-label">Homes for Sale</div>
    </div>
    <div class="stat-item">
      <div class="stat-value"><?= number_format($countRent) ?></div>
      <div class="stat-label">Rentals</div>
    </div>
    <div class="stat-item">
      <div class="stat-value">Local</div>
      <div class="stat-label">Expert Agent</div>
    </div>
  </div>
</div>

<!-- ─── Featured Listings ────────────────────────────────────────────────── -->
<?php if (!empty($featured['rows'])): ?>
<section class="section" style="background:#fff;">
  <div class="container">
    <div class="section-header">
      <h2 class="section-title">Latest Listings</h2>
      <a href="/v2/listings.php" class="section-action">See all &rarr;</a>
    </div>
    <div class="property-grid">
      <?php foreach ($featured['rows'] as $p): ?>
        <?= renderPropertyCard($p) ?>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ─── For Sale ─────────────────────────────────────────────────────────── -->
<?php if (!empty($forSale['rows'])): ?>
<section class="section">
  <div class="container">
    <div class="section-header">
      <h2 class="section-title">Homes for Sale</h2>
      <a href="/v2/listings.php?type=sale" class="section-action">View all for sale &rarr;</a>
    </div>
    <div class="property-grid">
      <?php foreach ($forSale['rows'] as $p): ?>
        <?= renderPropertyCard($p) ?>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ─── For Rent ─────────────────────────────────────────────────────────── -->
<?php if (!empty($forRent['rows'])): ?>
<section class="section" style="background:#fff;">
  <div class="container">
    <div class="section-header">
      <h2 class="section-title">Rentals Available</h2>
      <a href="/v2/listings.php?type=rent" class="section-action">View all rentals &rarr;</a>
    </div>
    <div class="property-grid">
      <?php foreach ($forRent['rows'] as $p): ?>
        <?= renderPropertyCard($p) ?>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ─── CTA Band ──────────────────────────────────────────────────────────── -->
<section class="section" style="background:var(--clr-primary); text-align:center; padding: 50px 20px; margin-top:0;">
  <h2 style="color:#fff; font-size:28px; font-weight:800; margin-bottom:10px;">Ready to Find Your Home?</h2>
  <p style="color:rgba(255,255,255,.8); margin-bottom:24px;">Contact <?= e(AGENT_NAME) ?> today — your local real estate expert.</p>
  <a href="tel:<?= e(AGENT_PHONE) ?>" class="btn btn-lg" style="background:#fff; color:var(--clr-primary); margin-right:10px;">
    Call <?= e(AGENT_PHONE) ?>
  </a>
  <a href="mailto:<?= e(AGENT_EMAIL) ?>" class="btn btn-lg btn-outline" style="border-color:rgba(255,255,255,.5); color:#fff;">
    Send an Email
  </a>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>

<?php
/** Render a property card — defined here so index.php can use it without including functions twice */
function renderPropertyCard(array $p): string {
    $photo    = firstPhoto($p['photos']);
    $isRent   = ($p['listing_type'] === 'rent');
    $isNew    = isNewListing($p['created_at']);
    $status   = $p['status'];
    $url      = '/property.php?id=' . (int)$p['id'];
    $price    = formatPrice((float)$p['price'], $isRent);

    ob_start(); ?>
    <article class="property-card">
      <a href="<?= e($url) ?>" class="card-photo-wrap" tabindex="-1" aria-hidden="true">
        <?php if ($photo !== '/assets/img/house-placeholder.svg'): ?>
          <img src="<?= e($photo) ?>" alt="<?= e($p['address']) ?>" loading="lazy">
        <?php else: ?>
          <div class="photo-placeholder">
            <svg width="72" height="72" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M32 6L4 28H12V56H26V40H38V56H52V28H60L32 6Z" fill="#9BB5CB" stroke="none"/>
            </svg>
          </div>
        <?php endif; ?>
        <div class="card-badge-row">
          <?= statusBadge($status, $p['listing_type']) ?>
          <?php if ($isNew && $status === 'active'): ?><span class="badge badge-new">New</span><?php endif; ?>
        </div>
      </a>
      <div class="card-body">
        <a href="<?= e($url) ?>">
          <div class="card-price"><?= e($price) ?></div>
          <div class="card-address">
            <strong><?= e($p['address']) ?></strong><br>
            <?= e($p['city']) ?>, <?= e($p['state']) ?> <?= e($p['zip']) ?>
          </div>
        </a>
        <div class="card-stats">
          <span class="card-stat">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 22V12M21 22V12M1 12h22M6 12V4.5C6 3.7 6.7 3 7.5 3h9c.8 0 1.5.7 1.5 1.5V12M10 22V18h4v4"/></svg>
            <strong><?= (int)$p['beds'] ?></strong> bd
          </span>
          <span class="card-stat-sep">|</span>
          <span class="card-stat">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 12h16M4 12V6a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v6M4 16v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/></svg>
            <strong><?= number_format((float)$p['baths'], 1) ?></strong> ba
          </span>
          <?php if ($p['sqft']): ?>
          <span class="card-stat-sep">|</span>
          <span class="card-stat"><strong><?= number_format((int)$p['sqft']) ?></strong> sqft</span>
          <?php endif; ?>
          <span class="card-type-badge"><?= e($p['property_type']) ?></span>
        </div>
      </div>
    </article>
    <?php return ob_get_clean();
}
?>
