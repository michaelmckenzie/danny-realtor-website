<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= isset($pageTitle) ? e($pageTitle) . ' | ' : '' ?><?= e(SITE_NAME) ?></title>
  <meta name="description" content="<?= e($metaDesc ?? 'Search homes for sale and rent with ' . SITE_NAME . '.') ?>">
  <link rel="stylesheet" href="/v2/assets/css/style.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body class="<?= isset($bodyClass) ? e($bodyClass) : '' ?>">

<header class="site-header <?= isset($headerTransparent) && $headerTransparent ? 'is-transparent' : '' ?>">
  <div class="header-inner container">

    <a href="/v2/" class="site-logo" aria-label="<?= e(SITE_NAME) ?> Home">
      <svg width="32" height="32" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
        <path d="M16 2L2 14H6V28H13V20H19V28H26V14H30L16 2Z" fill="currentColor"/>
      </svg>
      <span class="logo-text">Danny<strong>Homes</strong></span>
    </a>

    <?php if (empty($hideHeaderSearch)): ?>
    <form class="header-search-form" action="/v2/listings.php" method="get" role="search">
      <div class="header-search-wrap">
        <svg class="search-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
          <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
        </svg>
        <input
          type="search"
          name="q"
          class="header-search-input"
          placeholder="Enter city, address, or ZIP"
          value="<?= isset($searchQuery) ? e($searchQuery) : '' ?>"
          aria-label="Search properties"
          autocomplete="off"
        >
        <button type="submit" class="btn btn-primary header-search-btn">Search</button>
      </div>
    </form>
    <?php endif; ?>

    <nav class="main-nav" aria-label="Main navigation">
      <a href="/v2/listings.php?type=sale" class="nav-link">Buy</a>
      <a href="/v2/listings.php?type=rent" class="nav-link">Rent</a>
      <a href="/v2/admin/login.php" class="nav-link nav-link-agent">Agent Login</a>
    </nav>

    <button class="hamburger" id="navToggle" aria-label="Open menu" aria-expanded="false">
      <span></span><span></span><span></span>
    </button>
  </div>
</header>
