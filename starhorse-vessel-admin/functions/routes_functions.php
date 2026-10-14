<?php
/**
 * functions/routes_functions.php
 * -----------------------------------------------------------------------
 * All database access for the Routes Gallery Management module lives
 * here. Both the public site (sections/routes.php, read-only) and the
 * admin panel (full CRUD) include this file. Every query uses prepared
 * statements.
 *
 * Two tables, two shapes:
 *   - route_destinations — one row per destination shown in the public
 *     carousel. Like `fares` (and unlike `vessels`/`vessel_images`), each
 *     row stores exactly one image directly on the row
 *     (`destination_image`) — no child table is needed.
 *   - route_banner — a singleton row (id is always 1) holding the hero
 *     image shown above the carousel. get_route_banner_row() and
 *     update_route_banner()/clear_route_banner() always target id = 1.
 * -----------------------------------------------------------------------
 */

require_once __DIR__ . '/../../config/database.php';

/**
 * PUBLIC SITE: all published destinations that have an uploaded image,
 * ordered for display. A published row with no image yet is intentionally
 * excluded — an empty tile would look broken in the carousel — so an
 * administrator can stage a destination (name + published) before its
 * photo is ready without it appearing on the live site.
 */
function get_published_destinations(): array
{
    $pdo = get_db();

    return $pdo->query(
        "SELECT id, location_name, destination_image
         FROM route_destinations
         WHERE status = 'published' AND destination_image IS NOT NULL
         ORDER BY sort_order ASC, location_name ASC"
    )->fetchAll();
}

/**
 * PUBLIC SITE: the current hero/banner image filename, or null if none
 * has been uploaded yet.
 */
function get_route_banner(): ?string
{
    $pdo = get_db();
    $filename = $pdo->query('SELECT banner_image FROM route_banner WHERE id = 1')->fetchColumn();
    return $filename !== false && $filename !== null ? $filename : null;
}

/**
 * ADMIN: paginated, searchable, sortable destination list.
 *
 * @return array{rows: array, total: int}
 */
function get_destinations_for_admin(string $search = '', int $limit = 10, int $offset = 0, string $orderBy = 'created_at', string $orderDir = 'DESC'): array
{
    $pdo = get_db();

    $allowedOrderBy = ['location_name', 'status', 'sort_order', 'created_at', 'updated_at'];
    if (!in_array($orderBy, $allowedOrderBy, true)) {
        $orderBy = 'created_at';
    }
    $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';

    $where  = '';
    $params = [];
    if ($search !== '') {
        $where = 'WHERE location_name LIKE :search';
        $params[':search'] = '%' . $search . '%';
    }

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM route_destinations $where");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $sql = "SELECT id, location_name, destination_image, status, sort_order, created_at, updated_at
            FROM route_destinations
            $where
            ORDER BY $orderBy $orderDir
            LIMIT :limit OFFSET :offset";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    return ['rows' => $stmt->fetchAll(), 'total' => $total];
}

/** Single destination row, for View/Edit. */
function get_destination(int $id): ?array
{
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT * FROM route_destinations WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Case-insensitive duplicate check on location_name, mirroring
 * fare_route_exists() in functions/fare_functions.php. Pass $excludeId
 * when checking during an update so a route doesn't collide with itself.
 */
function destination_location_exists(string $locationName, ?int $excludeId = null): bool
{
    $pdo = get_db();

    $sql = 'SELECT COUNT(*) FROM route_destinations WHERE LOWER(location_name) = LOWER(:location_name)';
    $params = [':location_name' => $locationName];

    if ($excludeId !== null) {
        $sql .= ' AND id != :exclude_id';
        $params[':exclude_id'] = $excludeId;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return ((int) $stmt->fetchColumn()) > 0;
}

/**
 * Insert a new destination. sort_order defaults to "append to the end"
 * (MAX + 1), the same convention used by create_faq() in
 * functions/faq_functions.php, so newly added destinations land at the
 * end of the carousel instead of jumping to the front.
 * Returns the new destination's ID.
 */
function create_destination(array $data): int
{
    $pdo = get_db();

    $nextSortOrder = (int) $pdo->query('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM route_destinations')->fetchColumn();

    $stmt = $pdo->prepare(
        'INSERT INTO route_destinations (location_name, status, sort_order)
         VALUES (:location_name, :status, :sort_order)'
    );
    $stmt->execute([
        ':location_name' => $data['location_name'],
        ':status'        => $data['status'] ?? 'published',
        ':sort_order'    => $data['sort_order'] ?? $nextSortOrder,
    ]);

    return (int) $pdo->lastInsertId();
}

/** Update a destination's core fields (location_name, status). Image is handled separately via update_destination_image(). */
function update_destination(int $id, array $data): bool
{
    $pdo = get_db();

    $stmt = $pdo->prepare(
        'UPDATE route_destinations SET
            location_name = :location_name,
            status = :status
         WHERE id = :id'
    );

    return $stmt->execute([
        ':location_name' => $data['location_name'],
        ':status'        => $data['status'] ?? 'published',
        ':id'            => $id,
    ]);
}

/**
 * Set (or clear, with $filename = null) a destination's image. Kept as
 * its own function — like update_fare_image() — so the ajax layer can
 * handle "keep / replace / remove" independently from the text fields.
 */
function update_destination_image(int $id, ?string $filename): bool
{
    $pdo = get_db();
    $stmt = $pdo->prepare('UPDATE route_destinations SET destination_image = :image WHERE id = :id');
    return $stmt->execute([':image' => $filename, ':id' => $id]);
}

/** Delete a destination and remove its image file from disk. */
function delete_destination(int $id): bool
{
    $pdo = get_db();

    $stmt = $pdo->prepare('SELECT destination_image FROM route_destinations WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $existing = $stmt->fetch();

    if (!$existing) {
        return false;
    }

    $del = $pdo->prepare('DELETE FROM route_destinations WHERE id = :id');
    $result = $del->execute([':id' => $id]);

    if ($result && !empty($existing['destination_image'])) {
        delete_uploaded_image_file($existing['destination_image'], 'routes');
    }

    return $result;
}

/** Simple dashboard stat: total destination count. */
function count_all_destinations(): int
{
    return (int) get_db()->query('SELECT COUNT(*) FROM route_destinations')->fetchColumn();
}

/**
 * ADMIN: the banner row (always id = 1 — see route_banner's singleton
 * CHECK constraint in database/migration_003_routes.sql). Used to
 * pre-fill the Routes Hero Management screen and to know the previous
 * filename so it can be deleted on replace.
 */
function get_route_banner_row(): array
{
    $pdo = get_db();
    $stmt = $pdo->query('SELECT id, banner_image, updated_at FROM route_banner WHERE id = 1');
    $row = $stmt->fetch();

    // The seed migration guarantees this row exists, but fall back
    // gracefully rather than returning null if it was ever deleted by hand.
    return $row ?: ['id' => 1, 'banner_image' => null, 'updated_at' => null];
}

/** Replace the banner image filename. */
function update_route_banner(string $filename): bool
{
    $pdo = get_db();
    $stmt = $pdo->prepare('UPDATE route_banner SET banner_image = :image WHERE id = 1');
    return $stmt->execute([':image' => $filename]);
}

/** Clear the banner image (row itself is never deleted — it's a singleton). */
function clear_route_banner(): bool
{
    $pdo = get_db();
    $stmt = $pdo->prepare('UPDATE route_banner SET banner_image = NULL WHERE id = 1');
    return $stmt->execute();
}