<?php
/**
 * LibSuggest — api/propose.php
 * Buat usulan buku baru.
 * Otomatis simpan data buku ke tabel books (kalau belum ada).
 *
 * POST JSON body:
 *   google_id      → wajib
 *   alasan         → opsional
 *   title          → wajib (dari hasil search)
 *   authors        → array, opsional
 *   publisher      → opsional
 *   published_year → opsional
 *   cover_url      → opsional
 *   isbn           → opsional
 *   page_count     → opsional
 *   language       → opsional
 *
 * Response JSON:
 *   { success: true, book_id: int, proposal_id: int }
 *   { error: string }
 */

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['error' => 'Method not allowed']));
}

requireLogin();
$user = currentUser();

// ── Parse JSON body ─────────────────────────────────────────
$body = json_decode(file_get_contents('php://input'), true);
if (!$body) {
    http_response_code(400);
    die(json_encode(['error' => 'Request body tidak valid']));
}

$googleId = trim($body['google_id'] ?? '');
$title    = trim($body['title']     ?? '');
$alasan   = trim($body['alasan']    ?? '');

if (!$googleId || !$title) {
    http_response_code(400);
    die(json_encode(['error' => 'Data buku tidak lengkap']));
}

// Sanitasi
$alasan = mb_substr($alasan, 0, 1000);

// ── Upsert buku ke tabel books ──────────────────────────────
// Kalau google_id sudah ada → ambil id-nya
// Kalau belum → insert baru
$authorsJson = json_encode($body['authors'] ?? [], JSON_UNESCAPED_UNICODE);

$book = dbFetch('SELECT id FROM books WHERE google_id = ?', [$googleId]);

if ($book) {
    $bookId = $book['id'];

    // Update data kalau ada yang lebih baru (cover mungkin berubah)
    dbQuery(
        'UPDATE books SET
            cover_url      = COALESCE(NULLIF(?, ""), cover_url),
            authors        = COALESCE(NULLIF(?, "[]"), authors),
            publisher      = COALESCE(NULLIF(?, ""), publisher),
            published_year = COALESCE(NULLIF(?, 0), published_year),
            page_count     = COALESCE(NULLIF(?, 0), page_count)
         WHERE id = ?',
        [
            $body['cover_url']      ?? '',
            $authorsJson,
            $body['publisher']      ?? '',
            (int)($body['published_year'] ?? 0),
            (int)($body['page_count']     ?? 0),
            $bookId,
        ]
    );
} else {
    $bookId = (int) dbInsert(
        'INSERT INTO books
            (google_id, isbn, title, authors, publisher, published_year,
             cover_url, language, page_count)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [
            $googleId,
            $body['isbn']           ?? null,
            $title,
            $authorsJson,
            $body['publisher']      ?? null,
            $body['published_year'] ?? null,
            $body['cover_url']      ?? null,
            $body['language']       ?? null,
            $body['page_count']     ?? null,
        ]
    );
}

// ── Cek apakah user sudah pernah usul buku ini ─────────────
$existing = dbFetch(
    'SELECT id, status FROM proposals WHERE uid = ? AND book_id = ?',
    [$user['uid'], $bookId]
);

if ($existing) {
    // Sudah pernah usul → kembalikan info supaya frontend bisa switch ke mode vote
    die(json_encode([
        'already_proposed' => true,
        'book_id'          => $bookId,
        'proposal_id'      => $existing['id'],
        'status'           => $existing['status'],
    ]));
}

// ── Insert proposal baru ────────────────────────────────────
$proposalRole = $user['role'] === 'pegawai' ? 'petugas' : $user['role'];

$proposalId = (int) dbInsert(
    'INSERT INTO proposals
        (book_id, uid, user_name, user_email, user_role, alasan)
     VALUES (?, ?, ?, ?, ?, ?)',
    [
        $bookId,
        $user['uid'],
        $user['name'],
        $user['email'],
        $proposalRole,
        $alasan ?: null,
    ]
);

echo json_encode([
    'success'     => true,
    'book_id'     => $bookId,
    'proposal_id' => $proposalId,
]);
