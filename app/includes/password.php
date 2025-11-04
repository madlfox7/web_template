<?php
/**
 * Minimal password utilities for the project.
 * - Uses server-side PEPPER from env var PASSWORD_PEPPER (optional but recommended)
 * - Uses Argon2id when available, falls back to PASSWORD_DEFAULT
 * - Provides hash, verify and rehash helpers
 */

$PEPPER = getenv('PASSWORD_PEPPER') ?: '';

function get_argon_options(): array {
    // Tunable parameters — adjust for your environment
    return [
        'memory_cost' => 1 << 16, // 64 MB
        'time_cost'   => 4,
        'threads'     => 2,
    ];
}

function hash_password(string $password): string {
    global $PEPPER;
    $input = $PEPPER . $password;
    if (defined('PASSWORD_ARGON2ID')) {
        return password_hash($input, PASSWORD_ARGON2ID, get_argon_options());
    }
    // Fallback (bcrypt or PHP default)
    return password_hash($input, PASSWORD_DEFAULT);
}

function verify_password(string $password, string $hash): bool {
    global $PEPPER;
    return password_verify($PEPPER . $password, $hash);
}

function needs_rehash(string $hash): bool {
    if (defined('PASSWORD_ARGON2ID')) {
        return password_needs_rehash($hash, PASSWORD_ARGON2ID, get_argon_options());
    }
    return password_needs_rehash($hash, PASSWORD_DEFAULT);
}

// End
