<?php
/**
 * LibSuggest — api/propose_manual.php
 * Buat usulan buku yang tidak ditemukan melalui pencarian.
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
$body = json_decode(file_get_contents('php://input'), true);

if (!is_array($body)) {
    http_response_code(400);
    die(json_encode(['error' => 'Request body tidak valid']));
}

foreach (['title', 'authors', 'publisher', 'isbn', 'published_year', 'page_count', 'alasan', 'kata_kunci'] as $field) {
    if (isset($body[$field]) && !is_string($body[$field]) && !is_numeric($body[$field])) {
        http_response_code(400);
        die(json_encode(['error' => 'Format data buku tidak valid']));
    }
}

$title = trim($body['title'] ?? '');
$authors = trim($body['authors'] ?? '');
$publisher = trim($body['publisher'] ?? '');
$isbn = trim($body['isbn'] ?? '');
$publishedYear = trim((string)($body['published_year'] ?? ''));
$pageCount = trim((string)($body['page_count'] ?? ''));
$alasan = trim($body['alasan'] ?? '');
$searchQuery = trim($body['kata_kunci'] ?? '');

if ($title === '' || mb_strlen($title) > 500) {
    http_response_code(400);
    die(json_encode(['error' => 'Judul wajib diisi dan maksimal 500 karakter']));
}
if (mb_strlen($authors) > 500 || mb_strlen($publisher) > 255 || strlen($isbn) > 20) {
    http_response_code(400);
    die(json_encode(['error' => 'Data penulis, penerbit, atau ISBN terlalu panjang']));
}
if ($publishedYear !== '' && (!ctype_digit($publishedYear) || (int)$publishedYear < 1901 || (int)$publishedYear > 2155)) {
    http_response_code(400);
    die(json_encode(['error' => 'Tahun terbit harus berupa tahun 1901 sampai 2155']));
}
if ($pageCount !== '' && (!ctype_digit($pageCount) || (int)$pageCount < 1 || (int)$pageCount > 65535)) {
    http_response_code(400);
    die(json_encode(['error' => 'Jumlah halaman harus berupa angka antara 1 sampai 65535']));
}

$alasan = mb_substr($alasan, 0, 1000);
$searchQuery = mb_substr($searchQuery, 0, 255);
$authorsJson = $authors !== ''
    ? json_encode(array_map('trim', explode(',', $authors)), JSON_UNESCAPED_UNICODE)
    : null;
$publishedYear = $publishedYear !== '' ? (int)$publishedYear : null;
$pageCount = $pageCount !== '' ? (int)$pageCount : null;
$bookId = null;

try {
    getDB()->beginTransaction();

    if ($isbn !== '') {
        $existingBook = dbFetch('SELECT id FROM books WHERE isbn = ? LIMIT 1', [$isbn]);
        if ($existingBook) {
            $bookId = (int)$existingBook['id'];
        }
    }

    if ($bookId === null) {
        $googleId = 'manual_' . bin2hex(random_bytes(16));
        $bookId = (int)dbInsert(
            'INSERT INTO books
                (google_id, isbn, title, authors, publisher, published_year, language, page_count)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $googleId,
                $isbn !== '' ? $isbn : null,
                $title,
                $authorsJson,
                $publisher !== '' ? $publisher : null,
                $publishedYear,
                'id',
                $pageCount,
            ]
        );
    }

    $existingProposal = dbFetch(
        'SELECT id, status FROM proposals WHERE uid = ? AND book_id = ?',
        [$user['uid'], $bookId]
    );

    if ($existingProposal) {
        getDB()->commit();
        echo json_encode([
            'already_proposed' => true,
            'book_id' => $bookId,
            'proposal_id' => (int)$existingProposal['id'],
            'status' => $existingProposal['status'],
        ]);
        exit;
    }

    $proposalRole = $user['role'] === 'pegawai' ? 'petugas' : $user['role'];
    $proposalId = (int)dbInsert(
        'INSERT INTO proposals (book_id, uid, user_name, user_email, user_role, alasan)
         VALUES (?, ?, ?, ?, ?, ?)',
        [
            $bookId,
            $user['uid'],
            $user['name'],
            $user['email'],
            $proposalRole,
            $alasan !== '' ? $alasan : null,
        ]
    );
    dbInsert(
        'INSERT INTO usulan_manual (proposal_id, kata_kunci) VALUES (?, ?)',
        [$proposalId, $searchQuery !== '' ? $searchQuery : null]
    );

    getDB()->commit();
} catch (PDOException $e) {
    if (getDB()->inTransaction()) {
        getDB()->rollBack();
    }
    http_response_code(500);
    die(json_encode(['error' => 'Usulan buku tidak dapat disimpan. Silakan coba lagi.']));
}

echo json_encode([
    'success' => true,
    'book_id' => $bookId,
    'proposal_id' => $proposalId,
]);
