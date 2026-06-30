<?php
/**
 * property.php — Single property detail page
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$p  = $id ? getProperty($id) : null;

if (!$p) {
    http_response_code(404);
    $pageTitle = 'Property Not Found';
    require __DIR__ . '/includes/header.php';
    echo '<div class="container no-results" style="padding:80px 20px; text-align:center;"><h2>Property not found</h2><p>This listing may have been removed or the URL is incorrect.</p><a href="/v2/listings.php" class="btn btn-primary mt-4">Back to Listings</a></div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$photos   = decodePhotos($p['photos']);
$isRent   = ($p['listing_type'] === 'rent');
$price    = formatPrice((float)$p['price'], $isRent);
$fullAddr = "{$p['address']}, {$p['city']}, {$p['state']} {$p['zip']}";

$pageTitle = e($p['address']) . ', ' . e($p['city']);
$metaDesc  = "See this {$p['beds']} bed, {$p['baths']} bath home at {$fullAddr} — {$price}. Listed on " . SITE_NAME;

// Handle contact form submission
$contactSent = false;
$contactError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['contact_submit'])) {
    $name    = trim(strip_tags($_POST['contact_name']  ?? ''));
    $email   = trim(strip_tags($_POST['contact_email'] ?? ''));
    $phone   = trim(strip_tags($_POST['contact_phone'] ?? ''));
    $message = trim(strip_tags($_POST['contact_msg']   ?? ''));

    if (!$name || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $contactError = 'Please enter your name and a valid email address.';
    } else {
        // In production, configure real mail delivery (SMTP via PHPMailer etc.)
        $to      = AGENT_EMAIL;
        $subject = "Property Inquiry: {$fullAddr}";
        $body    = "Name: $name\nEmail: $email\nPhone: $phone\n\nMessage:\n$message\n\nProperty: $fullAddr\nURL: " . SITE_URL . "/property.php?id=$id";
        $headers = "From: noreply@" . parse_url(SITE_URL, PHP_URL_HOST) . "\r\nReply-To: $email";
        // mail($to, $subject, $body, $headers);  // uncomment when server mail is configured
        $contactSent = true;
    }
}

require __DIR__ . '/includes/header.php';
?>

<div class="detail-page">
  <div class="container">

    <!-- Breadcrumb -->
    <nav class="breadcrumb" aria-label="Breadcrumb">
      <a href="/v2/">Home</a>
      <span class="breadcrumb-sep" aria-hidden="true">/</span>
      <a href="/v2/listings.php">Listings</a>
      <span class="breadcrumb-sep" aria-hidden="true">/</span>
      <span><?= e($p['city']) ?></span>
      <span class="breadcrumb-sep" aria-hidden="true">/</span>
      <span><?= e($p['address']) ?></span>
    </nav>

    <!-- Photo gallery -->
    <?php if (!empty($photos)): ?>
    <div class="gallery-grid" id="galleryGrid">
      <div class="gallery-main">
        <img
          src="<?= e(UPLOAD_URL . $photos[0]) ?>"
          alt="<?= e($p['address']) ?> — photo 1"
          class="gallery-img"
          data-lightbox
          data-src="<?= e(UPLOAD_URL . $photos[0]) ?>"
        >
      </div>
      <?php foreach (array_slice($photos, 1, 2) as $i => $photo): ?>
      <div>
        <img
          src="<?= e(UPLOAD_URL . $photo) ?>"
          alt="<?= e($p['address']) ?> — photo <?= $i + 2 ?>"
          class="gallery-img"
          data-lightbox
          data-src="<?= e(UPLOAD_URL . $photo) ?>"
        >
      </div>
      <?php endforeach; ?>
      <?php if (count($photos) > 3): ?>
      <button class="gallery-more-btn" id="viewAllPhotos" aria-label="View all photos">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="9" y1="3" x2="9" y2="21"/><line x1="15" y1="3" x2="15" y2="21"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="3" y1="15" x2="21" y2="15"/></svg>
        See all <?= count($photos) ?> photos
      </button>
      <?php endif; ?>
    </div>
    <?php else: ?>
    <div style="height:360px; border-radius:var(--radius-md); overflow:hidden;">
      <div class="gallery-placeholder">
        <svg width="100" height="100" viewBox="0 0 64 64" fill="none">
          <path d="M32 6L4 28H12V56H26V40H38V56H52V28H60L32 6Z" fill="#9BB5CB"/>
        </svg>
      </div>
    </div>
    <?php endif; ?>

    <!-- Hidden photos for lightbox -->
    <?php foreach (array_slice($photos, 3) as $i => $photo): ?>
      <img data-lightbox data-src="<?= e(UPLOAD_URL . $photo) ?>" alt="Photo <?= $i+4 ?>" style="display:none">
    <?php endforeach; ?>

    <!-- Lightbox overlay -->
    <div id="lightboxOverlay" class="lightbox-overlay" role="dialog" aria-label="Photo gallery" aria-modal="true">
      <button id="lightboxClose" class="lightbox-close" aria-label="Close">&times;</button>
      <button id="lightboxPrev" class="lightbox-prev" aria-label="Previous photo">&#8249;</button>
      <img id="lightboxImg" class="lightbox-img" src="" alt="">
      <button id="lightboxNext" class="lightbox-next" aria-label="Next photo">&#8250;</button>
    </div>

    <!-- Detail layout -->
    <div class="detail-layout">

      <!-- Left: details -->
      <div class="detail-main">
        <div class="detail-price-row">
          <h1 class="detail-price"><?= e($price) ?></h1>
          <?= statusBadge($p['status'], $p['listing_type']) ?>
          <?php if (isNewListing($p['created_at']) && $p['status']==='active'): ?>
            <span class="badge badge-new">New</span>
          <?php endif; ?>
        </div>
        <p class="detail-address"><?= e($fullAddr) ?></p>

        <div class="detail-stats">
          <div class="detail-stat">
            <span class="detail-stat-val"><?= (int)$p['beds'] ?></span>
            <span class="detail-stat-key">Beds</span>
          </div>
          <div class="detail-stat">
            <span class="detail-stat-val"><?= number_format((float)$p['baths'],1) ?></span>
            <span class="detail-stat-key">Baths</span>
          </div>
          <?php if ($p['sqft']): ?>
          <div class="detail-stat">
            <span class="detail-stat-val"><?= number_format((int)$p['sqft']) ?></span>
            <span class="detail-stat-key">Sq Ft</span>
          </div>
          <?php endif; ?>
          <?php if ($p['lot_sqft']): ?>
          <div class="detail-stat">
            <span class="detail-stat-val"><?= number_format((int)$p['lot_sqft']) ?></span>
            <span class="detail-stat-key">Lot Sq Ft</span>
          </div>
          <?php endif; ?>
        </div>

        <!-- Description -->
        <?php if ($p['description']): ?>
        <div class="detail-section">
          <h2 class="detail-section-title">About this home</h2>
          <p class="detail-desc"><?= nl2br(e($p['description'])) ?></p>
        </div>
        <?php endif; ?>

        <!-- Facts & Features -->
        <div class="detail-section">
          <h2 class="detail-section-title">Facts &amp; Features</h2>
          <div class="detail-facts">
            <div class="detail-fact"><span class="detail-fact-key">Property Type</span><span class="detail-fact-val"><?= e($p['property_type']) ?></span></div>
            <div class="detail-fact"><span class="detail-fact-key">Listing Type</span><span class="detail-fact-val"><?= $isRent ? 'For Rent' : 'For Sale' ?></span></div>
            <?php if ($p['year_built']): ?><div class="detail-fact"><span class="detail-fact-key">Year Built</span><span class="detail-fact-val"><?= (int)$p['year_built'] ?></span></div><?php endif; ?>
            <?php if ($p['garage']): ?><div class="detail-fact"><span class="detail-fact-key">Garage</span><span class="detail-fact-val"><?= (int)$p['garage'] ?> car</span></div><?php endif; ?>
            <?php if ($p['hoa_fee']): ?><div class="detail-fact"><span class="detail-fact-key">HOA / Month</span><span class="detail-fact-val"><?= formatPrice((float)$p['hoa_fee']) ?></span></div><?php endif; ?>
            <div class="detail-fact"><span class="detail-fact-key">Status</span><span class="detail-fact-val"><?= ucfirst(e($p['status'])) ?></span></div>
            <div class="detail-fact"><span class="detail-fact-key">Listed</span><span class="detail-fact-val"><?= date('M j, Y', strtotime($p['created_at'])) ?></span></div>
            <?php if ($p['mls_id']): ?><div class="detail-fact"><span class="detail-fact-key">MLS #</span><span class="detail-fact-val"><?= e($p['mls_id']) ?></span></div><?php endif; ?>
          </div>
        </div>

        <!-- Map placeholder -->
        <div class="detail-section">
          <h2 class="detail-section-title">Location</h2>
          <div style="height:260px; border-radius:var(--radius-md); background:linear-gradient(135deg,#c8daea,#ddeaf5); display:flex; align-items:center; justify-content:center; border:1px solid var(--clr-border); flex-direction:column; gap:12px;">
            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#7aabcc" stroke-width="1.5" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
            <p style="color:#7aabcc; font-size:13px;">Map integration — add your Google Maps API key in config.php</p>
            <a
              href="https://maps.google.com/?q=<?= urlencode($fullAddr) ?>"
              target="_blank"
              rel="noopener noreferrer"
              class="btn btn-outline btn-sm"
            >Open in Google Maps</a>
          </div>
        </div>
      </div>

      <!-- Right: contact sidebar -->
      <aside class="detail-sidebar">
        <div class="contact-card">
          <div class="contact-card-price"><?= e($price) ?></div>
          <div class="contact-card-addr"><?= e($fullAddr) ?></div>

          <?php if ($contactSent): ?>
            <div class="flash flash-success">Thanks! <?= e(AGENT_NAME) ?> will be in touch soon.</div>
          <?php elseif ($contactError): ?>
            <div class="flash flash-error"><?= e($contactError) ?></div>
          <?php endif; ?>

          <?php if (!$contactSent): ?>
          <form class="contact-form" id="contactForm" method="post">
            <input type="hidden" name="contact_submit" value="1">
            <div class="form-group">
              <label class="form-label" for="contact_name">Your Name</label>
              <input type="text" name="contact_name" id="contact_name" class="form-control" placeholder="Jane Smith" required>
            </div>
            <div class="form-group">
              <label class="form-label" for="contact_email">Email</label>
              <input type="email" name="contact_email" id="contact_email" class="form-control" placeholder="jane@example.com" required>
            </div>
            <div class="form-group">
              <label class="form-label" for="contact_phone">Phone <span style="font-weight:400;">(optional)</span></label>
              <input type="tel" name="contact_phone" id="contact_phone" class="form-control" placeholder="(555) 123-4567">
            </div>
            <div class="form-group">
              <label class="form-label" for="contact_msg">Message</label>
              <textarea name="contact_msg" id="contact_msg" class="form-control" rows="3" placeholder="I'd like to schedule a tour of this property..."></textarea>
            </div>
            <button type="submit" class="btn btn-primary btn-block btn-lg">Request a Tour</button>
          </form>
          <?php endif; ?>

          <div class="contact-agent">
            <div class="agent-avatar"><?= strtoupper(substr(AGENT_NAME, 0, 1)) ?></div>
            <div>
              <div class="agent-name"><?= e(AGENT_NAME) ?></div>
              <div class="agent-phone">
                <a href="tel:<?= e(AGENT_PHONE) ?>"><?= e(AGENT_PHONE) ?></a>
              </div>
            </div>
          </div>
        </div>
      </aside>
    </div>

  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
