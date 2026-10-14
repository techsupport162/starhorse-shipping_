<?php
/**
 * functions/fare_functions.php
 * -----------------------------------------------------------------------
 * All database access for the Fare Management module lives here. Both the
 * public site (sections/rates.php, read-only) and the admin panel (full
 * CRUD) include this file. Every query uses prepared statements.
 *
 * Unlike Vessels (1-to-many gallery via vessel_images), each fare route
 * has exactly two images stored directly on the `fares` row:
 * passenger_fare_image and cargo_fare_image — so no child table is needed.
 *
 * A route no longer needs a second row for its reverse direction. Setting
 * `is_vice_versa = 1` on a row means it serves BOTH origin->destination
 * and destination->origin. See fare_route_exists() and find_fare_route()
 * for how the reverse direction is resolved.
 * -----------------------------------------------------------------------
 */

require_once __DIR__ . '/../../config/database.php';

/**
 * PUBLIC SITE: all published fare routes, ordered for display.
 */
function get_published_fares(): array
{
    $pdo = get_db();

    return $pdo->query(
        "SELECT id, origin, destination, passenger_fare_image, cargo_fare_image, is_vice_versa
         FROM fares
         WHERE status = 'published'
         ORDER BY sort_order ASC, origin ASC, destination ASC"
    )->fetchAll();
}

/**
 * ADMIN: paginated, searchable, sortable fare list.
 *
 * @return array{rows: array, total: int}
 */
function get_fares_for_admin(string $search = '', int $limit = 10, int $offset = 0, string $orderBy = 'created_at', string $orderDir = 'DESC'): array
{
    $pdo = get_db();

    $allowedOrderBy = ['origin', 'destination', 'status', 'created_at', 'updated_at'];
    if (!in_array($orderBy, $allowedOrderBy, true)) {
        $orderBy = 'created_at';
    }
    $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';

    $where  = '';
    $params = [];
    if ($search !== '') {
        $where = 'WHERE f.origin LIKE :search OR f.destination LIKE :search';
        $params[':search'] = '%' . $search . '%';
    }

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM fares f $where");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $sql = "SELECT f.id, f.origin, f.destination, f.passenger_fare_image, f.cargo_fare_image,
                   f.is_vice_versa, f.status, f.created_at, f.updated_at
            FROM fares f
            $where
            ORDER BY f.$orderBy $orderDir
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

/** Single fare route, for View/Edit. */
function get_fare(int $id): ?array
{
    $pdo = get_db();

    $stmt = $pdo->prepare('SELECT * FROM fares WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $fare = $stmt->fetch();

    return $fare ?: null;
}

/**
 * Case-insensitive check for whether a route (origin -> destination)
 * already exists, optionally excluding a given fare ID (used when
 * editing so a route doesn't collide with itself).
 *
 * A single fare row can now represent both directions of a route
 * (see `is_vice_versa`), so an exact duplicate is no longer only a
 * same-direction match. This also collides when:
 *   - the SAME direction already exists (always a duplicate), or
 *   - the REVERSE direction already exists and that existing row is
 *     flagged is_vice_versa = 1 (it already serves this direction too).
 *
 * A reverse row that is NOT vice-versa does not collide — e.g. having
 * "Balanacan -> Lucena" (not vice-versa) does not block someone from
 * separately adding "Lucena -> Balanacan" as its own, distinct route.
 */
function fare_route_exists(string $origin, string $destination, ?int $excludeId = null): bool
{
    $pdo = get_db();

    $sql = 'SELECT COUNT(*) FROM fares
            WHERE (
                (LOWER(origin) = LOWER(:origin) AND LOWER(destination) = LOWER(:destination))
                OR (
                    LOWER(origin) = LOWER(:destination2) AND LOWER(destination) = LOWER(:origin2)
                    AND is_vice_versa = 1
                )
            )';
    $params = [
        ':origin'      => $origin,
        ':destination' => $destination,
        ':origin2'     => $origin,
        ':destination2' => $destination,
    ];

    if ($excludeId !== null) {
        $sql .= ' AND id != :exclude_id';
        $params[':exclude_id'] = $excludeId;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return ((int) $stmt->fetchColumn()) > 0;
}

/**
 * Finds the single fare row that serves a given origin -> destination
 * pair, whichever direction it was originally saved as.
 *
 * Tries the direct match first; if nothing is found, falls back to the
 * reverse match but ONLY if that reverse row is flagged is_vice_versa,
 * matching the same rule used by fare_route_exists(). Used wherever the
 * backend needs to resolve a route server-side (e.g. admin duplicate
 * checks); the public site's own lookup is covered separately in
 * sections/rates.php.
 */
function find_fare_route(string $origin, string $destination): ?array
{
    $pdo = get_db();

    $stmt = $pdo->prepare(
        'SELECT * FROM fares WHERE LOWER(origin) = LOWER(:origin) AND LOWER(destination) = LOWER(:destination) LIMIT 1'
    );
    $stmt->execute([':origin' => $origin, ':destination' => $destination]);
    $fare = $stmt->fetch();
    if ($fare) {
        return $fare;
    }

    $stmt = $pdo->prepare(
        'SELECT * FROM fares
         WHERE LOWER(origin) = LOWER(:destination) AND LOWER(destination) = LOWER(:origin)
         AND is_vice_versa = 1
         LIMIT 1'
    );
    $stmt->execute([':origin' => $origin, ':destination' => $destination]);
    $fare = $stmt->fetch();

    return $fare ?: null;
}

/** Insert a new fare route. Returns the new fare's ID. */
function create_fare(array $data): int
{
    $pdo = get_db();

    $stmt = $pdo->prepare(
        'INSERT INTO fares (origin, destination, passenger_fare_image, cargo_fare_image, is_vice_versa, status)
         VALUES (:origin, :destination, :passenger_fare_image, :cargo_fare_image, :is_vice_versa, :status)'
    );
    $stmt->execute([
        ':origin'               => $data['origin'],
        ':destination'          => $data['destination'],
        ':passenger_fare_image' => $data['passenger_fare_image'] ?? null,
        ':cargo_fare_image'     => $data['cargo_fare_image'] ?? null,
        ':is_vice_versa'        => !empty($data['is_vice_versa']) ? 1 : 0,
        ':status'               => $data['status'] ?? 'published',
    ]);

    return (int) $pdo->lastInsertId();
}

/**
 * Update an existing fare route's core fields (origin/destination/status).
 * Image columns are updated separately via update_fare_image() so the
 * ajax layer can handle "keep / replace / remove" per image independently.
 */
function update_fare(int $id, array $data): bool
{
    $pdo = get_db();

    $stmt = $pdo->prepare(
        'UPDATE fares SET
            origin = :origin,
            destination = :destination,
            is_vice_versa = :is_vice_versa,
            status = :status
         WHERE id = :id'
    );

    return $stmt->execute([
        ':origin'        => $data['origin'],
        ':destination'   => $data['destination'],
        ':is_vice_versa' => !empty($data['is_vice_versa']) ? 1 : 0,
        ':status'        => $data['status'] ?? 'published',
        ':id'            => $id,
    ]);
}

/**
 * Set (or clear, with $filename = null) one of the two image columns for
 * a fare. $column must be 'passenger_fare_image' or 'cargo_fare_image' —
 * validated against an allow-list here since it's interpolated into SQL.
 */
function update_fare_image(int $id, string $column, ?string $filename): bool
{
    $allowedColumns = ['passenger_fare_image', 'cargo_fare_image'];
    if (!in_array($column, $allowedColumns, true)) {
        throw new InvalidArgumentException('Invalid fare image column: ' . $column);
    }

    $pdo = get_db();
    $stmt = $pdo->prepare("UPDATE fares SET $column = :filename WHERE id = :id");

    return $stmt->execute([':filename' => $filename, ':id' => $id]);
}

/** Delete a fare route and remove its image files from disk. */
function delete_fare(int $id): bool
{
    $pdo = get_db();

    $fare = get_fare($id);

    $stmt = $pdo->prepare('DELETE FROM fares WHERE id = :id');
    $result = $stmt->execute([':id' => $id]);

    if ($result && $fare) {
        if (!empty($fare['passenger_fare_image'])) {
            delete_uploaded_image_file($fare['passenger_fare_image'], 'fares');
        }
        if (!empty($fare['cargo_fare_image'])) {
            delete_uploaded_image_file($fare['cargo_fare_image'], 'fares');
        }
    }

    return $result;
}

/** Simple dashboard stat: total fare route count. */
function count_all_fares(): int
{
    return (int) get_db()->query('SELECT COUNT(*) FROM fares')->fetchColumn();
}