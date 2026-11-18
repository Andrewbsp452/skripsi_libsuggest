<?php
/**
 * LibSuggest — api/dashboard.php
 * Data untuk halaman petugas perpustakaan.
 *
 * GET params:
 *   action → 'list' | 'detail' | 'update_status'
 *   book_id     → (untuk detail & update_status)
 *   status      → filter status (untuk list): all | pending | disetujui | dibeli | tersedia | ditolak
 *   sort        → dukungan | terbaru (default: dukungan)
 *   page        → halaman (default 0)
 *
 * POST params (untuk update_status):
 *   book_id      → int
 *   proposal_id  → int (kalau update per proposal)
 *   new_status   → enum
 *   note         → opsional, catatan petugas
 */

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');

requireDashboardAccess(); // Semua user login bisa akses (mahasiswa, dosen, petugas)
$user = currentUser();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'list';

if ($method === 'GET' && $action === 'rejection_reasons') {
    requirePetugas();
    $categories = dbFetchAll(
        'SELECT id, kode, nama, perlu_penjelasan
         FROM kategori_verifikasi
         ORDER BY id'
    );
    echo json_encode(['categories' => $categories]);
    exit;
}

if ($method === 'GET' && $action === 'catalog') {
    $catalog = dbFetchAll(
        "SELECT
            b.id,
            b.title,
            b.authors,
            b.publisher,
            b.cover_url,
            bs.total_dukungan,
            p.status AS status_proposal,
            p.updated_at AS status_changed_at
         FROM books b
         JOIN v_book_summary bs ON bs.id = b.id
         JOIN proposals p ON p.book_id = b.id
         WHERE p.id = (
             SELECT latest.id
             FROM proposals latest
             WHERE latest.book_id = b.id
             ORDER BY latest.updated_at DESC, latest.id DESC
             LIMIT 1
         )
           AND p.status IN ('dibeli', 'ditolak')
           AND p.updated_at >= DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 6 MONTH)
         ORDER BY p.updated_at DESC"
    );

    foreach ($catalog as &$book) {
        $book['authors'] = json_decode($book['authors'] ?? '[]', true);
    }
    unset($book);

    echo json_encode(['books' => $catalog]);
    exit;
}

// ============================================================
// GET: list semua usulan (untuk tabel polling)
// ============================================================
if ($method === 'GET' && $action === 'list') {
    $status  = $_GET['status'] ?? 'all';
    $sort    = $_GET['sort']   ?? 'dukungan';
    $page    = max(0, (int)($_GET['page'] ?? 0));
    $perPage = 20;
    $offset  = $page * $perPage;

    $allowedStatus = ['all','pending','disetujui'];
    if (!in_array($status, $allowedStatus)) $status = 'all';

    // Sort
    $orderBy = $sort === 'terbaru'
        ? 'bs.last_proposed_at DESC'
        : 'bs.total_dukungan DESC, bs.last_proposed_at DESC';

    // Query utama: buku + summary dukungan + list pengusul
    $books = dbFetchAll(
        "SELECT
            bs.id,
            bs.google_id,
            bs.title,
            bs.authors,
            bs.publisher,
            bs.published_year,
            bs.cover_url,
            bs.total_proposals,
            bs.total_votes,
            bs.total_dukungan,
            bs.last_proposed_at,
            (
                SELECT GROUP_CONCAT(
                    DISTINCT CONCAT(members.user_name, ' (', members.user_role, ')')
                    ORDER BY members.created_at ASC
                    SEPARATOR ', '
                )
                FROM proposals members
                WHERE members.book_id = bs.id
            ) AS pengusul_list,
            latest.status AS status_proposal
         FROM v_book_summary bs
         JOIN proposals latest ON latest.book_id = bs.id
         WHERE latest.id = (
             SELECT p3.id
             FROM proposals p3
             WHERE p3.book_id = bs.id
             ORDER BY p3.updated_at DESC, p3.id DESC
             LIMIT 1
         )
           AND latest.status NOT IN ('dibeli', 'ditolak')
         " . ($status !== 'all' ? "AND latest.status = ?" : "") . "
         ORDER BY $orderBy
         LIMIT $perPage OFFSET $offset",
        $status !== 'all' ? [$status] : []
    );

    // Total untuk pagination
    $total = dbFetch(
        "SELECT COUNT(*) AS cnt
         FROM proposals latest
         WHERE latest.id = (
             SELECT p3.id FROM proposals p3
             WHERE p3.book_id = latest.book_id
             ORDER BY p3.updated_at DESC, p3.id DESC
             LIMIT 1
         )
           AND latest.status NOT IN ('dibeli', 'ditolak')"
        . ($status !== 'all' ? " AND latest.status = ?" : ""),
        $status !== 'all' ? [$status] : []
    );

    // Metrics ringkasan
    $metrics = dbFetch(
        "SELECT
            COUNT(DISTINCT book_id)                                      AS total_buku,
            SUM(status = 'pending')                                      AS pending,
            SUM(status = 'disetujui')                                    AS disetujui,
            SUM(status = 'dibeli')                                       AS dibeli,
            COUNT(DISTINCT uid)                                          AS total_user
         FROM proposals"
    );

    // Decode authors JSON untuk setiap buku
    foreach ($books as &$b) {
        $b['authors'] = json_decode($b['authors'] ?? '[]', true);
    }
    unset($b);

    echo json_encode([
        'books'   => $books,
        'total'   => (int)($total['cnt'] ?? 0),
        'page'    => $page,
        'metrics' => $metrics,
    ]);
    exit;
}

// ============================================================
// GET: detail satu buku (siapa saja yang usul)
// ============================================================
if ($method === 'GET' && $action === 'detail') {
    $bookId = (int)($_GET['book_id'] ?? 0);
    if (!$bookId) { http_response_code(400); die(json_encode(['error' => 'book_id wajib'])); }

    $book = dbFetch(
        'SELECT * FROM v_book_summary WHERE id = ?',
        [$bookId]
    );
    if (!$book) { http_response_code(404); die(json_encode(['error' => 'Buku tidak ditemukan'])); }
    $book['authors'] = json_decode($book['authors'] ?? '[]', true);

    $proposals = dbFetchAll(
        'SELECT p.id, p.uid, p.user_name, p.user_email, p.user_role, p.alasan,
                p.status, p.note_petugas, kv.nama AS kategori_verifikasi, p.created_at
         FROM proposals p
         LEFT JOIN kategori_verifikasi kv ON kv.id = p.kategori_verifikasi_id
         WHERE p.book_id = ? ORDER BY p.created_at ASC',
        [$bookId]
    );

    echo json_encode(['book' => $book, 'proposals' => $proposals]);
    exit;
}

// ============================================================
// POST: update status proposal
// ============================================================
if ($method === 'POST' && $action === 'update_status') {
    requirePetugas(); // Hanya petugas yang bisa ubah status
    $body = json_decode(file_get_contents('php://input'), true) ?? [];

    $proposalId = (int)($body['proposal_id'] ?? 0);
    $bookId     = (int)($body['book_id']     ?? 0);
    $newStatus  = $body['new_status']  ?? '';
    $note       = mb_substr($body['note'] ?? '', 0, 500);
    $categoryId = (int)($body['kategori_verifikasi_id'] ?? 0);

    $allowed = ['pending','disetujui','dibeli','tersedia','ditolak'];
    if (!in_array($newStatus, $allowed)) {
        http_response_code(400);
        die(json_encode(['error' => 'Status tidak valid']));
    }

    if ($newStatus === 'ditolak') {
        if (!$categoryId) {
            http_response_code(400);
            die(json_encode(['error' => 'Kategori alasan penolakan wajib dipilih']));
        }

        $category = dbFetch(
            'SELECT perlu_penjelasan FROM kategori_verifikasi WHERE id = ?',
            [$categoryId]
        );
        if (!$category) {
            http_response_code(400);
            die(json_encode(['error' => 'Kategori alasan penolakan tidak valid']));
        }
        if ((int)$category['perlu_penjelasan'] === 1 && trim($note) === '') {
            http_response_code(400);
            die(json_encode(['error' => 'Penjelasan wajib diisi untuk kategori Lainnya']));
        }
    } else {
        $categoryId = 0;
        $note = '';
    }

    $categoryId = $categoryId ?: null;
    $note = $note ?: null;
    if ($proposalId) {
        // Update satu proposal spesifik
        dbQuery(
            'UPDATE proposals
             SET status = ?, kategori_verifikasi_id = ?, note_petugas = ?
             WHERE id = ?',
            [$newStatus, $categoryId, $note, $proposalId]
        );
    } elseif ($bookId) {
        // Update SEMUA proposal untuk buku ini (bulk approve/reject)
        dbQuery(
            'UPDATE proposals
             SET status = ?, kategori_verifikasi_id = ?, note_petugas = ?
             WHERE book_id = ?',
            [$newStatus, $categoryId, $note, $bookId]
        );
    } else {
        http_response_code(400);
        die(json_encode(['error' => 'proposal_id atau book_id wajib']));
    }

    echo json_encode(['success' => true, 'new_status' => $newStatus]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Action tidak dikenali']);