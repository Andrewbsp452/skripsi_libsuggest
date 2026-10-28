-- ============================================================
-- SQL untuk menambah tabel bukulokal ke database db_libsuggest
-- Jalankan script ini di MySQL jika tabel belum ada
-- ============================================================

USE db_libsuggest;

-- ──────────────────────────────────────────────
-- Tabel: bukulokal
-- Menyimpan data buku yang di-import dari CSV/Excel
-- (Buku lokal perpustakaan)
-- ──────────────────────────────────────────────

-- Drop table lama jika ada (backup dulu!)
-- DROP TABLE IF EXISTS bukulokal;

DROP TABLE IF EXISTS bukulokal;

CREATE TABLE bukulokal (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sku             VARCHAR(50)  DEFAULT NULL UNIQUE,        -- Kode buku (misal: DP12396A)
    isbn            VARCHAR(20)  NOT NULL,                   -- ISBN
    judul           VARCHAR(500) NOT NULL,                   -- Judul buku
    penulis         VARCHAR(500) DEFAULT NULL,               -- Penulis (pisahkan dengan ;)
    kategori        VARCHAR(100) DEFAULT NULL,               -- Kategori utama (misal: UMUM, FIKSI, dll)
    sub_kategori    VARCHAR(100) DEFAULT NULL,               -- Sub kategori (misal: Anak-Anak, Cerita Dewasa)
    tahun           YEAR        DEFAULT NULL,                -- Tahun penerbitan/edisi
    ukuran          VARCHAR(50)  DEFAULT NULL,               -- Ukuran (misal: C5, A4, Custom)
    halaman         SMALLINT UNSIGNED DEFAULT NULL,          -- Jumlah halaman
    spek            TEXT         DEFAULT NULL,               -- Spesifikasi tambahan (misal: FC Bookpaper)
    harga           DECIMAL(12, 2) DEFAULT NULL,             -- Harga buku
    
    -- Data tambahan (backward compatibility)
    publisher       VARCHAR(255) DEFAULT NULL,               -- Penerbit
    description     TEXT         DEFAULT NULL,               -- Deskripsi
    cover_url       VARCHAR(1000) DEFAULT NULL,              -- URL cover
    language        VARCHAR(10)  DEFAULT 'id',               -- Bahasa
    
    created_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Indexes untuk performa
    INDEX idx_isbn      (isbn),
    INDEX idx_sku       (sku),
    INDEX idx_judul     (judul(100)),
    INDEX idx_kategori  (kategori),
    INDEX idx_tahun     (tahun),
    INDEX idx_created   (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pesan
SELECT 'Tabel bukulokal berhasil dibuat / sudah ada!' AS Status;
