<?php
/**
 * functions/user_functions.php
 * -----------------------------------------------------------------------
 * All database access for the User Management module lives here, mirroring
 * the pattern established in functions/vessel_functions.php. Every query
 * uses prepared statements. Server-side role checks (require_admin()) live
 * in includes/auth.php and must be called by every page/endpoint that
 * requires these functions — this file does not enforce authorization
 * itself, it only talks to the database.
 * -----------------------------------------------------------------------
 */

require_once __DIR__ . '/../../config/database.php';

/**
 * ADMIN: full user list for the User Management DataTable.
 * (Client-side search/sort/pagination via DataTables, same approach as
 * the Vessel module — fine for a staff-sized user list.)
 */
function get_all_users(): array
{
    $pdo = get_db();
    return $pdo->query(
        "SELECT id, profile_image, username, full_name, email, contact_number,
                user_access, status, created_at, updated_at
         FROM users
         ORDER BY created_at DESC"
    )->fetchAll();
}

/** Single user record by ID (excludes the password hash). */
function get_user(int $id): ?array
{
    $pdo = get_db();
    $stmt = $pdo->prepare(
        'SELECT id, profile_image, username, full_name, email, contact_number,
                user_access, status, created_at, updated_at
         FROM users WHERE id = :id'
    );
    $stmt->execute([':id' => $id]);
    $user = $stmt->fetch();
    return $user ?: null;
}

/** True if a username is already taken (optionally excluding one user ID — for edit forms). */
function username_exists(string $username, ?int $excludeId = null): bool
{
    $pdo = get_db();
    $sql = 'SELECT COUNT(*) FROM users WHERE username = :username';
    $params = [':username' => $username];
    if ($excludeId !== null) {
        $sql .= ' AND id != :id';
        $params[':id'] = $excludeId;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn() > 0;
}

/** True if an email is already taken (optionally excluding one user ID). */
function email_exists(string $email, ?int $excludeId = null): bool
{
    $pdo = get_db();
    $sql = 'SELECT COUNT(*) FROM users WHERE email = :email';
    $params = [':email' => $email];
    if ($excludeId !== null) {
        $sql .= ' AND id != :id';
        $params[':id'] = $excludeId;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn() > 0;
}

/** Insert a new user. $data['password'] must already be hashed. Returns the new user's ID. */
function create_user(array $data): int
{
    $pdo = get_db();
    $stmt = $pdo->prepare(
        'INSERT INTO users (profile_image, username, password, full_name, email, contact_number, user_access, status)
         VALUES (:profile_image, :username, :password, :full_name, :email, :contact_number, :user_access, :status)'
    );
    $stmt->execute([
        ':profile_image'   => $data['profile_image'] ?? null,
        ':username'        => $data['username'],
        ':password'        => $data['password'],
        ':full_name'       => $data['full_name'],
        ':email'           => $data['email'],
        ':contact_number'  => $data['contact_number'] ?? null,
        ':user_access'     => $data['user_access'] ?? 'user',
        ':status'          => $data['status'] ?? 'active',
    ]);
    return (int) $pdo->lastInsertId();
}

/**
 * Update a user's profile fields (not password — see change_user_password()).
 * Pass $data['profile_image'] only when a new image was uploaded; omit the
 * key entirely to leave the existing image untouched.
 */
function update_user(int $id, array $data): bool
{
    $pdo = get_db();

    $setParts = [
        'username = :username',
        'full_name = :full_name',
        'email = :email',
        'contact_number = :contact_number',
        'user_access = :user_access',
        'status = :status',
    ];
    $params = [
        ':username'       => $data['username'],
        ':full_name'      => $data['full_name'],
        ':email'          => $data['email'],
        ':contact_number' => $data['contact_number'] ?? null,
        ':user_access'    => $data['user_access'] ?? 'user',
        ':status'         => $data['status'] ?? 'active',
        ':id'             => $id,
    ];

    if (array_key_exists('profile_image', $data)) {
        $setParts[] = 'profile_image = :profile_image';
        $params[':profile_image'] = $data['profile_image'];
    }

    $sql = 'UPDATE users SET ' . implode(', ', $setParts) . ' WHERE id = :id';
    $stmt = $pdo->prepare($sql);
    return $stmt->execute($params);
}

/** Update just the password (already hashed by the caller). */
function change_user_password(int $id, string $hashedPassword): bool
{
    $pdo = get_db();
    $stmt = $pdo->prepare('UPDATE users SET password = :password WHERE id = :id');
    return $stmt->execute([':password' => $hashedPassword, ':id' => $id]);
}

/** Set a user's status to 'active' or 'inactive'. */
function set_user_status(int $id, string $status): bool
{
    $status = $status === 'inactive' ? 'inactive' : 'active';
    $pdo = get_db();
    $stmt = $pdo->prepare('UPDATE users SET status = :status WHERE id = :id');
    return $stmt->execute([':status' => $status, ':id' => $id]);
}

/** Delete a user record. Returns false if the ID didn't exist. */
function delete_user(int $id): bool
{
    $pdo = get_db();
    $stmt = $pdo->prepare('DELETE FROM users WHERE id = :id');
    $stmt->execute([':id' => $id]);
    return $stmt->rowCount() > 0;
}

/** Get just the profile_image filename for a user (used before overwrite/delete). */
function get_user_profile_image(int $id): ?string
{
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT profile_image FROM users WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $image = $stmt->fetchColumn();
    return $image !== false && $image !== null ? $image : null;
}

/** How many accounts currently have 'administrator' access — used to block removing the last one. */
function count_active_administrators(?int $excludeId = null): int
{
    $pdo = get_db();
    $sql = "SELECT COUNT(*) FROM users WHERE user_access = 'administrator' AND status = 'active'";
    $params = [];
    if ($excludeId !== null) {
        $sql .= ' AND id != :id';
        $params[':id'] = $excludeId;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
}

/** Total user count, for the dashboard stat card. */
function count_all_users(): int
{
    return (int) get_db()->query('SELECT COUNT(*) FROM users')->fetchColumn();
}
