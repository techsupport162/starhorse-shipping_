<?php
/**
 * functions/login_throttle_functions.php
 * -----------------------------------------------------------------------
 * Brute-force protection for login.php. Failed sign-ins are stored in the
 * `login_attempts` table (database/migration_004_login_attempts.sql) —
 * not the session — so clearing cookies doesn't reset the counter.
 *
 * Two limits, both over a rolling LOGIN_THROTTLE_WINDOW:
 *   - per username + IP: stops guessing one account's password
 *   - per IP (any username): stops cycling through usernames
 * Keyed on IP as well as username so an attacker can't lock the real
 * administrator out from somewhere else just by typing their username.
 * -----------------------------------------------------------------------
 */

require_once __DIR__ . '/../../config/database.php';

const LOGIN_THROTTLE_WINDOW          = 900; // seconds (15 minutes)
const LOGIN_THROTTLE_MAX_PER_ACCOUNT = 5;
const LOGIN_THROTTLE_MAX_PER_IP      = 20;

/** The client's IP. REMOTE_ADDR only — X-Forwarded-For is client-controlled and trivially spoofed. */
function login_client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 0, 45);
}

/** Normalised username key, so "Admin" and "admin " count as the same account. */
function login_throttle_username(string $username): string
{
    return mb_substr(mb_strtolower(trim($username)), 0, 100);
}

/**
 * Seconds until this username/IP may try again, or 0 if not blocked.
 * All time maths happens in the database (NOW()) so a PHP/DB timezone
 * mismatch on the host can't skew the lockout.
 */
function login_throttle_seconds_remaining(string $username): int
{
    $ip = login_client_ip();

    $account = login_throttle_window_stats(
        'username = :u AND ip_address = :ip',
        [':u' => login_throttle_username($username), ':ip' => $ip]
    );
    $perIp = login_throttle_window_stats('ip_address = :ip', [':ip' => $ip]);

    $remaining = 0;
    if ($account['n'] >= LOGIN_THROTTLE_MAX_PER_ACCOUNT) {
        $remaining = max($remaining, $account['remaining']);
    }
    if ($perIp['n'] >= LOGIN_THROTTLE_MAX_PER_IP) {
        $remaining = max($remaining, $perIp['remaining']);
    }

    return max(0, $remaining);
}

/**
 * Failure count inside the window for rows matching $where, plus seconds
 * until the oldest of them ages out (which is when the block lifts).
 *
 * @return array{n: int, remaining: int}
 */
function login_throttle_window_stats(string $where, array $params): array
{
    // PostgreSQL date arithmetic:
    //   TIMESTAMPDIFF(SECOND, NOW(), expr)  → EXTRACT(EPOCH FROM (expr - NOW()))
    //   INTERVAL :param SECOND              → (:param * INTERVAL '1 second')
    $stmt = get_db()->prepare(
        "SELECT COUNT(*) AS n,
                COALESCE(
                    EXTRACT(EPOCH FROM (MIN(attempted_at) + (:w1 * INTERVAL '1 second') - NOW()))::int,
                    0
                ) AS remaining
         FROM login_attempts
         WHERE $where
           AND attempted_at > NOW() - (:w2 * INTERVAL '1 second')"
    );
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':w1', LOGIN_THROTTLE_WINDOW, PDO::PARAM_INT);
    $stmt->bindValue(':w2', LOGIN_THROTTLE_WINDOW, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch();

    return ['n' => (int) $row['n'], 'remaining' => (int) $row['remaining']];
}

/** Record one failed sign-in, and prune rows old enough to no longer matter. */
function login_throttle_record_failure(string $username): void
{
    $pdo = get_db();

    $stmt = $pdo->prepare('INSERT INTO login_attempts (username, ip_address) VALUES (:u, :ip)');
    $stmt->execute([':u' => login_throttle_username($username), ':ip' => login_client_ip()]);

    $pdo->exec("DELETE FROM login_attempts WHERE attempted_at < NOW() - INTERVAL '1 day'");
}

/** Successful sign-in: forget this username/IP's failures so typos earlier don't count against them later. */
function login_throttle_clear(string $username): void
{
    $stmt = get_db()->prepare('DELETE FROM login_attempts WHERE username = :u AND ip_address = :ip');
    $stmt->execute([':u' => login_throttle_username($username), ':ip' => login_client_ip()]);
}
