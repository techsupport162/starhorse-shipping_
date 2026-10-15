<?php
/**
 * functions/vessel_functions.php
 * -----------------------------------------------------------------------
 * All database access for the Vessel module lives here. Both the public
 * site (sections/vessels.php, read-only) and the admin panel (full CRUD)
 * include this file. Every query uses prepared statements.
 * -----------------------------------------------------------------------
 */

require_once __DIR__ . '/../../config/database.php';

/**
 * PUBLIC SITE: all published vessels, each with its ordered gallery images.
 * Returns each vessel with an 'images' array (filenames only) and a
 * convenience 'image' key set to the first image (used for card thumbnails).
 */
function get_published_vessels(): array
{
    $pdo = get_db();

    $vessels = $pdo->query(
        "SELECT id, name, tagline, length_m, breadth_m, depth_m, gross_tonnage, description
         FROM vessels
         WHERE status = 'published'
         ORDER BY sort_order ASC, created_at DESC"
    )->fetchAll();

    if (!$vessels) {
        return [];
    }

    $ids = array_column($vessels, 'id');
    $images = fetch_images_for_vessels($ids);
    $features = fetch_features_for_vessels($ids);

    foreach ($vessels as &$vessel) {
        $vessel['images']   = $images[$vessel['id']] ?? [];
        $vessel['image']    = $vessel['images'][0] ?? null;
        $vessel['features'] = $features[$vessel['id']] ?? [];
    }
    unset($vessel);

    return $vessels;
}

/** Fetch ordered image filenames grouped by vessel_id, for a set of vessel IDs. */
function fetch_images_for_vessels(array $vesselIds): array
{
    if (empty($vesselIds)) {
        return [];
    }

    $pdo = get_db();
    $placeholders = implode(',', array_fill(0, count($vesselIds), '?'));

    $stmt = $pdo->prepare(
        "SELECT vessel_id, image FROM vessel_images
         WHERE vessel_id IN ($placeholders)
         ORDER BY sort_order ASC, id ASC"
    );
    $stmt->execute($vesselIds);

    $grouped = [];
    foreach ($stmt->fetchAll() as $row) {
        $grouped[$row['vessel_id']][] = $row['image'];
    }
    return $grouped;
}

/**
 * ADMIN: paginated, searchable, sortable vessel list.
 *
 * @return array{rows: array, total: int}
 */
function get_vessels_for_admin(string $search = '', int $limit = 10, int $offset = 0, string $orderBy = 'created_at', string $orderDir = 'DESC'): array
{
    $pdo = get_db();

    $allowedOrderBy = ['name', 'length_m', 'breadth_m', 'depth_m', 'gross_tonnage', 'created_at'];
    if (!in_array($orderBy, $allowedOrderBy, true)) {
        $orderBy = 'created_at';
    }
    $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';

    $where  = '';
    $params = [];
    if ($search !== '') {
        $where = 'WHERE v.name ILIKE :search';
        $params[':search'] = '%' . $search . '%';
    }

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM vessels v $where");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $sql = "SELECT v.id, v.name, v.length_m, v.breadth_m, v.depth_m, v.gross_tonnage,
                   v.status, v.created_at, v.updated_at,
                   (SELECT image FROM vessel_images vi
                    WHERE vi.vessel_id = v.id
                    ORDER BY vi.sort_order ASC, vi.id ASC LIMIT 1) AS thumbnail
            FROM vessels v
            $where
            ORDER BY v.$orderBy $orderDir
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

/** Single vessel with its full ordered image gallery (id + filename), for View/Edit. */
function get_vessel_with_images(int $id): ?array
{
    $pdo = get_db();

    $stmt = $pdo->prepare('SELECT * FROM vessels WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $vessel = $stmt->fetch();

    if (!$vessel) {
        return null;
    }

    $imgStmt = $pdo->prepare(
        'SELECT id, image FROM vessel_images WHERE vessel_id = :id ORDER BY sort_order ASC, id ASC'
    );
    $imgStmt->execute([':id' => $id]);
    $vessel['images'] = $imgStmt->fetchAll();

    $vessel['features']    = get_features_for_vessel($id);
    $vessel['feature_ids'] = array_column($vessel['features'], 'id');

    return $vessel;
}

/** Insert a new vessel. Returns the new vessel's ID. */
function create_vessel(array $data): int
{
    $pdo = get_db();

    $stmt = $pdo->prepare(
        'INSERT INTO vessels (name, tagline, length_m, breadth_m, depth_m, gross_tonnage, description, status)
         VALUES (:name, :tagline, :length_m, :breadth_m, :depth_m, :gross_tonnage, :description, :status)
         RETURNING id'
    );
    $stmt->execute([
        ':name'          => $data['name'],
        ':tagline'       => $data['tagline'],
        ':length_m'      => $data['length_m'],
        ':breadth_m'     => $data['breadth_m'],
        ':depth_m'       => $data['depth_m'],
        ':gross_tonnage' => $data['gross_tonnage'],
        ':description'   => $data['description'],
        ':status'        => $data['status'] ?? 'published',
    ]);

    return (int) $stmt->fetchColumn();
}

/** Update an existing vessel's core fields. */
function update_vessel(int $id, array $data): bool
{
    $pdo = get_db();

    $stmt = $pdo->prepare(
        'UPDATE vessels SET
            name = :name,
            tagline = :tagline,
            length_m = :length_m,
            breadth_m = :breadth_m,
            depth_m = :depth_m,
            gross_tonnage = :gross_tonnage,
            description = :description,
            status = :status
         WHERE id = :id'
    );

    return $stmt->execute([
        ':name'          => $data['name'],
        ':tagline'       => $data['tagline'],
        ':length_m'      => $data['length_m'],
        ':breadth_m'     => $data['breadth_m'],
        ':depth_m'       => $data['depth_m'],
        ':gross_tonnage' => $data['gross_tonnage'],
        ':description'   => $data['description'],
        ':status'        => $data['status'] ?? 'published',
        ':id'            => $id,
    ]);
}

/** Delete a vessel (its vessel_images rows cascade via FK) and remove image files from disk. */
function delete_vessel(int $id): bool
{
    $pdo = get_db();

    $filenames = array_column(get_vessel_image_rows($id), 'image');

    $stmt = $pdo->prepare('DELETE FROM vessels WHERE id = :id');
    $result = $stmt->execute([':id' => $id]);

    if ($result) {
        foreach ($filenames as $filename) {
            delete_image_file($filename);
        }
    }

    return $result;
}

/** Raw image rows (id, image) for a vessel, ordered. */
function get_vessel_image_rows(int $vesselId): array
{
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT id, image FROM vessel_images WHERE vessel_id = :id ORDER BY sort_order ASC, id ASC');
    $stmt->execute([':id' => $vesselId]);
    return $stmt->fetchAll();
}

/** Attach an already-uploaded image filename to a vessel. */
function add_vessel_image(int $vesselId, string $filename, int $sortOrder = 0): int
{
    $pdo = get_db();
    $stmt = $pdo->prepare(
        'INSERT INTO vessel_images (vessel_id, image, sort_order) VALUES (:vessel_id, :image, :sort_order) RETURNING id'
    );
    $stmt->execute([':vessel_id' => $vesselId, ':image' => $filename, ':sort_order' => $sortOrder]);
    return (int) $stmt->fetchColumn();
}

/** Delete a single gallery image (DB row + file on disk). Returns false if not found. */
function delete_vessel_image(int $imageId): bool
{
    $pdo = get_db();

    $stmt = $pdo->prepare('SELECT image FROM vessel_images WHERE id = :id');
    $stmt->execute([':id' => $imageId]);
    $filename = $stmt->fetchColumn();

    if ($filename === false) {
        return false;
    }

    $del = $pdo->prepare('DELETE FROM vessel_images WHERE id = :id');
    $del->execute([':id' => $imageId]);

    delete_image_file($filename);

    return true;
}

/** Remove a Cloudinary vessel image or a legacy local image file. */
function delete_image_file(string $filename): void
{
    delete_uploaded_image_file($filename, 'vessels');
}

/** Simple dashboard stat: total vessel count. */
function count_all_vessels(): int
{
    return (int) get_db()->query('SELECT COUNT(*) FROM vessels')->fetchColumn();
}

/**
 * Reorder a vessel's EXISTING images according to a caller-supplied list of
 * image IDs (already filtered to IDs that still belong to this vessel and
 * weren't just removed). Any image not present in $orderedImageIds keeps
 * its relative order and is placed after the ones that were. Returns the
 * number of images the vessel has after this call, so the caller knows
 * where to continue sort_order numbering for newly uploaded files.
 */
function reorder_vessel_images(int $vesselId, array $orderedImageIds): int
{
    $pdo = get_db();

    // Only allow reordering images that actually belong to this vessel —
    // never trust IDs from the request without checking ownership.
    $current = get_vessel_image_rows($vesselId);
    $currentIds = array_column($current, 'id');

    $orderedImageIds = array_values(array_filter(array_map('intval', $orderedImageIds), function ($id) use ($currentIds) {
        return in_array($id, $currentIds, true);
    }));

    // Append anything not explicitly ordered (e.g. stale client state)
    // after the ordered ones, preserving their existing relative order.
    $remaining = array_values(array_diff($currentIds, $orderedImageIds));
    $finalOrder = array_merge($orderedImageIds, $remaining);

    $stmt = $pdo->prepare('UPDATE vessel_images SET sort_order = :sort_order WHERE id = :id AND vessel_id = :vessel_id');
    foreach ($finalOrder as $position => $imageId) {
        $stmt->execute([':sort_order' => $position, ':id' => $imageId, ':vessel_id' => $vesselId]);
    }

    return count($finalOrder);
}

/* =============================================================================
 * Vessel Features — master list (vessel_features) + per-vessel assignment
 * (vessel_feature_assignments). Kept in this same file since both tables
 * exist only to serve the Vessel module (public site + admin), matching
 * how vessel_images is handled above.
 * ============================================================================= */

/** Every feature in the master list, ordered for display (admin manager + assignment checklist). */
function get_all_features(): array
{
    return get_db()->query(
        'SELECT id, name, icon, sort_order FROM vessel_features ORDER BY sort_order ASC, name ASC'
    )->fetchAll();
}

/** The features currently assigned to one vessel, ordered — used on the public vessel gallery. */
function get_features_for_vessel(int $vesselId): array
{
    $stmt = get_db()->prepare(
        'SELECT f.id, f.name, f.icon
         FROM vessel_feature_assignments a
         INNER JOIN vessel_features f ON f.id = a.feature_id
         WHERE a.vessel_id = :vessel_id
         ORDER BY a.sort_order ASC, f.name ASC'
    );
    $stmt->execute([':vessel_id' => $vesselId]);
    return $stmt->fetchAll();
}

/** Feature IDs (only) assigned to one vessel — convenient for pre-checking the admin checklist. */
function get_feature_ids_for_vessel(int $vesselId): array
{
    $stmt = get_db()->prepare(
        'SELECT feature_id FROM vessel_feature_assignments WHERE vessel_id = :vessel_id ORDER BY sort_order ASC'
    );
    $stmt->execute([':vessel_id' => $vesselId]);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

/** Batch: features for many vessels at once, grouped by vessel_id — mirrors fetch_images_for_vessels(). */
function fetch_features_for_vessels(array $vesselIds): array
{
    if (empty($vesselIds)) {
        return [];
    }

    $pdo = get_db();
    $placeholders = implode(',', array_fill(0, count($vesselIds), '?'));

    $stmt = $pdo->prepare(
        "SELECT a.vessel_id, f.id, f.name, f.icon
         FROM vessel_feature_assignments a
         INNER JOIN vessel_features f ON f.id = a.feature_id
         WHERE a.vessel_id IN ($placeholders)
         ORDER BY a.sort_order ASC, f.name ASC"
    );
    $stmt->execute($vesselIds);

    $grouped = [];
    foreach ($stmt->fetchAll() as $row) {
        $grouped[$row['vessel_id']][] = ['id' => (int) $row['id'], 'name' => $row['name'], 'icon' => $row['icon']];
    }
    return $grouped;
}

/** Create a new feature. Returns the new feature's ID. Throws on duplicate name (caller should catch). */
function create_feature(string $name, string $icon): int
{
    $pdo = get_db();

    $maxOrder = (int) $pdo->query('SELECT COALESCE(MAX(sort_order), 0) FROM vessel_features')->fetchColumn();

    $stmt = $pdo->prepare(
        'INSERT INTO vessel_features (name, icon, sort_order) VALUES (:name, :icon, :sort_order) RETURNING id'
    );
    $stmt->execute([':name' => $name, ':icon' => $icon, ':sort_order' => $maxOrder + 10]);

    return (int) $stmt->fetchColumn();
}

/** Update an existing feature's name/icon. */
function update_feature(int $id, string $name, string $icon): bool
{
    $stmt = get_db()->prepare('UPDATE vessel_features SET name = :name, icon = :icon WHERE id = :id');
    return $stmt->execute([':name' => $name, ':icon' => $icon, ':id' => $id]);
}

/** Delete a feature outright (assignments cascade via FK — no orphaned rows). */
function delete_feature(int $id): bool
{
    $stmt = get_db()->prepare('DELETE FROM vessel_features WHERE id = :id');
    return $stmt->execute([':id' => $id]);
}

/** True if a feature with this name already exists (case-insensitive), optionally excluding one ID (for edits). */
function feature_name_exists(string $name, ?int $excludeId = null): bool
{
    $sql = 'SELECT COUNT(*) FROM vessel_features WHERE LOWER(name) = LOWER(:name)';
    $params = [':name' => $name];
    if ($excludeId !== null) {
        $sql .= ' AND id != :exclude_id';
        $params[':exclude_id'] = $excludeId;
    }
    $stmt = get_db()->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn() > 0;
}

/**
 * Replace a vessel's full set of assigned features in one call — the
 * simplest correct way to sync an admin checklist (some boxes newly
 * checked, some unchecked) without diffing. Preserves the checklist's
 * submitted order as the new per-vessel sort_order. Safe with an empty
 * array (removes all assignments).
 */
function set_vessel_features(int $vesselId, array $featureIds): void
{
    $pdo = get_db();

    $del = $pdo->prepare('DELETE FROM vessel_feature_assignments WHERE vessel_id = :vessel_id');
    $del->execute([':vessel_id' => $vesselId]);

    if (empty($featureIds)) {
        return;
    }

    $insert = $pdo->prepare(
        'INSERT INTO vessel_feature_assignments (vessel_id, feature_id, sort_order) VALUES (:vessel_id, :feature_id, :sort_order) ON CONFLICT DO NOTHING'
    );
    $position = 0;
    foreach (array_unique(array_map('intval', $featureIds)) as $featureId) {
        if ($featureId <= 0) {
            continue;
        }
        $insert->execute([':vessel_id' => $vesselId, ':feature_id' => $featureId, ':sort_order' => $position]);
        $position++;
    }
}

/** Simple dashboard stat: total feature count. */
function count_all_features(): int
{
    return (int) get_db()->query('SELECT COUNT(*) FROM vessel_features')->fetchColumn();
}