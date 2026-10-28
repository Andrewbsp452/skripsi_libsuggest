<?php
/**
 * LibSuggest — api/isbn_search.php
 * Cari buku dari Google Books API berdasarkan ISBN
 * 
 * POST params:
 *   isbn → nomor ISBN (10 atau 13 digit)
 *
 * Response JSON:
 *   { book: {...} }  atau  { error: "..." }
 */

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');

// Hanya terima POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['error' => 'Method not allowed']));
}

// Harus login dan menjadi petugas
requirePetugas();

// Ambil data JSON
$input = json_decode(file_get_contents('php://input'), true);
$isbn  = trim($input['isbn'] ?? '');

// Validasi ISBN
if (empty($isbn)) {
    http_response_code(400);
    die(json_encode(['error' => 'ISBN harus diisi']));
}

if (!preg_match('/^\d{10,13}$/', $isbn)) {
    http_response_code(400);
    die(json_encode(['error' => 'ISBN harus berisi 10 atau 13 digit']));
}

// Query Google Books API dengan ISBN
$endpoint = 'https://www.googleapis.com/books/v1/volumes';
$params   = http_build_query([
    'q'   => 'isbn:' . $isbn,
    'key' => GOOGLE_BOOKS_API_KEY,
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

if (empty($data['items']) || count($data['items']) === 0) {
    die(json_encode(['error' => 'Buku dengan ISBN ini tidak ditemukan']));
}

// Parse hasil pertama
$item = $data['items'][0];
$volumeInfo = $item['volumeInfo'] ?? [];

// Validasi data minimal
if (empty($volumeInfo['title'])) {
    die(json_encode(['error' => 'Data buku tidak lengkap di Google Books']));
}

// Extract data
$bookData = [
    'google_id'     => $item['id'] ?? null,
    'isbn'          => $isbn,
    'title'         => $volumeInfo['title'] ?? '',
    'authors'       => $volumeInfo['authors'] ?? [],
    'publisher'     => $volumeInfo['publisher'] ?? '',
    'published_year' => isset($volumeInfo['publishedDate']) 
        ? (int)substr($volumeInfo['publishedDate'], 0, 4) 
        : null,
    'description'   => $volumeInfo['description'] ?? '',
    'cover_url'     => $volumeInfo['imageLinks']['thumbnail'] ?? null,
    'language'      => $volumeInfo['language'] ?? 'id',
    'page_count'    => $volumeInfo['pageCount'] ?? null,
];

die(json_encode(['book' => $bookData]));
