<?php
/**
 * LibSuggest — api/import_csv.php
 * API untuk import koleksi buku dari CSV
 * 
 * POST params:
 *   books → array of book objects
 *
 * Response JSON:
 *   { imported: int, skipped: int, error?: string }
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
$books = $input['books'] ?? [];

// Validasi
if (empty($books) || !is_array($books)) {
    http_response_code(400);
    die(json_encode(['error' => 'Data buku tidak valid']));
}

if (count($books) > 1000) {
    http_response_code(400);
    die(json_encode(['error' => 'Maksimal 1000 buku per import']));
}

// Koneksi DB
$db = getDB();
$imported = 0;
$skipped = 0;

// Mulai transaction
$db->beginTransaction();

try {
    // Prepare statement untuk insert ke tabel bukulokal (struktur baru)
    $stmtLokal = $db->prepare(
        'INSERT INTO bukulokal (sku, isbn, judul, penulis, kategori, sub_kategori, tahun, ukuran, halaman, spek, harga, publisher, description, language)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
         judul = VALUES(judul),
         penulis = VALUES(penulis),
         kategori = VALUES(kategori),
         sub_kategori = VALUES(sub_kategori),
         tahun = VALUES(tahun),
         ukuran = VALUES(ukuran),
         halaman = VALUES(halaman),
         spek = VALUES(spek),
         harga = VALUES(harga),
         publisher = VALUES(publisher),
         description = VALUES(description),
         language = VALUES(language)'
    );

    // Prepare statement untuk insert ke tabel books (agar bisa diusulkan/divote)
    $stmtBooks = $db->prepare(
        'INSERT INTO books (google_id, isbn, title, authors, publisher, published_year, description, language, page_count)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
         title = VALUES(title),
         authors = VALUES(authors),
         publisher = VALUES(publisher),
         published_year = VALUES(published_year),
         description = VALUES(description),
         language = VALUES(language),
         page_count = VALUES(page_count)'
    );

    foreach ($books as $book) {
        // Normalize keys - support multiple naming conventions
        $sku = trim($book['SKU'] ?? $book['sku'] ?? $book['Kode'] ?? '');
        $isbn = trim($book['ISBN'] ?? $book['isbn'] ?? '');
        $judul = trim($book['Judul'] ?? $book['judul'] ?? $book['Title'] ?? $book['title'] ?? $book['Judul buku'] ?? $book['judul buku'] ?? '');
        $penulis = trim($book['Penulis'] ?? $book['penulis'] ?? $book['Authors'] ?? $book['authors'] ?? '');
        $kategori = trim($book['Kategori'] ?? $book['kategori'] ?? $book['Category'] ?? $book['Kategori 1'] ?? $book['kategori 1'] ?? '');
        $subKategori = trim($book['SubKategori'] ?? $book['sub_kategori'] ?? $book['SubCategory'] ?? $book['Kategori 2'] ?? $book['kategori 2'] ?? '');
        $tahun = trim($book['Tahun'] ?? $book['tahun'] ?? $book['PublishedYear'] ?? $book['publishedYear'] ?? $book['Ctkn Thn'] ?? $book['ctkn thn'] ?? '');
        $ukuran = trim($book['Ukuran'] ?? $book['ukuran'] ?? $book['Size'] ?? $book['Format'] ?? $book['format'] ?? '');
        $halaman = trim($book['Halaman'] ?? $book['halaman'] ?? $book['PageCount'] ?? $book['pageCount'] ?? $book['Hlm'] ?? $book['hlm'] ?? $book['Hal'] ?? $book['hal'] ?? '');
        $spek = trim($book['Spek'] ?? $book['spek'] ?? $book['Specification'] ?? '');
        $harga = trim($book['Harga'] ?? $book['harga'] ?? $book['Price'] ?? '');
        $publisher = trim($book['Publisher'] ?? $book['publisher'] ?? $book['Penerbit'] ?? '');
        $description = trim($book['Description'] ?? $book['description'] ?? $book['Deskripsi'] ?? $book['Ket'] ?? $book['ket'] ?? '');
        $language = trim($book['Language'] ?? $book['language'] ?? $book['Bahasa'] ?? 'id');

        // Validasi minimal (ISBN dan Judul wajib)
        if (empty($isbn) || empty($judul)) {
            $skipped++;
            continue;
        }

        // Normalize ISBN (hapus dash dan spasi)
        $isbn = preg_replace('/[\s\-]/', '', $isbn);

        // Validasi ISBN length
        if (!preg_match('/^\d{10,13}$/', $isbn)) {
            $skipped++;
            continue;
        }

        // Parse authors (split by semicolon)
        $authorArray = [];
        if (!empty($penulis)) {
            $authorArray = array_map('trim', explode(';', $penulis));
            $authorArray = array_filter($authorArray);
        }
        $authorsJson = !empty($authorArray) ? json_encode($authorArray) : null;

        // Parse tahun (hanya ambil 4 digit pertama)
        $publishedYear = null;
        if (!empty($tahun)) {
            preg_match('/\d{4}/', $tahun, $matches);
            $publishedYear = $matches[0] ?? null;
        }

        // Parse halaman (hanya angka)
        $halamanInt = null;
        if (!empty($halaman)) {
            preg_match('/\d+/', $halaman, $matches);
            $halamanInt = $matches[0] ?? null;
        }

        // Parse harga (hanya angka)
        $hargaDecimal = null;
        if (!empty($harga)) {
            preg_match('/[\d.,]+/', $harga, $matches);
            if ($matches) {
                $hargaDecimal = floatval(str_replace([',', '.'], ['', '.'], $matches[0]));
            }
        }

        try {
            // Insert ke tabel bukulokal
            $stmtLokal->execute([
                !empty($sku) ? $sku : null,
                $isbn,
                $judul,
                !empty($penulis) ? $penulis : null,
                !empty($kategori) ? $kategori : null,
                !empty($subKategori) ? $subKategori : null,
                $publishedYear,
                !empty($ukuran) ? $ukuran : null,
                $halamanInt,
                !empty($spek) ? $spek : null,
                $hargaDecimal,
                !empty($publisher) ? $publisher : null,
                !empty($description) ? $description : null,
                $language,
            ]);

            // Insert ke tabel books dengan google_id yang unik untuk buku lokal
            // Format: local_TIMESTAMP_ISBN untuk menghindari konflik
            $uniqueGoogleId = 'local_' . time() . '_' . $isbn;
            
            $stmtBooks->execute([
                $uniqueGoogleId,
                $isbn,
                $judul,
                $authorsJson,
                !empty($publisher) ? $publisher : null,
                $publishedYear,
                !empty($description) ? $description : null,
                $language,
                $halamanInt,
            ]);

            $imported++;
        } catch (PDOException $e) {
            // Skip jika ada error pada row ini (misal ISBN duplikat)
            $skipped++;
            continue;
        }
    }

    $db->commit();
} catch (Exception $e) {
    $db->rollBack();
    http_response_code(500);
    die(json_encode(['error' => 'Gagal mengimport data: ' . $e->getMessage()]));
}

die(json_encode([
    'imported' => $imported,
    'skipped' => $skipped,
    'message' => "Berhasil mengimport $imported buku"
]));
