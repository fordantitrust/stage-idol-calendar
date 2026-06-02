<?php
/**
 * FTS5 Full-Text Search helpers
 *
 * All functions accept a PDO connection and gracefully fall back to
 * LIKE queries when FTS5 tables are unavailable.
 */

// ─────────────────────────────────────────────────────────────────────────────
// Availability & escaping
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Check whether all three FTS5 virtual tables exist and are usable.
 *
 * @param PDO $db
 * @return bool
 */
function fts5_available(PDO $db): bool {
    static $cache = null;
    if ($cache !== null) return $cache;
    try {
        $tables = $db->query(
            "SELECT COUNT(*) FROM sqlite_master WHERE type='table'
             AND name IN ('programs_fts','events_fts','artists_fts')"
        )->fetchColumn();
        $cache = ((int)$tables === 3);
    } catch (Exception $e) {
        $cache = false;
    }
    return $cache;
}

/**
 * Escape a user-supplied query string for safe use in FTS5 MATCH.
 *
 * Splits on whitespace, wraps each term in double-quotes so that
 * FTS5 syntax characters (-, *, AND, OR …) are treated as literals.
 * Requires query length ≥ 3 for trigram tokenizer.
 *
 * @param string $query Raw user input
 * @return string        FTS5-safe MATCH expression, or '' if too short
 */
function fts5_escape(string $query): string {
    $query = trim($query);
    if (mb_strlen($query) < 3) return '';
    $terms = preg_split('/\s+/', $query, -1, PREG_SPLIT_NO_EMPTY);
    return implode(' ', array_map(
        fn($t) => '"' . str_replace('"', '""', $t) . '"',
        $terms
    ));
}

// ─────────────────────────────────────────────────────────────────────────────
// Per-table searches
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Count total matching programs (for pagination).
 *
 * @param PDO      $db
 * @param string   $query
 * @param int|null $eventId Filter by event_id (null = all events)
 * @return int
 */
function fts5_count_programs(PDO $db, string $query, ?int $eventId = null): int {
    $match = fts5_escape($query);
    if ($match === '') return 0;

    try {
        if (fts5_available($db)) {
            $sql = "SELECT COUNT(*) FROM programs_fts
                    JOIN programs p ON p.id = programs_fts.rowid
                    WHERE programs_fts MATCH :match"
                . ($eventId !== null ? " AND p.event_id = :eid" : "");
            $stmt = $db->prepare($sql);
            $stmt->bindValue(':match', $match);
            if ($eventId !== null) $stmt->bindValue(':eid', $eventId, PDO::PARAM_INT);
            $stmt->execute();
            return (int)$stmt->fetchColumn();
        }
    } catch (Exception $e) {}

    $like = '%' . str_replace(['%','_','\\'], ['\\%','\\_','\\\\'], $query) . '%';
    $sql  = "SELECT COUNT(*) FROM programs p
             WHERE (p.title LIKE :l OR p.categories LIKE :l OR p.organizer LIKE :l OR p.description LIKE :l ESCAPE '\\\\')"
          . ($eventId !== null ? " AND p.event_id = :eid" : "");
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':l', $like);
    if ($eventId !== null) $stmt->bindValue(':eid', $eventId, PDO::PARAM_INT);
    $stmt->execute();
    return (int)$stmt->fetchColumn();
}

/**
 * Full-text search programs.
 * Returns program rows + event_slug + event_name for link building.
 *
 * @param PDO      $db
 * @param string   $query   Raw user input
 * @param int|null $eventId Filter by event_id (null = all events)
 * @param int      $limit
 * @param int      $offset  For pagination
 * @return array   Each row: programs cols + event_slug + event_name + snippet
 */
function fts5_search_programs(PDO $db, string $query, ?int $eventId = null, int $limit = 50, int $offset = 0): array {
    $match = fts5_escape($query);
    if ($match === '') return [];

    try {
        if (fts5_available($db)) {
            $sql = "SELECT p.*, e.slug AS event_slug, e.name AS event_name,
                        snippet(programs_fts, 0, '<mark>', '</mark>', '…', 10) AS snippet
                    FROM programs_fts
                    JOIN programs p ON p.id = programs_fts.rowid
                    JOIN events e ON e.id = p.event_id"
                . " WHERE programs_fts MATCH :match"
                . ($eventId !== null ? " AND p.event_id = :eid" : "")
                . " ORDER BY p.start DESC LIMIT :lim OFFSET :off";
            $stmt = $db->prepare($sql);
            $stmt->bindValue(':match', $match);
            $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
            if ($eventId !== null) $stmt->bindValue(':eid', $eventId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        // fall through to LIKE
    }

    // LIKE fallback (also joins events for slug)
    $like = '%' . str_replace(['%','_','\\'], ['\\%','\\_','\\\\'], $query) . '%';
    $sql  = "SELECT p.*, e.slug AS event_slug, e.name AS event_name, '' AS snippet
             FROM programs p
             JOIN events e ON e.id = p.event_id
             WHERE (p.title LIKE :l OR p.categories LIKE :l OR p.organizer LIKE :l OR p.description LIKE :l ESCAPE '\\\\')"
          . ($eventId !== null ? " AND p.event_id = :eid" : "")
          . " ORDER BY p.start DESC LIMIT :lim OFFSET :off";
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':l', $like);
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
    if ($eventId !== null) $stmt->bindValue(':eid', $eventId, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Count total matching active events (for pagination).
 *
 * @param PDO    $db
 * @param string $query Raw user input
 * @return int
 */
function fts5_count_events(PDO $db, string $query): int {
    $match = fts5_escape($query);
    if ($match === '') return 0;

    try {
        if (fts5_available($db)) {
            $stmt = $db->prepare(
                "SELECT COUNT(*) FROM events_fts
                 JOIN events e ON e.id = events_fts.rowid
                 WHERE events_fts MATCH :match AND e.is_active = 1"
            );
            $stmt->bindValue(':match', $match);
            $stmt->execute();
            return (int)$stmt->fetchColumn();
        }
    } catch (Exception $e) {}

    $like = '%' . str_replace(['%','_','\\'], ['\\%','\\_','\\\\'], $query) . '%';
    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM events
         WHERE (name LIKE :l OR description LIKE :l ESCAPE '\\\\') AND is_active = 1"
    );
    $stmt->bindValue(':l', $like);
    $stmt->execute();
    return (int)$stmt->fetchColumn();
}

/**
 * Full-text search events (events_meta) with pagination support.
 *
 * @param PDO    $db
 * @param string $query
 * @param int    $limit
 * @param int    $offset  For pagination
 * @return array Each row is an events record + 'snippet' field
 */
function fts5_search_events(PDO $db, string $query, int $limit = 20, int $offset = 0): array {
    $match = fts5_escape($query);
    if ($match === '') return [];

    try {
        if (fts5_available($db)) {
            $stmt = $db->prepare(
                "SELECT e.*,
                     snippet(events_fts, 0, '<mark>', '</mark>', '…', 10) AS snippet
                 FROM events_fts
                 JOIN events e ON e.id = events_fts.rowid
                 WHERE events_fts MATCH :match AND e.is_active = 1
                 ORDER BY e.start_date DESC LIMIT :lim OFFSET :off"
            );
            $stmt->bindValue(':match', $match);
            $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {}

    $like = '%' . str_replace(['%','_','\\'], ['\\%','\\_','\\\\'], $query) . '%';
    $stmt = $db->prepare(
        "SELECT *, '' AS snippet FROM events
         WHERE (name LIKE :l OR description LIKE :l ESCAPE '\\\\') AND is_active = 1
         ORDER BY start_date DESC LIMIT :lim OFFSET :off"
    );
    $stmt->bindValue(':l', $like);
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Full-text search artists.
 *
 * @param PDO    $db
 * @param string $query
 * @param int    $limit
 * @return array Each row is an artists record + 'snippet' field
 */
function fts5_search_artists(PDO $db, string $query, int $limit = 20): array {
    $match = fts5_escape($query);
    if ($match === '') return [];

    try {
        if (fts5_available($db)) {
            $stmt = $db->prepare(
                "SELECT a.*,
                     snippet(artists_fts, 0, '<mark>', '</mark>', '…', 10) AS snippet
                 FROM artists_fts
                 JOIN artists a ON a.id = artists_fts.rowid
                 WHERE artists_fts MATCH :match
                 ORDER BY rank LIMIT :lim"
            );
            $stmt->bindValue(':match', $match);
            $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {}

    $like = '%' . str_replace(['%','_','\\'], ['\\%','\\_','\\\\'], $query) . '%';
    $stmt = $db->prepare(
        "SELECT *, '' AS snippet FROM artists WHERE name LIKE :l ESCAPE '\\\\' LIMIT :lim"
    );
    $stmt->bindValue(':l', $like);
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Cross-entity search: programs + events + artists in one call.
 *
 * @param PDO    $db
 * @param string $query
 * @return array ['programs' => [...], 'events' => [...], 'artists' => [...]]
 */
function fts5_search_all(PDO $db, string $query): array {
    return [
        'programs' => fts5_search_programs($db, $query, null, 30),
        'events'   => fts5_search_events($db, $query, 10),
        'artists'  => fts5_search_artists($db, $query, 10),
    ];
}

/**
 * Rebuild all FTS5 indexes from scratch.
 * Call after bulk insert operations (e.g. ICS import).
 *
 * @param PDO $db
 */
function fts5_rebuild_all(PDO $db): void {
    if (!fts5_available($db)) return;
    try {
        foreach (['programs_fts', 'events_fts', 'artists_fts'] as $t) {
            $db->exec("INSERT INTO $t($t) VALUES('rebuild')");
        }
    } catch (Exception $e) {
        error_log('FTS5 rebuild failed: ' . $e->getMessage());
    }
}
