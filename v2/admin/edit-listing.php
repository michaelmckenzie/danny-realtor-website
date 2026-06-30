<?php
/**
 * admin/edit-listing.php — Edit an existing property listing
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$p  = $id ? getProperty($id) : null;
if (!$p) {
    flashSet('error', 'Listing not found.');
    header('Location: /v2/admin/');
    exit;
}

$existingPhotos = decodePhotos($p['photos']);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf'] ?? '')) {
        $errors[] = 'Invalid form token. Please refresh and try again.';
    } else {
        $data = [
            'address'       => trim(strip_tags($_POST['address']       ?? '')),
            'city'          => trim(strip_tags($_POST['city']          ?? '')),
            'state'         => trim(strip_tags($_POST['state']         ?? '')),
            'zip'           => trim(strip_tags($_POST['zip']           ?? '')),
            'price'         => trim($_POST['price']         ?? ''),
            'beds'          => trim($_POST['beds']          ?? ''),
            'baths'         => trim($_POST['baths']         ?? ''),
            'sqft'          => trim($_POST['sqft']          ?? ''),
            'lot_sqft'      => trim($_POST['lot_sqft']      ?? ''),
            'year_built'    => trim($_POST['year_built']    ?? ''),
            'property_type' => trim(strip_tags($_POST['property_type'] ?? '')),
            'listing_type'  => in_array($_POST['listing_type'] ?? '', ['sale','rent']) ? $_POST['listing_type'] : 'sale',
            'status'        => in_array($_POST['status'] ?? '', ['active','pending','sold','off-market']) ? $_POST['status'] : 'active',
            'hoa_fee'       => trim($_POST['hoa_fee']  ?? ''),
            'garage'        => trim($_POST['garage']   ?? ''),
            'description'   => trim(strip_tags($_POST['description'] ?? '')),
        ];

        if (!$data['address'])                                  $errors[] = 'Address is required.';
        if (!$data['city'])                                     $errors[] = 'City is required.';
        if (!$data['state'])                                    $errors[] = 'State is required.';
        if (!$data['zip'])                                      $errors[] = 'ZIP code is required.';
        if (!is_numeric($data['price']) || $data['price'] < 0) $errors[] = 'A valid price is required.';
        if (!is_numeric($data['beds'])  || $data['beds']  < 0) $errors[] = 'Beds must be a number.';
        if (!is_numeric($data['baths']) || $data['baths'] < 0) $errors[] = 'Baths must be a number.';

        // Handle photo deletions
        $deletePhotos = $_POST['delete_photos'] ?? [];
        $remaining    = array_values(array_diff($existingPhotos, $deletePhotos));
        foreach ($deletePhotos as $filename) {
            deletePhotoFile($filename);
        }

        // Handle new uploads
        if (!empty($_FILES['photos']['tmp_name'][0])) {
            $uploadResult = handlePhotoUploads($_FILES['photos']);
            $remaining    = array_merge($remaining, $uploadResult['saved']);
            foreach ($uploadResult['errors'] as $e) $errors[] = $e;
        }

        if (empty($errors)) {
            $stmt = db()->prepare("
                UPDATE properties SET
                    address=:address, city=:city, state=:state, zip=:zip,
                    price=:price, beds=:beds, baths=:baths, sqft=:sqft, lot_sqft=:lot_sqft,
                    year_built=:year_built, property_type=:property_type,
                    listing_type=:listing_type, status=:status,
                    hoa_fee=:hoa_fee, garage=:garage, description=:description, photos=:photos
                WHERE id=:id
            ");
            $stmt->execute([
                ':address'       => $data['address'],
                ':city'          => $data['city'],
                ':state'         => $data['state'],
                ':zip'           => $data['zip'],
                ':price'         => (float)$data['price'],
                ':beds'          => (int)$data['beds'],
                ':baths'         => (float)$data['baths'],
                ':sqft'          => $data['sqft']       ? (int)$data['sqft']       : null,
                ':lot_sqft'      => $data['lot_sqft']   ? (int)$data['lot_sqft']   : null,
                ':year_built'    => $data['year_built'] ? (int)$data['year_built'] : null,
                ':property_type' => $data['property_type'],
                ':listing_type'  => $data['listing_type'],
                ':status'        => $data['status'],
                ':hoa_fee'       => $data['hoa_fee']    ? (float)$data['hoa_fee']  : null,
                ':garage'        => $data['garage']     ? (int)$data['garage']     : null,
                ':description'   => $data['description'],
                ':photos'        => json_encode(array_values($remaining)),
                ':id'            => $id,
            ]);
            flashSet('success', 'Listing updated successfully.');
            header('Location: /v2/admin/');
            exit;
        }
        // Refresh for display
        $p = array_merge($p, $data);
        $existingPhotos = $remaining;
    }
} else {
    $data = $p; // prepopulate form
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Edit Listing | Admin</title>
  <link rel="stylesheet" href="/v2/assets/css/style.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body class="admin-body">
<div class="admin-wrap">
  <?php require __DIR__ . '/partials/sidebar.php'; ?>
  <div class="admin-main">
    <div class="admin-topbar">
      <h1 class="admin-page-title">Edit: <?= e($p['address']) ?></h1>
      <div style="display:flex; gap:10px;">
        <a href="/v2/property.php?id=<?= $id ?>" target="_blank" class="btn btn-ghost btn-sm">View on Site</a>
        <a href="/v2/admin/" class="btn btn-ghost btn-sm">&larr; Back</a>
      </div>
    </div>
    <div class="admin-content">

      <?php if ($errors): ?>
        <div class="flash flash-error">
          <strong>Please fix the following:</strong>
          <ul style="margin-top:6px; padding-left:18px;">
            <?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <form method="post" enctype="multipart/form-data" class="admin-form-card">
        <?= csrfField() ?>

        <!-- Address -->
        <div class="admin-form-section">
          <h2 class="admin-form-section-title">Location</h2>
          <div class="form-group">
            <label class="form-label" for="address">Street Address *</label>
            <input type="text" id="address" name="address" class="form-control" value="<?= e($data['address'] ?? '') ?>" required>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label class="form-label" for="city">City *</label>
              <input type="text" id="city" name="city" class="form-control" value="<?= e($data['city'] ?? '') ?>" required>
            </div>
            <div class="form-group">
              <label class="form-label" for="state">State *</label>
              <input type="text" id="state" name="state" class="form-control" value="<?= e($data['state'] ?? '') ?>" required>
            </div>
          </div>
          <div class="form-group" style="max-width:200px;">
            <label class="form-label" for="zip">ZIP Code *</label>
            <input type="text" id="zip" name="zip" class="form-control" value="<?= e($data['zip'] ?? '') ?>" required>
          </div>
        </div>

        <!-- Listing details -->
        <div class="admin-form-section">
          <h2 class="admin-form-section-title">Listing Details</h2>
          <div class="form-row">
            <div class="form-group">
              <label class="form-label" for="listing_type">Listing Type *</label>
              <select id="listing_type" name="listing_type" class="form-control">
                <option value="sale" <?= ($data['listing_type'] ?? '') === 'sale' ? 'selected' : '' ?>>For Sale</option>
                <option value="rent" <?= ($data['listing_type'] ?? '') === 'rent' ? 'selected' : '' ?>>For Rent</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label" for="status">Status *</label>
              <select id="status" name="status" class="form-control">
                <?php foreach (['active','pending','sold','off-market'] as $s): ?>
                  <option value="<?= $s ?>" <?= ($data['status'] ?? '') === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label class="form-label" for="price">Price (USD) *</label>
              <input type="number" id="price" name="price" class="form-control" min="0" step="1" value="<?= e($data['price'] ?? '') ?>" required>
            </div>
            <div class="form-group">
              <label class="form-label" for="property_type">Property Type</label>
              <select id="property_type" name="property_type" class="form-control">
                <?php foreach (['Single Family','Condo','Townhouse','Multi-Family','Land','Commercial','Other'] as $pt): ?>
                  <option value="<?= e($pt) ?>" <?= ($data['property_type'] ?? '') === $pt ? 'selected' : '' ?>><?= e($pt) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
        </div>

        <!-- Property specs -->
        <div class="admin-form-section">
          <h2 class="admin-form-section-title">Property Specs</h2>
          <div class="form-row">
            <div class="form-group">
              <label class="form-label" for="beds">Bedrooms *</label>
              <input type="number" id="beds" name="beds" class="form-control" min="0" step="1" value="<?= e($data['beds'] ?? '') ?>" required>
            </div>
            <div class="form-group">
              <label class="form-label" for="baths">Bathrooms *</label>
              <input type="number" id="baths" name="baths" class="form-control" min="0" step="0.5" value="<?= e($data['baths'] ?? '') ?>" required>
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label class="form-label" for="sqft">Living Area (sq ft)</label>
              <input type="number" id="sqft" name="sqft" class="form-control" value="<?= e($data['sqft'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label class="form-label" for="lot_sqft">Lot Size (sq ft)</label>
              <input type="number" id="lot_sqft" name="lot_sqft" class="form-control" value="<?= e($data['lot_sqft'] ?? '') ?>">
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label class="form-label" for="year_built">Year Built</label>
              <input type="number" id="year_built" name="year_built" class="form-control" value="<?= e($data['year_built'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label class="form-label" for="garage">Garage Spaces</label>
              <input type="number" id="garage" name="garage" class="form-control" value="<?= e($data['garage'] ?? '') ?>">
            </div>
          </div>
          <div class="form-group" style="max-width:240px;">
            <label class="form-label" for="hoa_fee">HOA Fee / Month</label>
            <input type="number" id="hoa_fee" name="hoa_fee" class="form-control" value="<?= e($data['hoa_fee'] ?? '') ?>">
          </div>
        </div>

        <!-- Description -->
        <div class="admin-form-section">
          <h2 class="admin-form-section-title">Description</h2>
          <div class="form-group">
            <label class="form-label" for="description">Listing Description</label>
            <textarea id="description" name="description" class="form-control" rows="6"><?= e($data['description'] ?? '') ?></textarea>
          </div>
        </div>

        <!-- Existing photos -->
        <?php if (!empty($existingPhotos)): ?>
        <div class="admin-form-section">
          <h2 class="admin-form-section-title">Current Photos</h2>
          <p class="form-hint" style="margin-bottom:12px;">Check a photo to delete it when you save.</p>
          <div class="photo-previews">
            <?php foreach ($existingPhotos as $filename): ?>
            <div class="photo-preview-item">
              <img src="<?= e(UPLOAD_URL . $filename) ?>" alt="">
              <label style="position:absolute; bottom:2px; left:2px; background:rgba(0,0,0,.5); padding:2px 4px; border-radius:3px; cursor:pointer;" title="Mark for deletion">
                <input type="checkbox" name="delete_photos[]" value="<?= e($filename) ?>" class="photo-delete-check" style="display:none;">
                <span style="color:#fff; font-size:11px; font-weight:600;">✕ del</span>
              </label>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <!-- Upload new photos -->
        <div class="admin-form-section">
          <h2 class="admin-form-section-title">Add More Photos</h2>
          <div class="form-group">
            <input type="file" id="photoInput" name="photos[]" class="form-control" multiple accept="image/jpeg,image/png,image/webp">
            <p class="form-hint">New photos will be appended. JPG, PNG, WebP — max 5 MB each.</p>
            <div id="photoPreviews" class="photo-previews"></div>
          </div>
        </div>

        <div style="display:flex; gap:12px; flex-wrap:wrap;">
          <button type="submit" class="btn btn-primary btn-lg">Save Changes</button>
          <a href="/v2/admin/" class="btn btn-ghost btn-lg">Cancel</a>
          <a href="/v2/admin/delete-listing.php?id=<?= $id ?>"
             class="btn btn-danger btn-lg"
             style="margin-left:auto;"
             data-confirm="Delete this listing permanently?">Delete Listing</a>
        </div>
      </form>
    </div>
  </div>
</div>
<script src="/v2/assets/js/main.js"></script>
</body>
</html>
