<?php
/**
 * admin/delete-listing.php — Delete a property listing and its photos
 * Accepts GET (with JS confirm) or POST (direct form submission).
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

// Delete physical photo files first
foreach (decodePhotos($p['photos']) as $filename) {
    deletePhotoFile($filename);
}

// Delete database record
$stmt = db()->prepare('DELETE FROM properties WHERE id = :id');
$stmt->execute([':id' => $id]);

flashSet('success', 'Listing "' . $p['address'] . '" was deleted.');
header('Location: /v2/admin/');
exit;
