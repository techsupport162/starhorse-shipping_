<?php
/**
 * functions/career_functions.php
 * -----------------------------------------------------------------------
 * All database access for the Career Management module lives here — the
 * public site (published postings, position dropdown) and the admin panel
 * (full CRUD across Positions / Postings / Applicants) both include this
 * file. Every query uses prepared statements. Mirrors the shape of
 * functions/fare_functions.php and functions/faq_functions.php.
 *
 * This file grows across phases:
 *   Phase 3 (this section)  — Career Position Settings
 *   Phase 4                 — Career Postings
 *   Phase 5                 — Career Applicants
 * -----------------------------------------------------------------------
 */

require_once __DIR__ . '/../../config/database.php';

/* =========================================================================
 * CAREER POSITIONS (Position Settings)
 * ========================================================================= */

/**
 * ADMIN: every position, active or not, for the Position Settings table.
 * Ordered by sort_order then name so admin-defined ordering (future
 * drag-and-drop, if added) is respected, with a stable alphabetical
 * fallback.
 */
function get_positions_for_admin(): array
{
    $pdo = get_db();

    return $pdo->query(
        "SELECT p.id, p.name, p.is_active, p.sort_order, p.created_at, p.updated_at,
                (SELECT COUNT(*) FROM career_postings cp WHERE cp.position_id = p.id) AS posting_count
         FROM career_positions p
         ORDER BY p.sort_order ASC, p.name ASC"
    )->fetchAll();
}

/**
 * PUBLIC / ADMIN FORM: only active positions, for the "Position" dropdown
 * when creating or editing a career posting. Disabled positions are
 * intentionally excluded here — see career_functions.php Phase 4 notes on
 * how an existing posting keeps its (possibly now-disabled) position
 * intact via get_position_including_inactive().
 */
function get_active_positions(): array
{
    $pdo = get_db();

    return $pdo->query(
        "SELECT id, name FROM career_positions WHERE is_active = 1 ORDER BY sort_order ASC, name ASC"
    )->fetchAll();
}

/** Single position, for View/Edit. */
function get_position(int $id): ?array
{
    $pdo = get_db();

    $stmt = $pdo->prepare('SELECT * FROM career_positions WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $position = $stmt->fetch();

    return $position ?: null;
}

/**
 * Fetch a position by ID regardless of is_active — used when displaying
 * an existing career posting's position name even if that position has
 * since been disabled (disabling must never corrupt/hide existing
 * records, only remove the option from the dropdown for NEW postings).
 */
function get_position_including_inactive(int $id): ?array
{
    return get_position($id);
}

/** Case-insensitive check for whether a position name already exists. */
function career_position_name_exists(string $name, ?int $excludeId = null): bool
{
    $pdo = get_db();

    $sql = 'SELECT COUNT(*) FROM career_positions WHERE LOWER(name) = LOWER(:name)';
    $params = [':name' => $name];

    if ($excludeId !== null) {
        $sql .= ' AND id != :exclude_id';
        $params[':exclude_id'] = $excludeId;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return ((int) $stmt->fetchColumn()) > 0;
}

/** How many career postings currently reference this position. */
function count_postings_using_position(int $positionId): int
{
    $pdo = get_db();

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM career_postings WHERE position_id = :id');
    $stmt->execute([':id' => $positionId]);

    return (int) $stmt->fetchColumn();
}

/** Insert a new position. Returns the new position's ID. */
function create_career_position(array $data): int
{
    $pdo = get_db();

    // New positions are appended after the current highest sort_order so
    // they land at the end of the admin-defined list by default.
    $nextOrder = (int) $pdo->query('SELECT COALESCE(MAX(sort_order), 0) + 10 FROM career_positions')->fetchColumn();

    $stmt = $pdo->prepare(
        'INSERT INTO career_positions (name, is_active, sort_order)
         VALUES (:name, :is_active, :sort_order)'
    );
    $stmt->execute([
        ':name'       => $data['name'],
        ':is_active'  => !empty($data['is_active']) ? 1 : 0,
        ':sort_order' => $data['sort_order'] ?? $nextOrder,
    ]);

    return (int) $pdo->lastInsertId();
}

/** Update an existing position's name/active flag. */
function update_career_position(int $id, array $data): bool
{
    $pdo = get_db();

    $stmt = $pdo->prepare(
        'UPDATE career_positions SET name = :name, is_active = :is_active WHERE id = :id'
    );

    return $stmt->execute([
        ':name'      => $data['name'],
        ':is_active' => !empty($data['is_active']) ? 1 : 0,
        ':id'        => $id,
    ]);
}

/**
 * Set only the is_active flag (used by the quick Enable/Disable toggle in
 * the Position Settings table, separate from the full Edit form).
 */
function set_career_position_status(int $id, bool $isActive): bool
{
    $pdo = get_db();

    $stmt = $pdo->prepare('UPDATE career_positions SET is_active = :is_active WHERE id = :id');

    return $stmt->execute([':is_active' => $isActive ? 1 : 0, ':id' => $id]);
}

/**
 * Delete a position. Refuses (returns false with $blockedByUsage set) if
 * any career posting still references it — existing postings must never
 * be corrupted by a position being removed. The admin is expected to
 * disable the position instead, or reassign/delete the postings that use
 * it first. Mirrors the DB-level ON DELETE RESTRICT foreign key as a
 * friendlier, pre-emptive application-level check.
 */
function delete_career_position(int $id): array
{
    $inUse = count_postings_using_position($id);
    if ($inUse > 0) {
        return ['deleted' => false, 'in_use' => $inUse];
    }

    $pdo = get_db();
    $stmt = $pdo->prepare('DELETE FROM career_positions WHERE id = :id');
    $result = $stmt->execute([':id' => $id]);

    return ['deleted' => $result && $stmt->rowCount() > 0, 'in_use' => 0];
}

/** Simple dashboard stat: total position count. */
function count_all_career_positions(): int
{
    return (int) get_db()->query('SELECT COUNT(*) FROM career_positions')->fetchColumn();
}

/* =========================================================================
 * CAREER POSTINGS (Job Postings)
 * ========================================================================= */

/**
 * PUBLIC SITE: all published career postings, with their position name,
 * ordered for display.
 */
function get_published_career_postings(): array
{
    $pdo = get_db();

    return $pdo->query(
        "SELECT cp.id, cp.title, cp.short_description, cp.job_description, cp.qualifications,
                cp.responsibilities, cp.application_instructions, cp.contact_email,
                cp.recruitment_image, cp.created_at,
                p.id AS position_id, p.name AS position_name
         FROM career_postings cp
         INNER JOIN career_positions p ON p.id = cp.position_id
         WHERE cp.status = 'published'
         ORDER BY cp.sort_order ASC, cp.created_at DESC"
    )->fetchAll();
}

/**
 * PUBLIC SITE: a single published posting, for the details view / apply
 * flow. Returns null if the posting doesn't exist OR isn't published —
 * Draft postings must never be reachable from the public site, including
 * by guessing/sharing a direct link.
 */
function get_published_career_posting(int $id): ?array
{
    $pdo = get_db();

    $stmt = $pdo->prepare(
        "SELECT cp.id, cp.title, cp.short_description, cp.job_description, cp.qualifications,
                cp.responsibilities, cp.application_instructions, cp.contact_email,
                cp.recruitment_image, cp.created_at,
                p.id AS position_id, p.name AS position_name
         FROM career_postings cp
         INNER JOIN career_positions p ON p.id = cp.position_id
         WHERE cp.id = :id AND cp.status = 'published'"
    );
    $stmt->execute([':id' => $id]);
    $posting = $stmt->fetch();

    return $posting ?: null;
}

/**
 * ADMIN: paginated, searchable, sortable posting list, joined with its
 * position name (including inactive positions, so a posting tied to a
 * since-disabled position still displays correctly).
 *
 * @return array{rows: array, total: int}
 */
function get_career_postings_for_admin(string $search = '', int $limit = 10, int $offset = 0, string $orderBy = 'created_at', string $orderDir = 'DESC'): array
{
    $pdo = get_db();

    $allowedOrderBy = ['title', 'status', 'created_at', 'updated_at'];
    if (!in_array($orderBy, $allowedOrderBy, true)) {
        $orderBy = 'created_at';
    }
    $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';

    $where  = '';
    $params = [];
    if ($search !== '') {
        $where = 'WHERE cp.title LIKE :search OR p.name LIKE :search';
        $params[':search'] = '%' . $search . '%';
    }

    $countStmt = $pdo->prepare(
        "SELECT COUNT(*) FROM career_postings cp INNER JOIN career_positions p ON p.id = cp.position_id $where"
    );
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $sql = "SELECT cp.id, cp.title, cp.short_description, cp.status, cp.recruitment_image,
                   cp.created_at, cp.updated_at,
                   p.id AS position_id, p.name AS position_name, p.is_active AS position_is_active,
                   (SELECT COUNT(*) FROM career_applicants ca WHERE ca.posting_id = cp.id) AS applicant_count
            FROM career_postings cp
            INNER JOIN career_positions p ON p.id = cp.position_id
            $where
            ORDER BY cp.$orderBy $orderDir
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

/** Single career posting (any status), joined with its position name, for View/Edit. */
function get_career_posting(int $id): ?array
{
    $pdo = get_db();

    $stmt = $pdo->prepare(
        "SELECT cp.*, p.name AS position_name, p.is_active AS position_is_active
         FROM career_postings cp
         INNER JOIN career_positions p ON p.id = cp.position_id
         WHERE cp.id = :id"
    );
    $stmt->execute([':id' => $id]);
    $posting = $stmt->fetch();

    return $posting ?: null;
}

/**
 * Validate that $positionId is a legal choice for a career posting.
 *
 * On CREATE: the position must exist and be active — disabled positions
 * are never available for new postings.
 *
 * On UPDATE: the position must exist, and be EITHER active OR the same
 * position the posting already had ($currentPositionId) — this lets an
 * admin leave a posting's position untouched even if that position has
 * since been disabled, without corrupting the record, while still
 * blocking a *new* assignment to some other disabled position.
 *
 * @return string|null error message, or null if valid
 */
function validate_position_for_posting(int $positionId, bool $isCreate, ?int $currentPositionId = null): ?string
{
    $position = get_position($positionId);
    if (!$position) {
        return 'Selected position no longer exists. Please choose another.';
    }

    if ((int) $position['is_active'] === 1) {
        return null; // active — always fine
    }

    // Position is disabled.
    if ($isCreate) {
        return 'This position is disabled and is not available for new postings. Enable it in Position Settings first, or choose another position.';
    }

    if ($currentPositionId !== null && $currentPositionId === $positionId) {
        return null; // unchanged — keep the existing (now-disabled) assignment
    }

    return 'This position is disabled and cannot be newly assigned. Keep the posting\'s existing position, or choose an active one.';
}

/** Insert a new career posting. Returns the new posting's ID. */
function create_career_posting(array $data): int
{
    $pdo = get_db();

    $nextOrder = (int) $pdo->query('SELECT COALESCE(MAX(sort_order), 0) + 10 FROM career_postings')->fetchColumn();

    $stmt = $pdo->prepare(
        'INSERT INTO career_postings
            (position_id, title, short_description, job_description, qualifications, responsibilities,
             application_instructions, contact_email, recruitment_image, status, sort_order)
         VALUES
            (:position_id, :title, :short_description, :job_description, :qualifications, :responsibilities,
             :application_instructions, :contact_email, :recruitment_image, :status, :sort_order)'
    );
    $stmt->execute([
        ':position_id'              => $data['position_id'],
        ':title'                    => $data['title'],
        ':short_description'       => $data['short_description'] ?? null,
        ':job_description'          => $data['job_description'] ?? null,
        ':qualifications'           => $data['qualifications'] ?? null,
        ':responsibilities'         => $data['responsibilities'] ?? null,
        ':application_instructions' => $data['application_instructions'] ?? null,
        ':contact_email'            => $data['contact_email'] ?? null,
        ':recruitment_image'        => $data['recruitment_image'] ?? null,
        ':status'                   => $data['status'] ?? 'draft',
        ':sort_order'               => $nextOrder,
    ]);

    return (int) $pdo->lastInsertId();
}

/**
 * Update an existing career posting's core fields. The recruitment image
 * is updated separately via update_career_posting_image() so the ajax
 * layer can handle "keep / replace / remove" independently, matching the
 * Fares module's pattern for its two fare images.
 */
function update_career_posting(int $id, array $data): bool
{
    $pdo = get_db();

    $stmt = $pdo->prepare(
        'UPDATE career_postings SET
            position_id = :position_id,
            title = :title,
            short_description = :short_description,
            job_description = :job_description,
            qualifications = :qualifications,
            responsibilities = :responsibilities,
            application_instructions = :application_instructions,
            contact_email = :contact_email,
            status = :status
         WHERE id = :id'
    );

    return $stmt->execute([
        ':position_id'              => $data['position_id'],
        ':title'                    => $data['title'],
        ':short_description'       => $data['short_description'] ?? null,
        ':job_description'          => $data['job_description'] ?? null,
        ':qualifications'           => $data['qualifications'] ?? null,
        ':responsibilities'         => $data['responsibilities'] ?? null,
        ':application_instructions' => $data['application_instructions'] ?? null,
        ':contact_email'            => $data['contact_email'] ?? null,
        ':status'                   => $data['status'] ?? 'draft',
        ':id'                       => $id,
    ]);
}

/** Set (or clear, with $filename = null) the recruitment image for a posting. */
function update_career_posting_image(int $id, ?string $filename): bool
{
    $pdo = get_db();
    $stmt = $pdo->prepare('UPDATE career_postings SET recruitment_image = :filename WHERE id = :id');

    return $stmt->execute([':filename' => $filename, ':id' => $id]);
}

/**
 * Quick status change (Publish / Save as Draft / Hide) without touching
 * any other field — used by the table's inline status action as well as
 * internally by update_career_posting() for full-form saves.
 */
function set_career_posting_status(int $id, string $status): bool
{
    $status = $status === 'published' ? 'published' : 'draft';
    $pdo = get_db();
    $stmt = $pdo->prepare('UPDATE career_postings SET status = :status WHERE id = :id');

    return $stmt->execute([':status' => $status, ':id' => $id]);
}

/**
 * Delete a career posting and its recruitment image file from disk.
 * Any applicants that referenced this posting are preserved — the FK is
 * ON DELETE SET NULL and each applicant row already snapshots
 * applied_position_title at submission time, so applicant history
 * survives intact (see database/migration_003_career.sql).
 */
function delete_career_posting(int $id): bool
{
    $pdo = get_db();

    $posting = get_career_posting($id);

    $stmt = $pdo->prepare('DELETE FROM career_postings WHERE id = :id');
    $result = $stmt->execute([':id' => $id]);

    if ($result && $posting && !empty($posting['recruitment_image'])) {
        delete_uploaded_image_file($posting['recruitment_image'], 'career');
    }

    return $result && $stmt->rowCount() > 0;
}

/** Dashboard stat: total career posting count. */
function count_all_career_postings(): int
{
    return (int) get_db()->query('SELECT COUNT(*) FROM career_postings')->fetchColumn();
}

/** Dashboard stat: published (publicly visible) career posting count. */
function count_published_career_postings(): int
{
    return (int) get_db()->query("SELECT COUNT(*) FROM career_postings WHERE status = 'published'")->fetchColumn();
}

/* =========================================================================
 * CAREER APPLICANTS (Applicant Management)
 * ========================================================================= */

/** The applicant workflow statuses, in their natural progression order. */
const CAREER_APPLICANT_STATUSES = ['new', 'reviewed', 'shortlisted', 'interview', 'accepted', 'rejected'];

/**
 * PUBLIC: record a new application submitted from the Career section.
 * Only ever called after the caller (ajax/career_apply.php) has already
 * confirmed the target posting is currently published — applications
 * against a Draft or missing posting are never accepted.
 *
 * applied_position_title is snapshotted here (not just looked up via the
 * FK at read time) so applicant history stays accurate and readable even
 * if the posting is later edited or hard-deleted (posting_id -> SET NULL).
 */
function create_career_applicant(array $data): int
{
    $pdo = get_db();

    $stmt = $pdo->prepare(
        'INSERT INTO career_applicants
            (posting_id, applied_position_title, full_name, email, contact_number, address, message, resume_file, status)
         VALUES
            (:posting_id, :applied_position_title, :full_name, :email, :contact_number, :address, :message, :resume_file, \'new\')'
    );
    $stmt->execute([
        ':posting_id'             => $data['posting_id'],
        ':applied_position_title' => $data['applied_position_title'],
        ':full_name'              => $data['full_name'],
        ':email'                  => $data['email'],
        ':contact_number'         => $data['contact_number'],
        ':address'                => $data['address'] ?? null,
        ':message'                => $data['message'] ?? null,
        ':resume_file'            => $data['resume_file'] ?? null,
    ]);

    return (int) $pdo->lastInsertId();
}

/**
 * ADMIN: paginated, searchable, filterable applicant list, left-joined
 * with its posting's current title/status (LEFT JOIN, not INNER — a
 * posting may have been deleted since the application was submitted;
 * applied_position_title is the reliable fallback for what to display).
 *
 * @return array{rows: array, total: int}
 */
function get_career_applicants_for_admin(string $search = '', int $limit = 10, int $offset = 0, string $orderBy = 'applied_at', string $orderDir = 'DESC', ?string $statusFilter = null): array
{
    $pdo = get_db();

    $allowedOrderBy = ['full_name', 'applied_position_title', 'status', 'applied_at'];
    if (!in_array($orderBy, $allowedOrderBy, true)) {
        $orderBy = 'applied_at';
    }
    $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';

    $where = [];
    $params = [];

    if ($search !== '') {
        $where[] = '(ca.full_name LIKE :search OR ca.email LIKE :search OR ca.applied_position_title LIKE :search)';
        $params[':search'] = '%' . $search . '%';
    }
    if ($statusFilter !== null && in_array($statusFilter, CAREER_APPLICANT_STATUSES, true)) {
        $where[] = 'ca.status = :status';
        $params[':status'] = $statusFilter;
    }
    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM career_applicants ca $whereSql");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $sql = "SELECT ca.id, ca.posting_id, ca.applied_position_title, ca.full_name, ca.email, ca.contact_number,
                   ca.address, ca.message, ca.resume_file, ca.status, ca.applied_at, ca.updated_at,
                   cp.title AS posting_title, cp.status AS posting_status
            FROM career_applicants ca
            LEFT JOIN career_postings cp ON cp.id = ca.posting_id
            $whereSql
            ORDER BY ca.$orderBy $orderDir
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

/** Single applicant, with its posting's current title/status if still linked, for View. */
function get_career_applicant(int $id): ?array
{
    $pdo = get_db();

    $stmt = $pdo->prepare(
        'SELECT ca.*, cp.title AS posting_title, cp.status AS posting_status
         FROM career_applicants ca
         LEFT JOIN career_postings cp ON cp.id = ca.posting_id
         WHERE ca.id = :id'
    );
    $stmt->execute([':id' => $id]);
    $applicant = $stmt->fetch();

    return $applicant ?: null;
}

/**
 * Update an applicant's review status. Completely independent of the
 * posting's Published/Draft status — never read or written together
 * (see functions/career_functions.php module docblock).
 */
function set_career_applicant_status(int $id, string $status): bool
{
    if (!in_array($status, CAREER_APPLICANT_STATUSES, true)) {
        throw new InvalidArgumentException('Invalid applicant status.');
    }

    $pdo = get_db();
    $stmt = $pdo->prepare('UPDATE career_applicants SET status = :status WHERE id = :id');

    return $stmt->execute([':status' => $status, ':id' => $id]);
}

/** Delete an applicant record and its uploaded resume file, if any. */
function delete_career_applicant(int $id): bool
{
    $pdo = get_db();

    $applicant = get_career_applicant($id);

    $stmt = $pdo->prepare('DELETE FROM career_applicants WHERE id = :id');
    $result = $stmt->execute([':id' => $id]);

    if ($result && $applicant && !empty($applicant['resume_file'])) {
        delete_uploaded_document_file($applicant['resume_file'], 'career_resumes');
    }

    return $result && $stmt->rowCount() > 0;
}

/** Dashboard stat: applicants still awaiting first review. */
function count_new_career_applicants(): int
{
    return (int) get_db()->query("SELECT COUNT(*) FROM career_applicants WHERE status = 'new'")->fetchColumn();
}

/** Dashboard stat: total applicant count. */
function count_all_career_applicants(): int
{
    return (int) get_db()->query('SELECT COUNT(*) FROM career_applicants')->fetchColumn();
}