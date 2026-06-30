-- ============================================================
-- setup.sql — Run this once to initialise the database.
-- mysql -u your_user -p your_db_name < setup.sql
-- ============================================================

SET NAMES utf8mb4;
SET foreign_key_checks = 0;

-- ─── Properties ───────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `properties` (
  `id`             INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `address`        VARCHAR(255)     NOT NULL,
  `city`           VARCHAR(100)     NOT NULL,
  `state`          VARCHAR(50)      NOT NULL,
  `zip`            VARCHAR(20)      NOT NULL,
  `price`          DECIMAL(14,2)    NOT NULL,
  `beds`           TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `baths`          DECIMAL(4,1)     NOT NULL DEFAULT 0,
  `sqft`           INT UNSIGNED     NULL,
  `lot_sqft`       INT UNSIGNED     NULL,
  `year_built`     SMALLINT         NULL,
  `property_type`  VARCHAR(50)      NOT NULL DEFAULT 'Single Family',
  `listing_type`   ENUM('sale','rent') NOT NULL DEFAULT 'sale',
  `status`         ENUM('active','pending','sold','off-market') NOT NULL DEFAULT 'active',
  `description`    TEXT             NULL,
  `photos`         JSON             NULL COMMENT 'JSON array of relative image paths',
  `hoa_fee`        DECIMAL(10,2)    NULL,
  `garage`         TINYINT UNSIGNED NULL COMMENT 'number of garage spaces',
  `mls_id`         VARCHAR(100)     NULL COMMENT 'MLS listing number (NULL for manual entries)',
  `source`         ENUM('manual','mls') NOT NULL DEFAULT 'manual',
  `created_at`     TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_status`       (`status`),
  INDEX `idx_listing_type` (`listing_type`),
  INDEX `idx_city_state`   (`city`, `state`),
  INDEX `idx_price`        (`price`),
  INDEX `idx_source`       (`source`),
  UNIQUE KEY `uk_mls_id`   (`mls_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Admin sessions (optional extra security table) ─────────────────────
CREATE TABLE IF NOT EXISTS `admin_sessions` (
  `token`       CHAR(64)     NOT NULL,
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at`  TIMESTAMP    NOT NULL,
  PRIMARY KEY (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── MLS sync log ─────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `mls_sync_log` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `started_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `finished_at` TIMESTAMP    NULL,
  `status`      ENUM('running','success','error') NOT NULL DEFAULT 'running',
  `added`       INT UNSIGNED NOT NULL DEFAULT 0,
  `updated`     INT UNSIGNED NOT NULL DEFAULT 0,
  `removed`     INT UNSIGNED NOT NULL DEFAULT 0,
  `message`     TEXT         NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Sample listings (delete or replace with real data) ───────────────────
INSERT INTO `properties`
  (`address`, `city`, `state`, `zip`, `price`, `beds`, `baths`, `sqft`, `lot_sqft`,
   `year_built`, `property_type`, `listing_type`, `status`, `description`, `photos`, `garage`)
VALUES
(
  '142 Sunset Drive', 'Beverly Hills', 'CA', '90210',
  1850000, 4, 3.5, 3200, 7500, 2005, 'Single Family', 'sale', 'active',
  'Stunning modern home nestled in the heart of Beverly Hills. This beautifully updated property features an open floor plan, chef''s kitchen with quartz countertops, spa-like master bath, and a private pool and spa. Perfect for entertaining.',
  '[]', 2
),
(
  '87 Ocean View Blvd', 'Santa Monica', 'CA', '90401',
  3200000, 5, 4.0, 4500, 9200, 2018, 'Single Family', 'sale', 'active',
  'Rare ocean-view estate with breathtaking panoramic views of the Pacific. Features include floor-to-ceiling windows, gourmet kitchen, home theatre, and a rooftop terrace perfect for sunset watching.',
  '[]', 3
),
(
  '301 Maple Court, Unit 4B', 'Pasadena', 'CA', '91101',
  685000, 2, 2.0, 1250, NULL, 2015, 'Condo', 'sale', 'active',
  'Light-filled condo in a sought-after Pasadena community. Updated kitchen, in-unit laundry, and a private balcony overlooking the courtyard garden. Walk to Old Town dining and the Metro Gold Line.',
  '[]', 1
),
(
  '555 Hillcrest Avenue', 'Glendale', 'CA', '91206',
  920000, 3, 2.5, 2100, 5400, 1998, 'Single Family', 'sale', 'pending',
  'Charming traditional home with original character and modern upgrades throughout. Gorgeous hardwood floors, updated bathrooms, and a beautifully landscaped backyard with fruit trees.',
  '[]', 2
),
(
  '1024 Lakeshore Road', 'Burbank', 'CA', '91505',
  3800, 3, 2.0, 1800, NULL, 2010, 'Single Family', 'rent', 'active',
  'Spacious rental home in a quiet Burbank neighborhood. Recently remodeled with new appliances, freshly painted interior, and a large private backyard. Excellent Burbank Unified school district.',
  '[]', 2
),
(
  '29 Garden View Lane', 'Culver City', 'CA', '90230',
  1100000, 3, 3.0, 1950, 3200, 2020, 'Townhouse', 'sale', 'active',
  'Brand-new luxury townhome in the heart of Culver City''s thriving tech corridor. Rooftop deck with city views, smart home features, EV charging in private garage, and walkable to Amazon Studios.',
  '[]', 1
);

SET foreign_key_checks = 1;
