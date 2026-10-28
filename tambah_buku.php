<?php
/**
 * LibSuggest — api/tambah_buku.php
 * Simpan buku baru ke koleksi (dari petugas)
 * 
 * POST params:
 *   book → object dengan data buku dari ISBN search
 *
 * Response JSON:
 *   { success: true, book_id: int }  atau  { error: "..." }
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
$user = currentUser();

// Ambil data JSON
$input = json_decode(file_get_contents('php://input'), true);
$book  = $input['book'] ?? null;

// Validasi data buku
if (empty($book)) {
    http_response_code(400);
    die(json_encode(['error' => 'Data buku tidak valid']));
}

if (empty($book['title'])) {
    http_response_code(400);
    die(json_encode(['error' => 'Judul buku harus ada']));
}

if (empty($book['isbn'])) {
    http_response_code(400);
    die(json_encode(['error' => 'ISBN harus ada']));
}

// Koneksi DB
$db = getDB();

// Cek apakah buku sudah ada di database (berdasarkan ISBN)
$stmt = $db->prepare('SELECT id FROM books WHERE isbn = ?');
$stmt->execute([$book['isbn']]);
$existingBook = $stmt->fetch();

$bookId = null;

if ($existingBook) {
    // Buku sudah ada, gunakan ID yang ada
    $bookId = $existingBook['id'];
} else {
    // Buku baru, insert ke database
    $stmt = $db->prepare(
        'INSERT INTO books (google_id, isbn, title, authors, publisher, published_year, description, cover_url, language, page_count)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    
    try {
        $stmt->execute([
            $book['google_id'] ?? null,
            $book['isbn'],
            $book['title'],
            !empty($book['authors']) ? json_encode($book['authors']) : null,
            $book['publisher'] ?? null,
            $book['published_year'] ?? null,
            $book['description'] ?? null,
            $book['cover_url'] ?? null,
            $book['language'] ?? 'id',
            $book['page_count'] ?? null,
        ]);
        
        $bookId = $db->lastInsertId();
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
            // Google ID sudah ada (jarang terjadi)
            $stmt = $db->prepare('SELECT id FROM books WHERE google_id = ?');
            $stmt->execute([$book['google_id']]);
            $existing = $stmt->fetch();
            $bookId = $existing['id'];
        } else {
            http_response_code(500);
            die(json_encode(['error' => 'Gagal menyimpan buku ke database']));
        }
    }
}

// Jika berhasil
die(json_encode([
    'success'  => true,
    'book_id'  => $bookId,
    'message'  => 'Buku berhasil ditambahkan ke koleksi'
]));
