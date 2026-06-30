<?php
/**
 * listings.php — Search results / browse page
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

// ─── Input sanitisation ──────────────────────────────────────────────────────
$q            = trim(strip_tags($_GET['q']            ?? ''));
$type         = in_array($_GET['type'] ?? '', ['sale','rent']) ? $_GET['type'] : '';
$status       = in_array($_GET['status'] ?? '', ['active','pending','sold']) ? $_GET['status'] : '';
$minPrice     = isset($_GET['min_price'])     && is_numeric($_GET['min_price'])     ? (float)$_GET['min_price']     : null;
$maxPrice     = isset($_GET['max_price'])     && is_numeric($_GET['max_price'])     ? (float)$_GET['max_price']     : null;
$minBeds      = isset($_GET['min_beds'])      && is_numeric($_GET['min_beds'])      ? (int)$_GET['min_beds']        : null;
$minBaths     = isset($_GET['min_baths'])     && is_numeric($_GET['min_baths'])     ? (float)$_GET['min_baths']     : null;
$propertyType = trim(strip_tags($_GET['property_type'] ?? ''));
$sort         = in_array($_GET['sort'] ?? '', ['newest','price_asc','price_desc','beds_desc','sqft_desc'])
                ? $_GET['sort'] : 'newest';
$page         = max(1, (int)($_GET['page'] ?? 1));

$filters = array_filter([
    'q'             => $q,
    'type'          => $type,
    'status'        => $status,
    'min_price'     => $minPrice,
    'max_price'     => $maxPrice,
    'min_beds'      => $minBeds,
    'min_baths'     => $minBaths,
    'property_type' => $propertyType,
]);

$result = getProperties($filters, $sort, $page, 12);
$rows   = $result['rows'];

// Build a base URL that preserves all filters except page
function buildBaseUrl(array $exclude = ['page']): string {
    $params = $_GET;
    foreach ($exclude as $k) unset($params[$k]);
    return '/listings.php?' . http_build_query($params);
}

$pageTitle   = ($q ? e($q) . ' — ' : '') . 'Properties ' . ($type === 'rent' ? 'for Rent' : ($type === 'sale' ? 'for Sale' : ''));
$searchQuery = $q;

require __DIR__ . '/includes/header.php';
?>

<!-- ─── Filter bar ────────────────────────────────────────────────────────── -->
<form class="filters-bar" method="get" action="/v2/listings.php" id="filterForm">
  <div class="container filters-inner">

    <?php if ($q): ?><input type="hidden" name="q" value="<?= e($q) ?>"><?php endif; ?>

    <select name="type" class="filter-select <?= $type ? 'active' : '' ?>" data-auto-submit aria-label="Listing type">
      <option value="">Any Type</option>
      <option value="sale" <?= $type === 'sale' ? 'selected' : '' ?>>For Sale</option>
      <option value="rent" <?= $type === 'rent' ? 'selected' : '' ?>>For Rent</option>
    </select>

    <div class="filter-divider" aria-hidden="true"></div>

    <select name="min_beds" class="filter-select <?= $minBeds ? 'active' : '' ?>" data-auto-submit aria-label="Minimum bedrooms">
      <option value="">Beds</option>
      <option value="1" <?= $minBeds==1?'selected':'' ?>>1+ bd</option>
      <option value="2" <?= $minBeds==2?'selected':'' ?>>2+ bd</option>
      <option value="3" <?= $minBeds==3?'selected':'' ?>>3+ bd</option>
      <option value="4" <?= $minBeds==4?'selected':'' ?>>4+ bd</option>
      <option value="5" <?= $minBeds==5?'selected':'' ?>>5+ bd</option>
    </select>

    <select name="min_baths" class="filter-select <?= $minBaths ? 'active' : '' ?>" data-auto-submit aria-label="Minimum bathrooms">
      <option value="">Baths</option>
      <option value="1" <?= $minBaths==1?'selected':'' ?>>1+ ba</option>
      <option value="2" <?= $minBaths==2?'selected':'' ?>>2+ ba</option>
      <option value="3" <?= $minBaths==3?'selected':'' ?>>3+ ba</option>
    </select>

    <select name="property_type" class="filter-select <?= $propertyType ? 'active' : '' ?>" data-auto-submit aria-label="Property type">
      <option value="">Property Type</option>
      <?php foreach (['Single Family','Condo','Townhouse','Multi-Family','Land','Commercial'] as $pt): ?>
        <option value="<?= e($pt) ?>" <?= $propertyType===$pt?'selected':'' ?>><?= e($pt) ?></option>
      <?php endforeach; ?>
    </select>

    <!-- Price range -->
    <div style="display:flex; align-items:center; gap:6px; flex-wrap:wrap;">
      <input type="number" name="min_price" placeholder="Min $" value="<?= $minPrice ? (int)$minPrice : '' ?>"
             class="filter-select" style="width:90px;" aria-label="Minimum price">
      <span style="color:#ccc;">–</span>
      <input type="number" name="max_price" placeholder="Max $" value="<?= $maxPrice ? (int)$maxPrice : '' ?>"
             class="filter-select" style="width:90px;" aria-label="Maximum price">
      <button type="submit" class="btn btn-primary btn-sm">Go</button>
    </div>

    <?php if ($q || $type || $minBeds || $minBaths || $propertyType || $minPrice || $maxPrice): ?>
      <a href="/v2/listings.php" class="btn btn-ghost btn-sm" style="text-decoration:underline; color:var(--clr-danger);">Clear filters</a>
    <?php endif; ?>

    <div class="filters-sort">
      <span class="sort-label">Sort:</span>
      <select name="sort" class="filter-select" data-auto-submit aria-label="Sort order">
        <option value="newest"     <?= $sort==='newest'     ?'selected':'' ?>>Newest</option>
        <option value="price_asc"  <?= $sort==='price_asc'  ?'selected':'' ?>>Price ↑</option>
        <option value="price_desc" <?= $sort==='price_desc' ?'selected':'' ?>>Price ↓</option>
        <option value="beds_desc"  <?= $sort==='beds_desc'  ?'selected':'' ?>>Most Beds</option>
        <option value="sqft_desc"  <?= $sort==='sqft_desc'  ?'selected':'' ?>>Largest</option>
      </select>
    </div>
  </div>
</form>

<!-- ─── Main content ──────────────────────────────────────────────────────── -->
<div style="background: var(--clr-white); min-height: 60vh;">
  <div class="container">
    <div class="results-header">
      <p class="results-count">
        <?php if ($result['total'] === 0): ?>
          No results found
        <?php else: ?>
          Showing <strong><?= number_format(($page-1)*12+1) ?>–<?= min($page*12, $result['total']) ?></strong>
          of <strong><?= number_format($result['total']) ?></strong>
          <?= $q ? ' results for <em>' . e($q) . '</em>' : ' properties' ?>
        <?php endif; ?>
      </p>
    </div>

    <?php if (empty($rows)): ?>
      <div class="no-results">
        <svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" aria-hidden="true">
          <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
        </svg>
        <h3>No properties found</h3>
        <p>Try adjusting your filters or search a different area.</p>
        <a href="/v2/listings.php" class="btn btn-primary mt-4">View All Listings</a>
      </div>
    <?php else: ?>
      <div class="property-grid" style="padding-bottom:40px;">
        <?php foreach ($rows as $p): ?>
          <?php
            $photo   = firstPhoto($p['photos']);
            $isRent  = ($p['listing_type'] === 'rent');
            $isNew   = isNewListing($p['created_at']);
            $url     = '/property.php?id=' . (int)$p['id'];
          ?>
          <article class="property-card">
            <a href="<?= e($url) ?>" class="card-photo-wrap" tabindex="-1">
              <?php if ($photo !== '/assets/img/house-placeholder.svg'): ?>
                <img src="<?= e($photo) ?>" alt="<?= e($p['address']) ?>" loading="lazy">
              <?php else: ?>
                <div class="photo-placeholder">
                  <svg width="72" height="72" viewBox="0 0 64 64" fill="none">
                    <path d="M32 6L4 28H12V56H26V40H38V56H52V28H60L32 6Z" fill="#9BB5CB"/>
                  </svg>
                </div>
              <?php endif; ?>
              <div class="card-badge-row">
                <?= statusBadge($p['status'], $p['listing_type']) ?>
                <?php if ($isNew && $p['status']==='active'): ?><span class="badge badge-new">New</span><?php endif; ?>
              </div>
            </a>
            <div class="card-body">
              <a href="<?= e($url) ?>">
                <div class="card-price"><?= formatPrice((float)$p['price'], $isRent) ?></div>
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
                  <strong><?= number_format((float)$p['baths'],1) ?></strong> ba
                </span>
                <?php if ($p['sqft']): ?>
                <span class="card-stat-sep">|</span>
                <span class="card-stat"><strong><?= number_format((int)$p['sqft']) ?></strong> sqft</span>
                <?php endif; ?>
                <span class="card-type-badge"><?= e($p['property_type']) ?></span>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>

      <?= paginationLinks($result, buildBaseUrl()) ?>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
