<?php
/**
 * includes/db.php — PDO database connection singleton
 */

require_once __DIR__ . '/../config.php';

function db(): PDO {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }
    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        DB_HOST, DB_NAME, DB_CHARSET
    );
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        // In production, log the real error; show a generic message to visitors
        error_log('Database connection failed: ' . $e->getMessage());
        http_response_code(503);
        exit('<h1>Service temporarily unavailable</h1><p>Please try again shortly.</p>');
    }
    return $pdo;
}
