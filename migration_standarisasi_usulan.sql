USE db_libsuggest;

CREATE TABLE IF NOT EXISTS kategori_verifikasi (
    id               TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kode             VARCHAR(50)  NOT NULL UNIQUE,
    nama             VARCHAR(150) NOT NULL,
    perlu_penjelasan TINYINT(1)   NOT NULL DEFAULT 0
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

ALTER TABLE proposals
    ADD COLUMN kategori_verifikasi_id TINYINT UNSIGNED DEFAULT NULL,
    ADD CONSTRAINT fk_proposals_kategori_verifikasi
        FOREIGN KEY (kategori_verifikasi_id)
        REFERENCES kategori_verifikasi(id)
        ON DELETE SET NULL;

UPDATE proposals p
JOIN kategori_verifikasi kv ON kv.kode = 'lainnya'
SET p.kategori_verifikasi_id = kv.id,
    p.note_petugas = COALESCE(
        NULLIF(p.note_petugas, ''),
        'Penolakan ini tercatat sebelum kategori alasan standar diterapkan.'
    )
WHERE p.status = 'ditolak'
  AND p.kategori_verifikasi_id IS NULL;

CREATE TABLE IF NOT EXISTS usulan_manual (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    proposal_id INT UNSIGNED NOT NULL UNIQUE,
    kata_kunci  VARCHAR(255) DEFAULT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (proposal_id) REFERENCES proposals(id) ON DELETE CASCADE,
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
