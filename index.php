<?php
/**
 * A/B test router — assigns visitors 50/50 to v1 (elegant dark site)
 * or v2 (zillow-style site) and keeps them on the same version via cookie.
 */
$version = $_COOKIE['ab_version'] ?? null;

if (!$version || !in_array($version, ['a', 'b'], true)) {
    $version = (mt_rand(0, 1) === 0) ? 'a' : 'b';
    setcookie('ab_version', $version, time() + 30 * 24 * 3600, '/', '', true, true);
}

header('Location: ' . ($version === 'a' ? '/v1/' : '/v2/'));
exit;
