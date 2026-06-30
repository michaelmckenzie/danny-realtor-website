<?php
/**
 * includes/functions.php — Shared helper functions
 */

require_once __DIR__ . '/db.php';

// ─── Formatting ─────────────────────────────────────────────────────────────

function formatPrice(float $price, bool $isRent = false): string {
    $fmt = '$' . number_format($price, 0);
    return $isRent ? $fmt . '/mo' : $fmt;
}

function formatSqft(?int $sqft): string {
    return $sqft ? number_format($sqft) . ' sqft' : '—';
}

function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

// ─── Photos ─────────────────────────────────────────────────────────────────

function decodePhotos(mixed $json): array {
    if (!$json) return [];
    $decoded = is_string($json) ? json_decode($json, true) : $json;
    return is_array($decoded) ? $decoded : [];
}

function firstPhoto(mixed $json): string {
    $photos = decodePhotos($json);
    return !empty($photos) ? UPLOAD_URL . $photos[0] : '/assets/img/house-placeholder.svg';
}

// ─── Listing helpers ─────────────────────────────────────────────────────────

function isNewListing(string $createdAt): bool {
    return strtotime($createdAt) >= strtotime('-7 days');
}

function statusBadge(string $status, string $listingType): string {
    $labels = [
        'active'     => ($listingType === 'rent') ? 'For Rent' : 'For Sale',
        'pending'    => 'Pending',
        'sold'       => 'Sold',
        'off-market' => 'Off Market',
    ];
    $label = $labels[$status] ?? ucfirst($status);
    return '<span class="badge badge-' . e($status) . '">' . e($label) . '</span>';
}

// ─── Queries ─────────────────────────────────────────────────────────────────

function getProperties(array $filters = [], string $sort = 'newest', int $page = 1, int $perPage = 12): array {
    $where  = ['p.status != "off-market"'];
    $params = [];

    if (!empty($filters['q'])) {
        $where[]         = '(p.address LIKE :q OR p.city LIKE :q OR p.zip LIKE :q)';
        $params[':q']    = '%' . $filters['q'] . '%';
    }
    if (!empty($filters['type'])) {
        $where[]          = 'p.listing_type = :type';
        $params[':type']  = $filters['type'];
    }
    if (!empty($filters['status'])) {
        $where[]            = 'p.status = :status';
        $params[':status']  = $filters['status'];
    }
    if (!empty($filters['min_price'])) {
        $where[]              = 'p.price >= :min_price';
        $params[':min_price'] = (float)$filters['min_price'];
    }
    if (!empty($filters['max_price'])) {
        $where[]              = 'p.price <= :max_price';
        $params[':max_price'] = (float)$filters['max_price'];
    }
    if (!empty($filters['min_beds'])) {
        $where[]              = 'p.beds >= :min_beds';
        $params[':min_beds']  = (int)$filters['min_beds'];
    }
    if (!empty($filters['min_baths'])) {
        $where[]              = 'p.baths >= :min_baths';
        $params[':min_baths'] = (float)$filters['min_baths'];
    }
    if (!empty($filters['property_type'])) {
        $where[]                  = 'p.property_type = :property_type';
        $params[':property_type'] = $filters['property_type'];
    }

    $sortClause = match($sort) {
        'price_asc'  => 'p.price ASC',
        'price_desc' => 'p.price DESC',
        'beds_desc'  => 'p.beds DESC',
        'sqft_desc'  => 'p.sqft DESC',
        default      => 'p.created_at DESC',
    };

    $whereStr = implode(' AND ', $where);
    $offset   = ($page - 1) * $perPage;

    // Total count
    $countSql  = "SELECT COUNT(*) FROM properties p WHERE $whereStr";
    $countStmt = db()->prepare($countSql);
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    // Results
    $sql  = "SELECT * FROM properties p WHERE $whereStr ORDER BY $sortClause LIMIT :limit OFFSET :offset";
    $stmt = db()->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    return [
        'rows'       => $rows,
        'total'      => $total,
        'page'       => $page,
        'per_page'   => $perPage,
        'last_page'  => (int)ceil($total / $perPage),
    ];
}

function getProperty(int $id): ?array {
    $stmt = db()->prepare('SELECT * FROM properties WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

// ─── File upload ──────────────────────────────────────────────────────────────

/**
 * Handles uploaded photo files, returns array of saved relative filenames.
 * Returns ['saved' => [...], 'errors' => [...]]
 */
function handlePhotoUploads(array $filesArray): array {
    $saved  = [];
    $errors = [];

    if (empty($filesArray['tmp_name'])) return compact('saved', 'errors');

    $count = count($filesArray['tmp_name']);
    if ($count > MAX_PHOTOS) {
        $errors[] = 'Maximum ' . MAX_PHOTOS . ' photos allowed.';
        $count    = MAX_PHOTOS;
    }

    for ($i = 0; $i < $count; $i++) {
        if ($filesArray['error'][$i] !== UPLOAD_ERR_OK) {
            $errors[] = 'Upload error on file #' . ($i + 1) . '.';
            continue;
        }
        if ($filesArray['size'][$i] > MAX_UPLOAD_SIZE) {
            $errors[] = 'File "' . e($filesArray['name'][$i]) . '" exceeds 5 MB limit.';
            continue;
        }

        $mime = mime_content_type($filesArray['tmp_name'][$i]);
        if (!in_array($mime, ALLOWED_TYPES, true)) {
            $errors[] = 'File "' . e($filesArray['name'][$i]) . '" is not a supported image type.';
            continue;
        }

        $ext      = strtolower(pathinfo($filesArray['name'][$i], PATHINFO_EXTENSION));
        if (!in_array($ext, ALLOWED_EXT, true)) {
            $ext = 'jpg'; // fallback
        }
        $filename = bin2hex(random_bytes(12)) . '.' . $ext;
        $dest     = UPLOAD_DIR . $filename;

        if (!move_uploaded_file($filesArray['tmp_name'][$i], $dest)) {
            $errors[] = 'Could not save file "' . e($filesArray['name'][$i]) . '".';
            continue;
        }
        $saved[] = $filename;
    }
    return compact('saved', 'errors');
}

function deletePhotoFile(string $filename): void {
    // Only allow simple filenames — no directory traversal
    if (strpbrk($filename, '/\\..') === false) {
        $path = UPLOAD_DIR . basename($filename);
        if (file_exists($path)) {
            unlink($path);
        }
    }
}

// ─── Pagination helper ────────────────────────────────────────────────────────

function paginationLinks(array $result, string $baseUrl): string {
    if ($result['last_page'] <= 1) return '';

    $html = '<nav class="pagination">';
    $cur  = $result['page'];
    $last = $result['last_page'];

    // Build URL preserving existing params
    $buildUrl = fn(int $p) => $baseUrl . '&page=' . $p;

    if ($cur > 1) {
        $html .= '<a href="' . e($buildUrl($cur - 1)) . '" class="page-btn">&laquo; Prev</a>';
    }
    for ($p = max(1, $cur - 2); $p <= min($last, $cur + 2); $p++) {
        $active = $p === $cur ? ' active' : '';
        $html  .= '<a href="' . e($buildUrl($p)) . '" class="page-btn' . $active . '">' . $p . '</a>';
    }
    if ($cur < $last) {
        $html .= '<a href="' . e($buildUrl($cur + 1)) . '" class="page-btn">Next &raquo;</a>';
    }
    $html .= '</nav>';
    return $html;
}
