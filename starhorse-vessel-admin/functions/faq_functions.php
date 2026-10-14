<?php
/**
 * functions/faq_functions.php
 * -----------------------------------------------------------------------
 * All database access for the FAQ Management module lives here. Both the
 * public site (sections/faqs.php, read-only) and the admin panel (full
 * CRUD) include this file. Every query uses prepared statements.
 *
 * Mirrors functions/fare_functions.php in shape and conventions.
 * -----------------------------------------------------------------------
 */

require_once __DIR__ . '/../../config/database.php';

/**
 * PUBLIC SITE: all published FAQs, ordered for display.
 */
function get_published_faqs(): array
{
    $pdo = get_db();

    return $pdo->query(
        "SELECT id, question, answer
         FROM faqs
         WHERE status = 'published'
         ORDER BY sort_order ASC, created_at ASC"
    )->fetchAll();
}

/**
 * ADMIN: paginated, searchable, sortable FAQ list.
 *
 * @return array{rows: array, total: int}
 */
function get_faqs_for_admin(string $search = '', int $limit = 10, int $offset = 0, string $orderBy = 'created_at', string $orderDir = 'DESC'): array
{
    $pdo = get_db();

    $allowedOrderBy = ['question', 'status', 'sort_order', 'created_at', 'updated_at'];
    if (!in_array($orderBy, $allowedOrderBy, true)) {
        $orderBy = 'created_at';
    }
    $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';

    $where  = '';
    $params = [];
    if ($search !== '') {
        $where = 'WHERE f.question LIKE :search OR f.answer LIKE :search';
        $params[':search'] = '%' . $search . '%';
    }

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM faqs f $where");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $sql = "SELECT f.id, f.question, f.answer, f.status, f.sort_order, f.created_at, f.updated_at
            FROM faqs f
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

/** Single FAQ, for View/Edit. */
function get_faq(int $id): ?array
{
    $pdo = get_db();

    $stmt = $pdo->prepare('SELECT * FROM faqs WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $faq = $stmt->fetch();

    return $faq ?: null;
}

/**
 * Case-insensitive, whitespace-trimmed check for whether a question
 * already exists, optionally excluding a given FAQ ID (used when editing
 * so a FAQ doesn't collide with itself). Mirrors fare_route_exists().
 */
function faq_question_exists(string $question, ?int $excludeId = null): bool
{
    $pdo = get_db();

    $sql = 'SELECT COUNT(*) FROM faqs WHERE LOWER(TRIM(question)) = LOWER(TRIM(:question))';
    $params = [':question' => $question];

    if ($excludeId !== null) {
        $sql .= ' AND id != :exclude_id';
        $params[':exclude_id'] = $excludeId;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return ((int) $stmt->fetchColumn()) > 0;
}

/** Insert a new FAQ. Returns the new FAQ's ID. */
function create_faq(array $data): int
{
    $pdo = get_db();

    // New FAQs are appended to the end of the current display order.
    $nextSortOrder = (int) $pdo->query('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM faqs')->fetchColumn();

    $stmt = $pdo->prepare(
        'INSERT INTO faqs (question, answer, status, sort_order)
         VALUES (:question, :answer, :status, :sort_order)
         RETURNING id'
    );
    $stmt->execute([
        ':question'   => $data['question'],
        ':answer'     => $data['answer'],
        ':status'     => $data['status'] ?? 'published',
        ':sort_order' => $data['sort_order'] ?? $nextSortOrder,
    ]);

    return (int) $stmt->fetchColumn();
}

/** Update an existing FAQ's fields. */
function update_faq(int $id, array $data): bool
{
    $pdo = get_db();

    $stmt = $pdo->prepare(
        'UPDATE faqs SET
            question = :question,
            answer = :answer,
            status = :status
         WHERE id = :id'
    );

    return $stmt->execute([
        ':question' => $data['question'],
        ':answer'   => $data['answer'],
        ':status'   => $data['status'] ?? 'published',
        ':id'       => $id,
    ]);
}

/** Delete a FAQ, then close the gap it leaves in the display order. */
function delete_faq(int $id): bool
{
    $pdo = get_db();

    $stmt = $pdo->prepare('DELETE FROM faqs WHERE id = :id');
    $deleted = $stmt->execute([':id' => $id]) && $stmt->rowCount() > 0;

    if ($deleted) {
        normalize_faq_sort_order();
    }

    return $deleted;
}

/**
 * ADMIN: persist a new display order from drag-and-drop reordering.
 *
 * Accepts the FAQ IDs in their new top-to-bottom order (as dragged in the
 * admin table) and rewrites sort_order to 1, 2, 3... to match — this is
 * the only place sort_order is ever set from outside create_faq()'s
 * "append to end" default.
 *
 * Runs as a single transaction so a failure partway through can't leave
 * the table in a half-reordered state, and validates every ID actually
 * belongs to an existing FAQ before writing anything.
 *
 * @param int[] $orderedIds FAQ IDs in the desired new order
 * @throws InvalidArgumentException if any ID doesn't exist
 */
function update_faq_order(array $orderedIds): void
{
    $orderedIds = array_values(array_unique(array_map('intval', $orderedIds)));
    if (empty($orderedIds)) {
        return;
    }

    $pdo = get_db();

    // Validate every submitted ID actually exists before touching anything —
    // a stale client (another tab, a row deleted moments ago) shouldn't be
    // able to silently write partial/garbage order data.
    $placeholders = implode(',', array_fill(0, count($orderedIds), '?'));
    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM faqs WHERE id IN ($placeholders)");
    $checkStmt->execute($orderedIds);
    if ((int) $checkStmt->fetchColumn() !== count($orderedIds)) {
        throw new InvalidArgumentException('One or more FAQs in the new order no longer exist.');
    }

    $pdo->beginTransaction();
    try {
        $updateStmt = $pdo->prepare('UPDATE faqs SET sort_order = :sort_order WHERE id = :id');
        foreach ($orderedIds as $position => $id) {
            $updateStmt->execute([
                ':sort_order' => $position + 1,
                ':id'         => $id,
            ]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Re-sequence every FAQ's sort_order to a clean 1, 2, 3... with no gaps,
 * preserving current relative order. Called after a delete so gaps left
 * behind don't accumulate over time; harmless to call at any other point
 * too since it never changes relative order, only the numbers.
 */
function normalize_faq_sort_order(): void
{
    $pdo = get_db();

    $ids = $pdo->query('SELECT id FROM faqs ORDER BY sort_order ASC, created_at ASC')->fetchAll(PDO::FETCH_COLUMN);
    if (empty($ids)) {
        return;
    }

    $pdo->beginTransaction();
    try {
        $updateStmt = $pdo->prepare('UPDATE faqs SET sort_order = :sort_order WHERE id = :id');
        foreach ($ids as $position => $id) {
            $updateStmt->execute([
                ':sort_order' => $position + 1,
                ':id'         => $id,
            ]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/** Simple dashboard stat: total FAQ count. */
function count_all_faqs(): int
{
    return (int) get_db()->query('SELECT COUNT(*) FROM faqs')->fetchColumn();
}