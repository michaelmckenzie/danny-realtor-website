<?php
/**
 * mls/ResoAdapter.php
 *
 * Connector for the RESO Web API (the modern MLS standard, OAuth 2.0 + OData/JSON).
 * Most major MLS boards now provide a RESO-compliant endpoint.
 * Popular middleware providers: MLSGrid, Spark API, Bridge Interactive, Trestle.
 *
 * Setup requirements (config.php):
 *   MLS_PROVIDER       = 'reso'
 *   MLS_ENDPOINT       = 'https://api.mlsgrid.com/v2'
 *   MLS_CLIENT_ID      = 'your_client_id'
 *   MLS_CLIENT_SECRET  = 'your_client_secret'
 *   MLS_TOKEN_URL      = 'https://api.mlsgrid.com/oauth2/token'
 *   MLS_ENABLED        = true
 *
 * Documentation:
 *   RESO standard: https://www.reso.org/reso-web-api/
 *   MLSGrid docs:  https://docs.mlsgrid.com/
 *   Spark API:     https://sparkplatform.com/docs
 *   Trestle:       https://trestle.corelogic.com/
 */

require_once __DIR__ . '/MlsConnector.php';

class ResoAdapter extends MlsConnector {

    private ?string $accessToken = null;

    /**
     * Authenticate via OAuth 2.0 Client Credentials grant and store the access token.
     */
    public function connect(): void {
        if (empty($this->config['client_id']) || empty($this->config['client_secret'])) {
            throw new RuntimeException('RESO: MLS_CLIENT_ID and MLS_CLIENT_SECRET must be set in config.php');
        }
        if (empty($this->config['token_url'])) {
            throw new RuntimeException('RESO: MLS_TOKEN_URL must be set in config.php');
        }

        $ch = curl_init($this->config['token_url']);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS     => http_build_query([
                'grant_type'    => 'client_credentials',
                'client_id'     => $this->config['client_id'],
                'client_secret' => $this->config['client_secret'],
                'scope'         => 'api',   // adjust scope per your provider
            ]),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_TIMEOUT        => 30,
        ]);

        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code !== 200) {
            throw new RuntimeException("RESO: Token request failed (HTTP $code): $body");
        }

        $json = json_decode($body, true);
        if (empty($json['access_token'])) {
            throw new RuntimeException('RESO: No access_token in OAuth response.');
        }

        $this->accessToken = $json['access_token'];
    }

    /**
     * Fetch listings via the RESO OData endpoint.
     *
     * @param  array $query   OData $filter fragments, e.g. ['StandardStatus' => 'Active']
     * @param  int   $limit   OData $top value
     * @param  int   $offset  OData $skip value
     * @return array Raw associative arrays from the API
     */
    public function fetchListings(array $query = [], int $limit = 500, int $offset = 0): array {
        if (!$this->accessToken) {
            throw new RuntimeException('RESO: call connect() before fetchListings()');
        }

        // Build OData filter string
        $filters = [];
        foreach ($query as $field => $value) {
            // Quote string values; leave numerics bare
            $filters[] = is_string($value)
                ? "$field eq '$value'"
                : "$field eq $value";
        }
        $odata = [
            '$top'    => $limit,
            '$skip'   => $offset,
            '$select' => implode(',', $this->resoFields()),
        ];
        if ($filters) {
            $odata['$filter'] = implode(' and ', $filters);
        }

        // Most RESO endpoints use /Property as the resource
        $url = rtrim($this->config['endpoint'], '/') . '/Property?' . http_build_query($odata);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->accessToken,
                'Accept: application/json',
            ],
            CURLOPT_TIMEOUT        => 60,
        ]);

        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code !== 200) {
            throw new RuntimeException("RESO: Property fetch failed (HTTP $code): " . substr($body, 0, 500));
        }

        $data = json_decode($body, true);
        // RESO OData response wraps results in 'value'
        return $data['value'] ?? [];
    }

    /**
     * Map a RESO Property resource record to our database schema.
     * Field names follow RESO Data Dictionary 1.7+.
     * Adjust the field names on the right side to match your specific MLS board.
     */
    public function normalise(array $raw): array {
        // Photos: RESO uses Media resource; some providers embed MediaURL array inline
        $photos = [];
        if (!empty($raw['Media']) && is_array($raw['Media'])) {
            foreach ($raw['Media'] as $m) {
                if (!empty($m['MediaURL'])) {
                    $photos[] = $m['MediaURL']; // these are absolute URLs from the MLS
                }
            }
        } elseif (!empty($raw['PhotosCount']) && !empty($raw['MediaURL'])) {
            $photos[] = $raw['MediaURL'];
        }

        return [
            'address'       => $this->cast($raw['UnparsedAddress']  ?? ($raw['StreetNumber'] . ' ' . $raw['StreetName']), 'string'),
            'city'          => $this->cast($raw['City']             ?? null, 'string'),
            'state'         => $this->cast($raw['StateOrProvince']  ?? null, 'string'),
            'zip'           => $this->cast($raw['PostalCode']        ?? null, 'string'),
            'price'         => $this->cast($raw['ListPrice']         ?? $raw['ClosePrice'] ?? 0, 'float'),
            'beds'          => $this->cast($raw['BedroomsTotal']     ?? null, 'int'),
            'baths'         => $this->cast($raw['BathroomsTotalInteger'] ?? $raw['BathroomsFull'] ?? null, 'float'),
            'sqft'          => $this->cast($raw['LivingArea']        ?? null, 'int'),
            'lot_sqft'      => $this->cast($raw['LotSizeSquareFeet'] ?? null, 'int'),
            'year_built'    => $this->cast($raw['YearBuilt']         ?? null, 'int'),
            'property_type' => $this->cast($raw['PropertySubType']   ?? $raw['PropertyType'] ?? 'Single Family', 'string'),
            'listing_type'  => ($raw['MlsStatus'] ?? '') === 'For Rent' ? 'rent' : 'sale',
            'status'        => $this->mapStatus($raw['StandardStatus'] ?? $raw['MlsStatus'] ?? ''),
            'description'   => $this->cast($raw['PublicRemarks']    ?? null, 'string'),
            'photos'        => json_encode($photos),
            'hoa_fee'       => $this->cast($raw['AssociationFee']   ?? null, 'float'),
            'garage'        => $this->cast($raw['GarageSpaces']      ?? null, 'int'),
            'mls_id'        => $this->cast($raw['ListingKey']        ?? $raw['ListingId'] ?? null, 'string'),
            'source'        => 'mls',
        ];
    }

    /**
     * Map RESO StandardStatus to our status enum values.
     */
    private function mapStatus(string $s): string {
        return match(strtolower($s)) {
            'active', 'active under contract' => 'active',
            'pending'                          => 'pending',
            'closed', 'sold'                   => 'sold',
            default                            => 'off-market',
        };
    }

    /**
     * The RESO fields we request — reduce this to what your MLS board allows.
     */
    private function resoFields(): array {
        return [
            'ListingKey','ListingId','ListPrice','ClosePrice','UnparsedAddress',
            'StreetNumber','StreetName','City','StateOrProvince','PostalCode',
            'BedroomsTotal','BathroomsTotalInteger','BathroomsFull',
            'LivingArea','LotSizeSquareFeet','YearBuilt',
            'PropertyType','PropertySubType','MlsStatus','StandardStatus',
            'PublicRemarks','AssociationFee','GarageSpaces','Media','MediaURL','PhotosCount',
        ];
    }
}
