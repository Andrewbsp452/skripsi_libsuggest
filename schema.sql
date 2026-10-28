-- ============================================================
-- LibSuggest — Schema Database
-- Jalankan sekali di db_libsuggest
-- ============================================================

CREATE DATABASE IF NOT EXISTS db_libsuggest
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE db_libsuggest;

-- ──────────────────────────────────────────────
-- Tabel: local_accounts
-- Akun login untuk pemakaian lokal
-- ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS local_accounts (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(50)  NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    display_name  VARCHAR(150) NOT NULL,
    role          ENUM('pengusul','admin') NOT NULL DEFAULT 'pengusul',
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ──────────────────────────────────────────────
-- Tabel: bukulokal
-- Menyimpan data buku yang di-import dari CSV
-- (Buku lokal perpustakaan)
-- ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS bukulokal (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sku             VARCHAR(50)  DEFAULT NULL UNIQUE,
    isbn            VARCHAR(20)  NOT NULL,
    judul           VARCHAR(500) NOT NULL,
    penulis         VARCHAR(500) DEFAULT NULL,
    kategori        VARCHAR(100) DEFAULT NULL,
    sub_kategori    VARCHAR(100) DEFAULT NULL,
    tahun           YEAR        DEFAULT NULL,
    ukuran          VARCHAR(50)  DEFAULT NULL,
    halaman         SMALLINT UNSIGNED DEFAULT NULL,
    spek            TEXT         DEFAULT NULL,
    harga           DECIMAL(12, 2) DEFAULT NULL,
    publisher       VARCHAR(255) DEFAULT NULL,
    description     TEXT         DEFAULT NULL,
    cover_url       VARCHAR(1000) DEFAULT NULL,
    language        VARCHAR(10)  DEFAULT 'id',
    created_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_isbn      (isbn),
    INDEX idx_sku       (sku),
    INDEX idx_judul     (judul(100)),
    INDEX idx_kategori  (kategori),
    INDEX idx_tahun     (tahun),
    INDEX idx_created   (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ──────────────────────────────────────────────
-- Tabel: books
-- Menyimpan data buku yang pernah diusulkan
-- (cache dari Google Books API)
-- ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS books (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    google_id     VARCHAR(50)  NOT NULL UNIQUE,   -- ID dari Google Books
    isbn          VARCHAR(20)  DEFAULT NULL,
    title         VARCHAR(500) NOT NULL,
    authors       VARCHAR(500) DEFAULT NULL,       -- JSON array: ["Nama1","Nama2"]
    publisher     VARCHAR(255) DEFAULT NULL,
    published_year YEAR        DEFAULT NULL,
    description   TEXT         DEFAULT NULL,
    cover_url     VARCHAR(1000) DEFAULT NULL,      -- thumbnail dari Google Books
    language      VARCHAR(10)  DEFAULT 'id',
    page_count    SMALLINT UNSIGNED DEFAULT NULL,
    created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_google_id (google_id),
    INDEX idx_isbn      (isbn)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ──────────────────────────────────────────────
-- Tabel: kategori_verifikasi
-- Alasan penolakan usulan yang terstandar
-- ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS kategori_verifikasi (
    id              TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kode            VARCHAR(50)  NOT NULL UNIQUE,
    nama            VARCHAR(150) NOT NULL,
    perlu_penjelasan TINYINT(1)  NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO kategori_verifikasi (kode, nama, perlu_penjelasan) VALUES
    ('sudah_tersedia', 'Buku sudah tersedia di perpustakaan', 0),
    ('duplikat', 'Usulan duplikat', 0),
    ('kebijakan_koleksi', 'Tidak sesuai kebijakan pengembangan koleksi', 0),
    ('informasi_tidak_lengkap', 'Informasi buku tidak lengkap atau tidak valid', 0),
    ('belum_prioritas', 'Kebutuhan belum menjadi prioritas', 0),
    ('lainnya', 'Lainnya', 1)
ON DUPLICATE KEY UPDATE
    nama = VALUES(nama),
    perlu_penjelasan = VALUES(perlu_penjelasan);


-- ──────────────────────────────────────────────
-- Tabel: proposals
-- Satu row = satu user mengusulkan satu buku
-- Kalau dua user usul buku yang sama → dua row,
-- buku tetap satu di tabel books
-- ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS proposals (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    book_id       INT UNSIGNED NOT NULL,
    uid           VARCHAR(100) NOT NULL,    -- user ID dari session Palapa
    user_name     VARCHAR(255) NOT NULL,
    user_email    VARCHAR(255) NOT NULL,
    user_role     ENUM('mahasiswa','dosen','petugas') DEFAULT 'mahasiswa',
    alasan        TEXT         DEFAULT NULL,
    status        ENUM('pending','disetujui','dibeli','tersedia','ditolak')
                               DEFAULT 'pending',
    kategori_verifikasi_id TINYINT UNSIGNED DEFAULT NULL,
    note_petugas  TEXT         DEFAULT NULL,  -- catatan dari petugas saat review
    created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE,
    FOREIGN KEY (kategori_verifikasi_id) REFERENCES kategori_verifikasi(id) ON DELETE SET NULL,
    UNIQUE KEY uq_user_book (uid, book_id),   -- satu user satu usulan per buku
    INDEX idx_uid       (uid),
    INDEX idx_book_id   (book_id),
    INDEX idx_status    (status),
    INDEX idx_created   (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ──────────────────────────────────────────────
-- Tabel: usulan_manual
-- Penanda dan kata kunci pencarian untuk usulan manual
-- ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS usulan_manual (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    proposal_id INT UNSIGNED NOT NULL UNIQUE,
    kata_kunci  VARCHAR(255) DEFAULT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (proposal_id) REFERENCES proposals(id) ON DELETE CASCADE,
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ──────────────────────────────────────────────
-- Tabel: proposal_messages
-- Percakapan antara pengusul dan admin per usulan
-- ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS proposal_messages (
    id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    proposal_id  INT UNSIGNED NOT NULL,
    sender_uid   VARCHAR(100) NOT NULL,
    sender_name  VARCHAR(255) NOT NULL,
    sender_role  ENUM('pengusul','admin') NOT NULL,
    message      TEXT NOT NULL,
    read_at      DATETIME DEFAULT NULL,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (proposal_id) REFERENCES proposals(id) ON DELETE CASCADE,
    INDEX idx_proposal_created (proposal_id, created_at),
    INDEX idx_unread (proposal_id, sender_role, read_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ──────────────────────────────────────────────
-- Tabel: votes
-- Mendukung usulan yang sudah ada (bukan usulan baru)
-- Satu user bisa vote satu proposal (bukan per buku)
-- ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS votes (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    book_id       INT UNSIGNED NOT NULL,   -- redundan, tapi mempercepat query
    uid           VARCHAR(100) NOT NULL,
    created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE,
    UNIQUE KEY uq_vote (uid, book_id),     -- satu user satu vote per buku
    INDEX idx_book_id (book_id),
    INDEX idx_uid     (uid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ──────────────────────────────────────────────
-- View: v_book_summary
-- Dipakai di dashboard petugas & halaman utama
-- Menggabungkan jumlah proposal + vote per buku
-- ──────────────────────────────────────────────
CREATE OR REPLACE VIEW v_book_summary AS
SELECT
    b.id,
    b.google_id,
    b.isbn,
    b.title,
    b.authors,
    b.publisher,
    b.published_year,
    b.cover_url,
    b.language,
    b.page_count,
    COUNT(DISTINCT p.id)  AS total_proposals,  -- berapa user yg usul
    COUNT(DISTINCT v.id)  AS total_votes,       -- berapa user yg vote/dukung
    COUNT(DISTINCT p.id) + COUNT(DISTINCT v.id) AS total_dukungan,
    MIN(p.status)         AS status_min,        -- untuk cek ada yg approved atau tidak
    MAX(p.created_at)     AS last_proposed_at
FROM books b
LEFT JOIN proposals p ON p.book_id = b.id
LEFT JOIN votes     v ON v.book_id = b.id
GROUP BY b.id;


-- ──────────────────────────────────────────────
-- Contoh query yang akan sering dipakai:
-- ──────────────────────────────────────────────

-- Cek apakah user sudah usul buku tertentu:
-- SELECT id FROM proposals WHERE uid = ? AND book_id = ?

-- Cek apakah user sudah vote buku tertentu:
-- SELECT id FROM votes WHERE uid = ? AND book_id = ?

-- Daftar buku diurutkan dukungan terbanyak (dashboard):
-- SELECT * FROM v_book_summary ORDER BY total_dukungan DESC

-- Riwayat usulan satu user:
-- SELECT b.title, b.cover_url, p.alasan, p.status, p.created_at
-- FROM proposals p JOIN books b ON b.id = p.book_id
-- WHERE p.uid = ? ORDER BY p.created_at DESC
