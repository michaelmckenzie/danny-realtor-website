<?php
/**
 * mls/RetsAdapter.php
 *
 * Connector for RETS (Real Estate Transaction Standard) — the older MLS protocol.
 * Many MLS boards still use RETS 1.5/1.7. Requires the phRETS library.
 *
 * Setup requirements:
 *   1. Install phRETS via Composer:
 *        composer require troytoney/phrets
 *      OR download manually: https://github.com/troytoney/phRETS
 *
 *   2. Fill in config.php:
 *        MLS_PROVIDER   = 'rets'
 *        MLS_ENDPOINT   = 'http://your-mls.rets.url/login'   // login URL from your MLS board
 *        MLS_CLIENT_ID  = 'your_rets_username'
 *        MLS_API_KEY    = 'your_rets_password'
 *
 * Ask your MLS board for:
 *   - RETS Login URL
 *   - Username & Password
 *   - Resource name  (commonly 'Property')
 *   - Class name     (commonly 'Residential', 'ResidentialProperty', etc.)
 *   - UserAgent      (sometimes required)
 *   - UserAgent password (sometimes required)
 */

require_once __DIR__ . '/MlsConnector.php';

// Path to phRETS — adjust if you placed it elsewhere
// If using Composer: require_once __DIR__ . '/../vendor/autoload.php';
// If downloaded manually:
$phRetsPath = __DIR__ . '/../vendor/phrets/phrets.php';
if (file_exists($phRetsPath)) {
    require_once $phRetsPath;
}

class RetsAdapter extends MlsConnector {

    /**
     * Adjust these to match your MLS board's RETS metadata.
     * Your MLS board's tech contact can provide these values.
     */
    private string $retsResource  = 'Property';
    private string $retsClass     = 'Residential';
    private string $retsUserAgent = 'DannyHomes/1.0';

    /** @var mixed phRETS connection handle */
    private mixed $rets = null;

    /**
     * Connect and authenticate with the RETS server.
     */
    public function connect(): void {
        if (!class_exists('phRETS')) {
            throw new RuntimeException(
                'RETS: phRETS library not found. ' .
                'Run: composer require troytoney/phrets  ' .
                'or download from https://github.com/troytoney/phRETS'
            );
        }

        $this->rets = new phRETS();
        $this->rets->SetParam('compression_enabled', true);

        // Add UserAgent header if your board requires it
        if ($this->retsUserAgent) {
            $this->rets->SetParam('user_agent', $this->retsUserAgent);
        }

        $connected = $this->rets->Connect(
            $this->config['endpoint'],       // MLS_ENDPOINT
            $this->config['client_id'],      // MLS_CLIENT_ID (username)
            $this->config['api_key']         // MLS_API_KEY   (password)
        );

        if (!$connected) {
            throw new RuntimeException(
                'RETS: Connection failed. Check your login URL, username, and password. ' .
                'Reply text: ' . $this->rets->GetLastServerResponseText()
            );
        }
    }

    /**
     * Search for listings using RETS Search Transaction.
     *
     * @param  array $query  Key/value pairs of DMQL2 filter conditions
     *                       e.g. ['Status' => 'A'] → converts to (Status=A)
     * @param  int   $limit  Max records to return per call
     * @param  int   $offset Pagination offset
     * @return array Raw associative arrays from the RETS server
     */
    public function fetchListings(array $query = [], int $limit = 500, int $offset = 0): array {
        if (!$this->rets) {
            throw new RuntimeException('RETS: call connect() before fetchListings()');
        }

        // Build DMQL2 query string from array  e.g. (Status=A),(ListingType=R)
        if (empty($query)) {
            $dmql = '(Status=A)';  // default: Active listings
        } else {
            $parts = [];
            foreach ($query as $field => $value) {
                $parts[] = "($field=$value)";
            }
            $dmql = implode(',', $parts);
        }

        $result = $this->rets->SearchQuery(
            $this->retsResource,
            $this->retsClass,
            $dmql,
            [
                'Limit'  => $limit,
                'Offset' => $offset,
                'Format' => 'COMPACT-DECODED',
                'Count'  => 1,
            ]
        );

        if (!$result) {
            $code = $this->rets->GetLastServerResponseCode();
            // Code 20201 = no records found — not an error
            if ((string)$code === '20201') return [];
            throw new RuntimeException('RETS: SearchQuery failed. Code: ' . $code);
        }

        $rows = [];
        while ($row = $this->rets->FetchRow($result)) {
            $rows[] = $row;
        }
        $this->rets->FreeResult($result);

        return $rows;
    }

    /**
     * Map a raw RETS row to our database schema.
     * Field names below are EXAMPLES — your MLS board will have its own field names.
     * Run  $this->rets->GetMetadata('METADATA-TABLE', $resource, $class)  to discover them.
     *
     * Common field name patterns:
     *   LP / ListPrice, BD / Beds, BT / Baths, SqFt, Address, City, St, Zip
     */
    public function normalise(array $raw): array {
        // Try common field name variations (RETS field names vary per board)
        $get = fn(array $keys) => array_reduce($keys, fn($carry, $k) => $carry ?? ($raw[$k] ?? null), null);

        $photos = [];
        // Fetch media separately — see fetchPhotos() below
        // This placeholder stores the MLS listing ID for later photo retrieval
        $mlsId = $get(['L_ListingID','ListingID','MLS_NUMBER','MLSNumber','ID']);

        return [
            'address'       => $this->cast($get(['L_Address','ADDRESS','StreetAddress','UnparsedAddress']), 'string'),
            'city'          => $this->cast($get(['L_City','CITY','City']), 'string'),
            'state'         => $this->cast($get(['L_State','STATE','State','StateOrProvince']), 'string'),
            'zip'           => $this->cast($get(['L_Zip','ZIP','PostalCode']), 'string'),
            'price'         => $this->cast($get(['L_AskingPrice','LP','ListPrice','L_LP']), 'float'),
            'beds'          => $this->cast($get(['L_Bedrooms','BD','BEDS','BedroomsTotal']), 'int'),
            'baths'         => $this->cast($get(['L_TotalBaths','BT','BATHS','BathroomsTotalInteger']), 'float'),
            'sqft'          => $this->cast($get(['L_SqFt','SQFT','LivingArea','SquareFeet']), 'int'),
            'lot_sqft'      => $this->cast($get(['L_LotSqFt','LOTSQFT','LotSizeSquareFeet']), 'int'),
            'year_built'    => $this->cast($get(['L_YearBuilt','YEARBUILT','YearBuilt']), 'int'),
            'property_type' => $this->cast($get(['L_Type_','PROPTYPE','PropertySubType','L_TypeText']), 'string') ?? 'Single Family',
            'listing_type'  => 'sale',  // RETS rental classes are usually separate
            'status'        => $this->mapStatus($get(['L_Status','STATUS','MlsStatus']) ?? ''),
            'description'   => $this->cast($get(['L_Remarks','REMARKS','PublicRemarks']), 'string'),
            'photos'        => json_encode($photos),
            'hoa_fee'       => $this->cast($get(['L_HOAFee','HOAFEE','AssociationFee']), 'float'),
            'garage'        => $this->cast($get(['L_Garage','GARAGESP','GarageSpaces']), 'int'),
            'mls_id'        => $this->cast($mlsId, 'string'),
            'source'        => 'mls',
        ];
    }

    /**
     * Fetch photo URLs for a single listing from the RETS GetObject transaction.
     * Returns an array of absolute URLs.
     *
     * Call this after normalise() if you need photos, and store the results
     * in the 'photos' column as a JSON array of URLs.
     */
    public function fetchPhotos(string $mlsId): array {
        if (!$this->rets) {
            throw new RuntimeException('RETS: call connect() first.');
        }

        $result = $this->rets->GetObject($this->retsResource, 'Photo', $mlsId, '*', 0);
        $urls   = [];
        if ($result && is_array($result)) {
            foreach ($result as $obj) {
                if (!empty($obj['Data'])) {
                    // $obj['Data'] is raw image bytes — save to disk and store path
                    $filename = 'mls_' . preg_replace('/[^a-z0-9]/i', '_', $mlsId) . '_' . count($urls) . '.jpg';
                    $dest     = UPLOAD_DIR . $filename;
                    file_put_contents($dest, $obj['Data']);
                    $urls[] = $filename;
                } elseif (!empty($obj['Location'])) {
                    // Some servers provide URL instead of binary
                    $urls[] = $obj['Location'];
                }
            }
        }
        return $urls;
    }

    public function disconnect(): void {
        if ($this->rets) {
            $this->rets->Disconnect();
            $this->rets = null;
        }
    }

    private function mapStatus(string $s): string {
        $s = strtoupper(trim($s));
        return match($s) {
            'A', 'ACT', 'ACTIVE'   => 'active',
            'P', 'PEND', 'PENDING' => 'pending',
            'S', 'SOLD', 'CLS','C' => 'sold',
            default                => 'off-market',
        };
    }
}
