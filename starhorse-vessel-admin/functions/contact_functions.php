<?php
/**
 * functions/contact_functions.php
 * -----------------------------------------------------------------------
 * All database access for the Contact Management module lives here. Both
 * the public site (sections/contact.php, read-only) and the admin panel
 * (full CRUD) include this file. Every query uses prepared statements.
 *
 * Covers three concerns:
 *   - contact_settings         Head Office singleton row + map config
 *                               (mirrors get_route_banner()-style access
 *                               in functions/routes_functions.php)
 *   - contact_phones / _emails Head Office repeatable sub-lists, saved
 *                               together with the settings form via a
 *                               diff-and-sync helper (add/update/delete
 *                               all in one submit)
 *   - contact_ports            Full CRUD + drag-to-reorder list, shaped
 *                               exactly like functions/faq_functions.php
 * -----------------------------------------------------------------------
 */

require_once __DIR__ . '/../../config/database.php';

/* =========================================================================
   HEAD OFFICE SETTINGS (contact_settings — singleton row, id = 1)
   ========================================================================= */

/**
 * PUBLIC SITE: Head Office settings, only if published. Returns null if
 * the row is missing or set to draft, so the public section can show an
 * empty state instead of stale/placeholder content.
 */
function get_published_contact_settings(): ?array
{
    $pdo = get_db();

    $stmt = $pdo->query(
        "SELECT * FROM contact_settings WHERE id = 1 AND status = 'published' LIMIT 1"
    );
    $row = $stmt->fetch();

    return $row ?: null;
}

/** ADMIN: Head Office settings regardless of publish status, for the edit form. */
function get_contact_settings(): ?array
{
    $pdo = get_db();

    $stmt = $pdo->query('SELECT * FROM contact_settings WHERE id = 1 LIMIT 1');
    $row = $stmt->fetch();

    return $row ?: null;
}

/** ADMIN: update the singleton Head Office / map settings row. */
function update_contact_settings(array $data): bool
{
    $pdo = get_db();

    $stmt = $pdo->prepare(
        'UPDATE contact_settings SET
            office_name   = :office_name,
            address       = :address,
            tel_number    = :tel_number,
            facebook_name = :facebook_name,
            facebook_url  = :facebook_url,
            map_embed_url = :map_embed_url,
            map_status    = :map_status,
            status        = :status
         WHERE id = 1'
    );

    return $stmt->execute([
        ':office_name'   => $data['office_name'] ?? 'Head Office',
        ':address'       => $data['address'] ?? '',
        ':tel_number'    => $data['tel_number'] !== '' ? $data['tel_number'] : null,
        ':facebook_name' => $data['facebook_name'] !== '' ? $data['facebook_name'] : null,
        ':facebook_url'  => $data['facebook_url'] !== '' ? $data['facebook_url'] : null,
        ':map_embed_url' => $data['map_embed_url'] !== '' ? $data['map_embed_url'] : null,
        ':map_status'    => ($data['map_status'] ?? 'published') === 'draft' ? 'draft' : 'published',
        ':status'        => ($data['status'] ?? 'published') === 'draft' ? 'draft' : 'published',
    ]);
}

/* =========================================================================
   HEAD OFFICE PHONES (contact_phones)
   ========================================================================= */

/** PUBLIC SITE: published mobile numbers, in display order. */
function get_published_contact_phones(): array
{
    $pdo = get_db();

    return $pdo->query(
        "SELECT id, phone_number FROM contact_phones
         WHERE status = 'published'
         ORDER BY sort_order ASC, id ASC"
    )->fetchAll();
}

/** ADMIN: every phone row (published + draft), for the settings form. */
function get_contact_phones(): array
{
    $pdo = get_db();

    return $pdo->query(
        'SELECT id, phone_number, label, status, sort_order
         FROM contact_phones
         ORDER BY sort_order ASC, id ASC'
    )->fetchAll();
}

/**
 * ADMIN: replace the full phone list with the submitted rows in one
 * transaction — rows with an existing id are updated, rows without one
 * are inserted, and any row currently in the table that's missing from
 * the submitted list is deleted. This lets the Head Office form manage
 * add/edit/delete for the whole (small) list as a single "Save" action,
 * the same way a repeater field works in most CMS admin panels.
 *
 * @param array $phones [ ['id' => ?int, 'phone_number' => string, 'label' => ?string, 'status' => string], ... ]
 */
function sync_contact_phones(array $phones): void
{
    $pdo = get_db();
    $pdo->beginTransaction();

    try {
        $keepIds = [];

        $insertStmt = $pdo->prepare(
            'INSERT INTO contact_phones (phone_number, label, status, sort_order) VALUES (:phone_number, :label, :status, :sort_order)'
        );
        $updateStmt = $pdo->prepare(
            'UPDATE contact_phones SET phone_number = :phone_number, label = :label, status = :status, sort_order = :sort_order WHERE id = :id'
        );

        foreach (array_values($phones) as $position => $phone) {
            $params = [
                ':phone_number' => $phone['phone_number'],
                ':label'        => ($phone['label'] ?? '') !== '' ? $phone['label'] : null,
                ':status'       => ($phone['status'] ?? 'published') === 'draft' ? 'draft' : 'published',
                ':sort_order'   => $position + 1,
            ];

            if (!empty($phone['id'])) {
                $updateStmt->execute($params + [':id' => (int) $phone['id']]);
                $keepIds[] = (int) $phone['id'];
            } else {
                $insertStmt->execute($params);
                $keepIds[] = (int) $pdo->lastInsertId();
            }
        }

        if (empty($keepIds)) {
            $pdo->exec('DELETE FROM contact_phones');
        } else {
            $placeholders = implode(',', array_fill(0, count($keepIds), '?'));
            $deleteStmt = $pdo->prepare("DELETE FROM contact_phones WHERE id NOT IN ($placeholders)");
            $deleteStmt->execute($keepIds);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/* =========================================================================
   HEAD OFFICE EMAILS (contact_emails)
   ========================================================================= */

/** PUBLIC SITE: published email addresses, in display order. */
function get_published_contact_emails(): array
{
    $pdo = get_db();

    return $pdo->query(
        "SELECT id, email FROM contact_emails
         WHERE status = 'published'
         ORDER BY sort_order ASC, id ASC"
    )->fetchAll();
}

/** ADMIN: every email row (published + draft), for the settings form. */
function get_contact_emails(): array
{
    $pdo = get_db();

    return $pdo->query(
        'SELECT id, email, label, status, sort_order
         FROM contact_emails
         ORDER BY sort_order ASC, id ASC'
    )->fetchAll();
}

/**
 * ADMIN: replace the full email list with the submitted rows in one
 * transaction. Same add/update/delete-diff approach as sync_contact_phones().
 *
 * @param array $emails [ ['id' => ?int, 'email' => string, 'label' => ?string, 'status' => string], ... ]
 */
function sync_contact_emails(array $emails): void
{
    $pdo = get_db();
    $pdo->beginTransaction();

    try {
        $keepIds = [];

        $insertStmt = $pdo->prepare(
            'INSERT INTO contact_emails (email, label, status, sort_order) VALUES (:email, :label, :status, :sort_order)'
        );
        $updateStmt = $pdo->prepare(
            'UPDATE contact_emails SET email = :email, label = :label, status = :status, sort_order = :sort_order WHERE id = :id'
        );

        foreach (array_values($emails) as $position => $email) {
            $params = [
                ':email'      => $email['email'],
                ':label'      => ($email['label'] ?? '') !== '' ? $email['label'] : null,
                ':status'     => ($email['status'] ?? 'published') === 'draft' ? 'draft' : 'published',
                ':sort_order' => $position + 1,
            ];

            if (!empty($email['id'])) {
                $updateStmt->execute($params + [':id' => (int) $email['id']]);
                $keepIds[] = (int) $email['id'];
            } else {
                $insertStmt->execute($params);
                $keepIds[] = (int) $pdo->lastInsertId();
            }
        }

        if (empty($keepIds)) {
            $pdo->exec('DELETE FROM contact_emails');
        } else {
            $placeholders = implode(',', array_fill(0, count($keepIds), '?'));
            $deleteStmt = $pdo->prepare("DELETE FROM contact_emails WHERE id NOT IN ($placeholders)");
            $deleteStmt->execute($keepIds);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/* =========================================================================
   PORTS (contact_ports) — shaped exactly like functions/faq_functions.php
   ========================================================================= */

/** PUBLIC SITE: all published ports, ordered for display. */
function get_published_ports(): array
{
    $pdo = get_db();

    return $pdo->query(
        "SELECT id, port_name, phone_number
         FROM contact_ports
         WHERE status = 'published'
         ORDER BY sort_order ASC, created_at ASC"
    )->fetchAll();
}

/** ADMIN: full port list (published + draft) in display order, for the DataTable. */
function get_ports_for_admin(): array
{
    $pdo = get_db();

    return $pdo->query(
        'SELECT id, port_name, phone_number, status, sort_order, created_at, updated_at
         FROM contact_ports
         ORDER BY sort_order ASC, created_at ASC'
    )->fetchAll();
}

/** Single port, for View/Edit. */
function get_port(int $id): ?array
{
    $pdo = get_db();

    $stmt = $pdo->prepare('SELECT * FROM contact_ports WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $port = $stmt->fetch();

    return $port ?: null;
}

/**
 * Case-insensitive, whitespace-trimmed check for whether a port name
 * already exists, optionally excluding a given port ID (used when
 * editing). Mirrors faq_question_exists() / destination_location_exists().
 */
function port_name_exists(string $portName, ?int $excludeId = null): bool
{
    $pdo = get_db();

    $sql = 'SELECT COUNT(*) FROM contact_ports WHERE LOWER(TRIM(port_name)) = LOWER(TRIM(:port_name))';
    $params = [':port_name' => $portName];

    if ($excludeId !== null) {
        $sql .= ' AND id != :exclude_id';
        $params[':exclude_id'] = $excludeId;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return ((int) $stmt->fetchColumn()) > 0;
}

/** Insert a new port. Returns the new port's ID. */
function create_port(array $data): int
{
    $pdo = get_db();

    // New ports are appended to the end of the current display order.
    $nextSortOrder = (int) $pdo->query('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM contact_ports')->fetchColumn();

    $stmt = $pdo->prepare(
        'INSERT INTO contact_ports (port_name, phone_number, status, sort_order)
         VALUES (:port_name, :phone_number, :status, :sort_order)'
    );
    $stmt->execute([
        ':port_name'    => $data['port_name'],
        ':phone_number' => $data['phone_number'],
        ':status'       => $data['status'] ?? 'published',
        ':sort_order'   => $data['sort_order'] ?? $nextSortOrder,
    ]);

    return (int) $pdo->lastInsertId();
}

/** Update an existing port's fields. */
function update_port(int $id, array $data): bool
{
    $pdo = get_db();

    $stmt = $pdo->prepare(
        'UPDATE contact_ports SET
            port_name = :port_name,
            phone_number = :phone_number,
            status = :status
         WHERE id = :id'
    );

    return $stmt->execute([
        ':port_name'    => $data['port_name'],
        ':phone_number' => $data['phone_number'],
        ':status'       => $data['status'] ?? 'published',
        ':id'           => $id,
    ]);
}

/** Delete a port, then close the gap it leaves in the display order. */
function delete_port(int $id): bool
{
    $pdo = get_db();

    $stmt = $pdo->prepare('DELETE FROM contact_ports WHERE id = :id');
    $deleted = $stmt->execute([':id' => $id]) && $stmt->rowCount() > 0;

    if ($deleted) {
        normalize_port_sort_order();
    }

    return $deleted;
}

/**
 * ADMIN: persist a new display order from drag-and-drop reordering.
 * Mirrors update_faq_order() exactly — single transaction, validates
 * every submitted ID actually exists before writing anything.
 *
 * @param int[] $orderedIds Port IDs in the desired new order
 * @throws InvalidArgumentException if any ID doesn't exist
 */
function update_port_order(array $orderedIds): void
{
    $orderedIds = array_values(array_unique(array_map('intval', $orderedIds)));
    if (empty($orderedIds)) {
        return;
    }

    $pdo = get_db();

    $placeholders = implode(',', array_fill(0, count($orderedIds), '?'));
    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM contact_ports WHERE id IN ($placeholders)");
    $checkStmt->execute($orderedIds);
    if ((int) $checkStmt->fetchColumn() !== count($orderedIds)) {
        throw new InvalidArgumentException('One or more ports in the new order no longer exist.');
    }

    $pdo->beginTransaction();
    try {
        $updateStmt = $pdo->prepare('UPDATE contact_ports SET sort_order = :sort_order WHERE id = :id');
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
 * Re-sequence every port's sort_order to a clean 1, 2, 3... with no gaps,
 * preserving current relative order. Called after a delete. Mirrors
 * normalize_faq_sort_order().
 */
function normalize_port_sort_order(): void
{
    $pdo = get_db();

    $ids = $pdo->query('SELECT id FROM contact_ports ORDER BY sort_order ASC, created_at ASC')->fetchAll(PDO::FETCH_COLUMN);
    if (empty($ids)) {
        return;
    }

    $pdo->beginTransaction();
    try {
        $updateStmt = $pdo->prepare('UPDATE contact_ports SET sort_order = :sort_order WHERE id = :id');
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

/** Simple dashboard stat: total port count. */
function count_all_ports(): int
{
    return (int) get_db()->query('SELECT COUNT(*) FROM contact_ports')->fetchColumn();
}