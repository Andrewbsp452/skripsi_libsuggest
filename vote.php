<?php
/**
 * LibSuggest — api/vote.php
 * Toggle vote (dukung / batalkan dukungan) untuk buku.
 * User tidak bisa vote buku yang dia sendiri usulkan
 * (karena propose sudah otomatis jadi 1 dukungan).
 *
 * POST JSON body:
 *   book_id → int, wajib
 *
 * Response JSON:
 *   { voted: bool, total_dukungan: int }
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

$body   = json_decode(file_get_contents('php://input'), true);
$bookId = (int)($body['book_id'] ?? 0);

if (!$bookId) {
    http_response_code(400);
    die(json_encode(['error' => 'book_id tidak valid']));
}

// Pastikan buku ada
$book = dbFetch('SELECT id FROM books WHERE id = ?', [$bookId]);
if (!$book) {
    http_response_code(404);
    die(json_encode(['error' => 'Buku tidak ditemukan']));
}

// Cek apakah user adalah pengusul buku ini
// Pengusul tidak perlu vote lagi — propose sudah hitung sebagai dukungan
$isProposer = dbFetch(
    'SELECT id FROM proposals WHERE uid = ? AND book_id = ?',
    [$user['uid'], $bookId]
);
if ($isProposer) {
    die(json_encode(['error' => 'Kamu sudah mengusulkan buku ini. Dukunganmu sudah terhitung.']));
}

// ── Toggle vote ─────────────────────────────────────────────
$existingVote = dbFetch(
    'SELECT id FROM votes WHERE uid = ? AND book_id = ?',
    [$user['uid'], $bookId]
);

if ($existingVote) {
    // Sudah vote → unvote
    dbQuery('DELETE FROM votes WHERE uid = ? AND book_id = ?', [$user['uid'], $bookId]);
    $voted = false;
} else {
    // Belum vote → vote
    dbInsert(
        'INSERT INTO votes (book_id, uid) VALUES (?, ?)',
        [$bookId, $user['uid']]
    );
    $voted = true;
}

// Ambil total dukungan terbaru dari view
$summary = dbFetch(
    'SELECT total_dukungan FROM v_book_summary WHERE id = ?',
    [$bookId]
);

echo json_encode([
    'voted'          => $voted,
    'total_dukungan' => $summary['total_dukungan'] ?? 0,
]);