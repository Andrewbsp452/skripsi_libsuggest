## 📋 SETUP GUIDE - LibSuggest CSV Import & Tambah Buku

Semua file sudah dibuat dan siap digunakan. Ikuti langkah ini untuk melengkapi setup:

### 📝 Usulan Manual dan Alasan Penolakan Terstandar

Pengguna dapat mengusulkan buku secara manual setelah pencarian tidak menemukan buku di Google Books maupun koleksi LibSuggest. Petugas wajib memilih kategori alasan saat menolak usulan; kategori dan penjelasan akan tampil pada Riwayat Saya milik pengusul.

Jika database sudah terpasang, jalankan **sekali** file `config/migration_standarisasi_usulan.sql` melalui tab SQL di phpMyAdmin pada database `db_libsuggest`. Untuk database baru, gunakan `config/schema.sql` yang sudah mencakup perubahan ini dan jangan jalankan migrasi tersebut lagi.

### 🔐 Login Lokal dan Chat Keberatan

Untuk database lama, jalankan `config/migration_standarisasi_usulan.sql` terlebih dahulu jika belum pernah dijalankan, lalu jalankan `config/migration_local_auth_chat.sql`. Masing-masing migrasi dijalankan sekali. Pada database baru, gunakan `config/schema.sql` saja.

- Buka `login.php` untuk masuk dan `register.php` untuk membuat akun pengusul.
- Pendaftaran hanya membuat akun pengusul. Buat admin dengan mendaftarkan akun terlebih dahulu, lalu ubah role-nya melalui phpMyAdmin:

```sql
UPDATE local_accounts SET role = 'admin' WHERE username = 'username_admin';
```

- Setelah role admin diubah, logout lalu login kembali agar sesi membaca role terbaru.
- Untuk usulan berstatus Ditolak, pengusul bisa membuka **Riwayat Saya → Diskusikan dengan admin**. Admin membalas melalui bagian **Percakapan keberatan** pada Dashboard. Pesan disimpan per usulan dan daftar pesan diperbarui otomatis saat percakapan terbuka.
- Login username/password dan pendaftaran lokal hanya aktif pada localhost. Di hosting, alur login PALAPA yang sudah ada tetap digunakan.

### ✅ File-File yang Sudah Dibuat:

#### Halaman Utama (Direktori Root):
- ✓ `tambah_buku.php` - Halaman menambah buku via ISBN
- ✓ `import_csv.php` - Halaman import koleksi dari CSV
- ✓ `index.php` - Updated dengan menu baru
- ✓ `riwayat.php` - Updated dengan menu baru
- ✓ `dashboard.php` - Updated dengan menu baru

#### API (api/):
- ✓ `api/propose_manual.php` - API untuk menyimpan usulan buku manual
- ✓ `api/chat.php` - API percakapan per usulan yang ditolak
- ✓ `api/isbn_search.php` - API cari buku dari Google Books by ISBN
- ✓ `api/tambah_buku.php` - API simpan buku ke tabel books
- ✓ `api/import_csv.php` - API import CSV ke bukulokal + books
- ✓ `api/search.php` - Updated dengan pencarian buku lokal

#### Database:
- ✓ `config/schema.sql` - Updated dengan tabel bukulokal
- ✓ `config/create_bukulokal.sql` - SQL untuk membuat tabel bukulokal
- ✓ `config/migration_standarisasi_usulan.sql` - Migrasi kategori verifikasi dan usulan manual
- ✓ `config/migration_local_auth_chat.sql` - Migrasi akun lokal dan pesan chat

---

### 🔧 LANGKAH SETUP DATABASE (WAJIB):

**Sebelum testing, Anda HARUS membuat tabel `bukulokal` di database:**

#### Opsi 1: Menggunakan phpMyAdmin
1. Buka **phpMyAdmin** → Login
2. Pilih database **`db_libsuggest`**
3. Tab **"SQL"**
4. Copy-paste isi file: `/usulan/config/create_bukulokal.sql`
5. Klik **"Go"**

#### Opsi 2: Menggunakan MySQL Command Line
```bash
mysql -u root -pPaaswordeTakGant1#2024 db_libsuggest < /var/www/html/bulian/palapa/usulan/config/create_bukulokal.sql
```

#### Opsi 3: Manual Query di phpMyAdmin
```sql
CREATE TABLE IF NOT EXISTS bukulokal (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    isbn          VARCHAR(20)  NOT NULL UNIQUE,
    title         VARCHAR(500) NOT NULL,
    authors       VARCHAR(500) DEFAULT NULL,
    publisher     VARCHAR(255) DEFAULT NULL,
    published_year YEAR        DEFAULT NULL,
    description   TEXT         DEFAULT NULL,
    cover_url     VARCHAR(1000) DEFAULT NULL,
    language      VARCHAR(10)  DEFAULT 'id',
    page_count    SMALLINT UNSIGNED DEFAULT NULL,
    created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_isbn      (isbn),
    INDEX idx_title     (title(100)),
    INDEX idx_created   (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### 🚀 CARA MENGGUNAKAN:

#### Untuk Petugas (Role: pegawai):

**1. Tambah Buku via ISBN:**
- Menu: "Tambah Buku" di navbar
- Input nomor ISBN (10 atau 13 digit)
- Sistem cari di Google Books API
- Tampil data buku (judul, penulis, penerbit, tahun, halaman)
- Klik "Tambah ke Koleksi"
- Buku disimpan ke tabel `books`

**2. Import Koleksi dari CSV:**
- Menu: "Import CSV" di navbar
- Download template CSV (atau buat sendiri)
- Format kolom: ISBN, Title, Authors, Publisher, PublishedYear, PageCount, Description, Language
- Drag & drop atau klik untuk upload
- Preview data sebelum import
- Klik "Import ke Database"
- Buku disimpan ke tabel `bukulokal` + `books`

#### Untuk Mahasiswa/Dosen:

**1. Cari Buku:**
- Menu: "Cari Buku" di navbar
- Input kata kunci (judul, penulis, ISBN, topik)
- Sistem mencari di:
  - Google Books API
  - Database lokal (tabel books dengan buku lokal)
- Hasil gabungan, tanpa duplikat
- Bisa usulkan atau vote buku apapun

---

### 📊 ALUR DATA:

```
IMPORT CSV:
CSV File
  ↓
  api/import_csv.php
  ↓
  Parsing & Validasi
  ↓
  ├─ INSERT → Tabel bukulokal (penyimpanan)
  └─ INSERT → Tabel books (untuk usulan/voting)

TAMBAH BUKU VIA ISBN:
ISBN Input
  ↓
  api/isbn_search.php
  ↓
  Query Google Books API
  ↓
  api/tambah_buku.php
  ↓
  INSERT → Tabel books

PENCARIAN:
Kata Kunci
  ↓
  api/search.php
  ↓
  Query Google Books API (halaman 1)
  ↓
  Query Tabel books (buku lokal: google_id LIKE 'local_%')
  ↓
  Gabung hasil, hindari duplikat ISBN
  ↓
  Return dengan flag source: 'google' atau 'local'
```

---

### ✨ FITUR LENGKAP:

#### Menu Petugas:
- ✓ Tambah Buku (via ISBN dari Google Books)
- ✓ Import CSV (koleksi massal)
- ✓ Dashboard (lihat usulan & manage status)

#### Fitur Pencarian:
- ✓ Cari di Google Books
- ✓ Cari di database lokal (buku CSV + ISBN search)
- ✓ Hasil gabungan tanpa duplikat
- ✓ Buku lokal bisa diusulkan & di-vote

---

### 🔍 TESTING CHECKLIST:

- [ ] Buat tabel `bukulokal` di database
- [ ] Login sebagai petugas
- [ ] Cek menu "Tambah Buku" muncul
- [ ] Cek menu "Import CSV" muncul
- [ ] Test Tambah Buku dengan ISBN yang valid
- [ ] Test Import CSV (gunakan template)
- [ ] Test Pencarian - cari buku lokal yang sudah di-import
- [ ] Test Usulan buku lokal
- [ ] Test Vote buku lokal

---

### 📞 TROUBLESHOOTING:

**Q: "File not found" untuk tambah_buku.php atau import_csv.php**
- A: File sudah dibuat. Refresh browser dengan Ctrl+Shift+R

**Q: Import CSV gagal**
- A: Pastikan tabel `bukulokal` sudah dibuat di database

**Q: Buku lokal tidak muncul di pencarian**
- A: Cek apakah buku sudah diimport ke tabel `bukulokal` & `books`

**Q: Menu Tambah Buku tidak muncul**
- A: Pastikan Anda login sebagai **petugas** (role pegawai)

**Q: Google Books API error**
- A: Cek konfigurasi di `config/db.php` - pastikan `GOOGLE_BOOKS_API_KEY` valid

---

Semua file sudah siap! Sekarang tinggal buat tabel `bukulokal` dan sistem sudah bisa digunakan. 🚀
