<?php
/**
 * mls/MlsConnector.php
 *
 * Abstract base class for MLS feed connectors.
 * Extend this class with either RetsAdapter or ResoAdapter (or write your own).
 *
 * Usage:
 *   $mls = new ResoAdapter();
 *   $mls->connect();
 *   $listings = $mls->fetchListings(['Status' => 'Active']);
 *   foreach ($listings as $raw) {
 *       $normalised = $mls->normalise($raw);
 *       // upsert into DB ...
 *   }
 *   $mls->disconnect();
 */

abstract class MlsConnector {

    /** @var array Configuration pulled from config.php constants */
    protected array $config = [];

    public function __construct() {
        $this->config = [
            'endpoint'      => MLS_ENDPOINT,
            'api_key'       => MLS_API_KEY,
            'client_id'     => MLS_CLIENT_ID,
            'client_secret' => MLS_CLIENT_SECRET,
            'token_url'     => MLS_TOKEN_URL,
        ];
    }

    /**
     * Authenticate with the MLS server.
     * Must be called before fetchListings().
     * Should throw RuntimeException on failure.
     */
    abstract public function connect(): void;

    /**
     * Fetch listings from the MLS.
     *
     * @param  array  $query  Provider-specific filter parameters
     *                        e.g. ['Status' => 'Active', 'StandardStatus' => 'Active']
     * @param  int    $limit  Max listings per call (default 500)
     * @param  int    $offset Pagination offset
     * @return array          Raw provider data rows (associative arrays)
     */
    abstract public function fetchListings(array $query = [], int $limit = 500, int $offset = 0): array;

    /**
     * Normalise a single raw MLS row into our database schema.
     * Returns an array ready to INSERT/UPDATE into the `properties` table.
     */
    abstract public function normalise(array $raw): array;

    /**
     * Close the MLS connection / revoke session.
     * Called after all fetching is done.
     */
    public function disconnect(): void {
        // Default: no-op. Override if cleanup is needed.
    }

    /**
     * Convenience: fetch ALL active listings (handles pagination automatically).
     *
     * @param  array  $query  Extra filter params merged with defaults
     * @param  int    $batchSize Number of records per API call
     * @return array  All normalised property rows
     */
    public function fetchAllActive(array $query = [], int $batchSize = 500): array {
        $all    = [];
        $offset = 0;

        do {
            $batch = $this->fetchListings(
                array_merge(['StandardStatus' => 'Active'], $query),
                $batchSize,
                $offset
            );
            foreach ($batch as $raw) {
                $all[] = $this->normalise($raw);
            }
            $offset += count($batch);
        } while (count($batch) === $batchSize);

        return $all;
    }

    /**
     * Upsert a normalised property row into the local `properties` table.
     * Matches on mls_id — inserts new, updates existing.
     *
     * @param  PDO   $pdo
     * @param  array $row  Normalised row from normalise()
     * @return string 'inserted' | 'updated' | 'skipped'
     */
    public function upsert(PDO $pdo, array $row): string {
        if (empty($row['mls_id'])) return 'skipped';

        $existing = $pdo->prepare('SELECT id FROM properties WHERE mls_id = :mls_id');
        $existing->execute([':mls_id' => $row['mls_id']]);
        $found = $existing->fetchColumn();

        $cols = ['address','city','state','zip','price','beds','baths','sqft','lot_sqft',
                 'year_built','property_type','listing_type','status','description','photos',
                 'hoa_fee','garage','mls_id','source'];

        if ($found) {
            // Update
            $sets = implode(', ', array_map(fn($c) => "$c = :$c", $cols));
            $stmt = $pdo->prepare("UPDATE properties SET $sets WHERE mls_id = :mls_id");
        } else {
            // Insert
            $colList = implode(', ', $cols);
            $phList  = implode(', ', array_map(fn($c) => ":$c", $cols));
            $stmt    = $pdo->prepare("INSERT INTO properties ($colList) VALUES ($phList)");
        }

        $params = [];
        foreach ($cols as $c) {
            $params[":$c"] = $row[$c] ?? null;
        }
        $stmt->execute($params);

        return $found ? 'updated' : 'inserted';
    }

    /**
     * Safely cast a value to the type expected by our schema.
     */
    protected function cast(mixed $value, string $type): mixed {
        return match($type) {
            'int'    => $value !== null && $value !== '' ? (int)$value    : null,
            'float'  => $value !== null && $value !== '' ? (float)$value  : null,
            'string' => $value !== null ? trim((string)$value) : null,
            'json'   => is_array($value) ? json_encode($value) : ($value ?? '[]'),
            default  => $value,
        };
    }
}
