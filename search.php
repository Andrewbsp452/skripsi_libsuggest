<?php
/**
 * LibSuggest — api/search.php
 * Proxy ke Google Books API.
 * Dipanggil via AJAX dari index.php
 *
 * GET params:
 *   q    → kata kunci (judul / penulis / ISBN)
 *   page → halaman (default 0, tiap halaman 12 buku)
 *
 * Response JSON:
 *   { books: [...], total: int, page: int }
 */

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');

// Hanya terima GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    die(json_encode(['error' => 'Method not allowed']));
}

// Harus login
requireLogin();
$user = currentUser();

// ── Validasi input ──────────────────────────────────────────
$q    = trim($_GET['q'] ?? '');
$page = max(0, (int)($_GET['page'] ?? 0));

if (strlen($q) < 2) {
    die(json_encode(['error' => 'Kata kunci minimal 2 karakter']));
}

$maxResults = 12;
$startIndex = $page * $maxResults;

// ── Panggil Google Books API ────────────────────────────────
$endpoint = 'https://www.googleapis.com/books/v1/volumes';
$params   = http_build_query([
    'q'          => $q,
    'startIndex' => $startIndex,
    'maxResults' => $maxResults,
    'langRestrict' => '',          // kosong = semua bahasa (termasuk Indonesia)
    'printType'  => 'books',
    'key'        => GOOGLE_BOOKS_API_KEY,
]);

$url = $endpoint . '?' . $params;

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_HTTPHEADER     => ['Accept: application/json'],
]);
$raw  = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err  = curl_error($ch);
curl_close($ch);

if ($err || $code !== 200) {
    http_response_code(502);
    die(json_encode(['error' => 'Gagal mengambil data dari Google Books. Coba lagi.']));
}

$data = json_decode($raw, true);

if (empty($data['items'])) {
    die(json_encode(['books' => [], 'total' => 0, 'page' => $page]));
}

// ── Ambil buku yang sudah pernah diusulkan ──────────────────
// Supaya kita bisa tandai: sudah diusulkan / belum, user sudah vote / belum
$googleIds = array_column(
    array_map(fn($item) => ['id' => $item['id']], $data['items']),
    'id'
);

// Cari book_id di DB berdasarkan google_id
$placeholders = implode(',', array_fill(0, count($googleIds), '?'));
$existingBooks = dbFetchAll(
    "SELECT id, google_id, title FROM books WHERE google_id IN ($placeholders)",
    $googleIds
);
$bookIdMap = array_column($existingBooks, 'id', 'google_id'); // [google_id => book_id]

// Cek proposal & vote user untuk buku-buku ini
$userProposals = [];
$userVotes     = [];

if (!empty($bookIdMap)) {
    $bookIds      = array_values($bookIdMap);
    $bPlaceholders = implode(',', array_fill(0, count($bookIds), '?'));

    $proposalRows = dbFetchAll(
        "SELECT book_id FROM proposals WHERE uid = ? AND book_id IN ($bPlaceholders)",
        array_merge([$user['uid']], $bookIds)
    );
    $userProposals = array_column($proposalRows, 'book_id');

    $voteRows = dbFetchAll(
        "SELECT book_id FROM votes WHERE uid = ? AND book_id IN ($bPlaceholders)",
        array_merge([$user['uid']], $bookIds)
    );
    $userVotes = array_column($voteRows, 'book_id');

    // Total dukungan per buku
    $summaryRows = dbFetchAll(
        "SELECT id, total_dukungan, total_proposals FROM v_book_summary WHERE id IN ($bPlaceholders)",
        $bookIds
    );
    $summaryMap = array_column($summaryRows, null, 'id');
}

// ── Format response ─────────────────────────────────────────
$googleBooks = [];
foreach ($data['items'] as $item) {
    $info     = $item['volumeInfo'] ?? [];
    $googleId = $item['id'];

    // Ambil cover — prefer large, fallback ke thumbnail
    $imageLinks = $info['imageLinks'] ?? [];
    $cover = $imageLinks['large']
          ?? $imageLinks['medium']
          ?? $imageLinks['thumbnail']
          ?? $imageLinks['smallThumbnail']
          ?? null;

    // Upgrade ke HTTPS dan hapus zoom param biar resolusi lebih bagus
    if ($cover) {
        $cover = str_replace('http://', 'https://', $cover);
        $cover = preg_replace('/&zoom=\d/', '', $cover);
    }

    // Tahun terbit
    $year = null;
    if (!empty($info['publishedDate'])) {
        preg_match('/^(\d{4})/', $info['publishedDate'], $m);
        $year = $m[1] ?? null;
    }

    // ISBN
    $isbn = null;
    foreach ($info['industryIdentifiers'] ?? [] as $id) {
        if ($id['type'] === 'ISBN_13') { $isbn = $id['identifier']; break; }
        if ($id['type'] === 'ISBN_10') $isbn = $id['identifier'];
    }

    $bookId   = $bookIdMap[$googleId] ?? null;
    $summary  = $bookId ? ($summaryMap[$bookId] ?? null) : null;

    $googleBooks[] = [
        'google_id'       => $googleId,
        'book_id'         => $bookId,           // null kalau belum pernah diusulkan
        'isbn'            => $isbn,
        'title'           => $info['title'] ?? 'Tanpa Judul',
        'authors'         => $info['authors'] ?? [],
        'publisher'       => $info['publisher'] ?? null,
        'published_year'  => $year,
        'description'     => isset($info['description'])
                             ? mb_substr($info['description'], 0, 300) . '...'
                             : null,
        'cover_url'       => $cover,
        'page_count'      => $info['pageCount'] ?? null,
        'language'        => $info['language'] ?? null,
        'total_dukungan'  => $summary['total_dukungan'] ?? 0,
        'already_proposed'=> $bookId && in_array($bookId, $userProposals),
        'already_voted'   => $bookId && in_array($bookId, $userVotes),
        'source'          => 'google',
    ];
}

// ── Cari juga di database lokal dan usulan manual ──
// Buku yang tidak ditemukan di Google Books tetap dapat ditemukan dan didukung.
$googleIsbns = array_filter(array_column($googleBooks, 'isbn'));

$searchTerm = '%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%';

if (empty($googleIsbns)) {
    // Tidak ada ISBN dari Google, query normal untuk buku lokal
    $dbBooks = dbFetchAll(
        "SELECT id, google_id, isbn, title, authors, publisher, published_year, cover_url, page_count, language, description
         FROM books 
         WHERE (google_id LIKE 'local_%' OR google_id LIKE 'manual_%' OR google_id IS NULL)
         AND (title LIKE ? OR authors LIKE ? OR isbn LIKE ?)
         ORDER BY created_at DESC
         LIMIT 12",
        [$searchTerm, $searchTerm, $searchTerm]
    );
} else {
    // Ada ISBN dari Google, exclude mereka untuk menghindari duplikat
    $isbnPlaceholders = implode(',', array_fill(0, count($googleIsbns), '?'));
    $dbBooks = dbFetchAll(
        "SELECT id, google_id, isbn, title, authors, publisher, published_year, cover_url, page_count, language, description
         FROM books 
         WHERE (google_id LIKE 'local_%' OR google_id LIKE 'manual_%' OR google_id IS NULL)
         AND isbn NOT IN ($isbnPlaceholders)
         AND (title LIKE ? OR authors LIKE ? OR isbn LIKE ?)
         ORDER BY created_at DESC
         LIMIT 12",
        array_merge($googleIsbns, [$searchTerm, $searchTerm, $searchTerm])
    );
}

// Tambahkan buku dari database ke result
$localBooks = [];
foreach ($dbBooks as $dbBook) {
    $authors = [];
    if (!empty($dbBook['authors'])) {
        $parsed = json_decode($dbBook['authors'], true);
        $authors = is_array($parsed) ? $parsed : [];
    }

    $dbBookId = $dbBook['id'];
    $alreadyProposed = false;
    $alreadyVoted = false;

    if ($dbBookId) {
        $proposalCheck = dbFetchAll(
            "SELECT id FROM proposals WHERE uid = ? AND book_id = ?",
            [$user['uid'], $dbBookId]
        );
        $alreadyProposed = !empty($proposalCheck);

        $voteCheck = dbFetchAll(
            "SELECT id FROM votes WHERE uid = ? AND book_id = ?",
            [$user['uid'], $dbBookId]
        );
        $alreadyVoted = !empty($voteCheck);
    }

    // Ambil total dukungan
    $dbSummary = dbFetchAll(
        "SELECT total_dukungan FROM v_book_summary WHERE id = ?",
        [$dbBookId]
    );
    $totalDukungan = !empty($dbSummary) ? $dbSummary[0]['total_dukungan'] : 0;

    // Tentukan asal data buku
    $isLocal = !empty($dbBook['google_id']) && strpos($dbBook['google_id'], 'local_') === 0;
    $isManual = !empty($dbBook['google_id']) && strpos($dbBook['google_id'], 'manual_') === 0;

    $localBooks[] = [
        'google_id'       => $dbBook['google_id'],
        'book_id'         => $dbBook['id'],
        'isbn'            => $dbBook['isbn'],
        'title'           => $dbBook['title'],
        'authors'         => $authors,
        'publisher'       => $dbBook['publisher'],
        'published_year'  => $dbBook['published_year'],
        'description'     => $dbBook['description'],
        'cover_url'       => $dbBook['cover_url'],
        'page_count'      => $dbBook['page_count'],
        'language'        => $dbBook['language'],
        'total_dukungan'  => $totalDukungan,
        'already_proposed'=> $alreadyProposed,
        'already_voted'   => $alreadyVoted,
        'source'          => $isManual ? 'manual' : ($isLocal ? 'local' : 'google'),
    ];
}

$books = array_merge($localBooks, $googleBooks);

echo json_encode([
    'books' => $books,
    'total' => count($books),
    'page'  => $page,
]);
