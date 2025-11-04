<?php
// Demo: create and verify password hashes, measure time and algorithm info
require __DIR__ . '/../app/includes/password.php';

$password = $argv[1] ?? 'Password123!';
$runs = 3;

echo "Password demo for: [{$password}]\n";

for ($i = 0; $i < $runs; $i++) {
    $t0 = microtime(true);
    $hash = hash_password($password);
    $t1 = microtime(true);
    $info = password_get_info($hash);
    $time_ms = round(($t1 - $t0) * 1000, 2);
    echo sprintf("Run %d: algorithm=%s time=%sms hash_len=%d\n", $i+1, $info['algoName'] ?: $info['algo'], $time_ms, strlen($hash));
}

// Verify
$ok = verify_password($password, $hash) ? 'OK' : 'FAIL';
echo "Verify last hash: {$ok}\n";

// Need rehash?
$needs = needs_rehash($hash) ? 'yes' : 'no';
echo "Needs rehash with current options? {$needs}\n";

// Show a short sample of the hash (don't print secrets in production)
echo "Sample hash (first 64 chars): " . substr($hash, 0, 64) . "...\n";

// Quick advice based on timing
if ($time_ms < 50) {
    echo "Note: hashing is very fast (<50ms). Consider increasing memory_cost/time_cost for Argon2 if you have enough RAM/CPU.\n";
} elseif ($time_ms > 500) {
    echo "Note: hashing is slow (>500ms). This may impact login latency; tune down if needed.\n";
} else {
    echo "Hashing timing looks reasonable for interactive logins.\n";
}
