<?php
/**
 * LibSuggest — config/db.php
 * Koneksi ke database db_libsuggest (terpisah dari DB Palapa).
 * Menggunakan PDO agar lebih aman (prepared statement bawaan).
 */

// ──────────────────────────────────────────────
// KONFIGURASI DATABASE — sesuaikan
// ──────────────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'db_libsuggest');
define('DB_USER', 'root');          // ganti dengan user MySQL kamu
define('DB_PASS', '');              // Laragon lokal root default tanpa password
define('DB_CHARSET', 'utf8mb4');

// ──────────────────────────────────────────────
// GOOGLE BOOKS API KEY
// ──────────────────────────────────────────────
// Daftar gratis di: https://console.cloud.google.com/
// Enable "Books API" lalu buat API Key
define('GOOGLE_BOOKS_API_KEY', 'AIzaSyCjPCiWNaKWJoHv_jDKxy7NUO14Ag-vyHQ');

// ──────────────────────────────────────────────
// KONEKSI PDO (Singleton)
// ──────────────────────────────────────────────
function getDB(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
        );
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            // Jangan expose error detail ke user di production
            http_response_code(500);
            die(json_encode(['error' => 'Koneksi database gagal.']));
        }
    }

    return $pdo;
}

/**
 * Helper: jalankan query dengan binding, return PDOStatement.
 * Contoh pakai: dbQuery('SELECT * FROM proposals WHERE uid = ?', [$uid])
 */
function dbQuery(string $sql, array $params = []): PDOStatement {
    $stmt = getDB()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

/**
 * Helper: ambil satu baris.
 */
function dbFetch(string $sql, array $params = []): ?array {
    $row = dbQuery($sql, $params)->fetch();
    return $row ?: null;
}

/**
 * Helper: ambil semua baris.
 */
function dbFetchAll(string $sql, array $params = []): array {
    return dbQuery($sql, $params)->fetchAll();
}

/**
 * Helper: insert, return last insert ID.
 */
function dbInsert(string $sql, array $params = []): string {
    dbQuery($sql, $params);
    return getDB()->lastInsertId();
}
