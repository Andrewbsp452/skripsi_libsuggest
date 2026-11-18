<?php
/**
 * LibSuggest — dashboard.php
 * Halaman polling usulan buku.
 * - Semua user login bisa LIHAT
 * - Hanya pegawai yang bisa UBAH STATUS
 */
require_once __DIR__ . '/config/session.php';
requireDashboardAccess(); // semua user login boleh masuk
$user     = currentUser();
$isPetugas = isPetugas(); // true = pegawai, false = mahasiswa
$initials = strtoupper(implode('', array_map(fn($w) => $w[0], explode(' ', $user['name']))));
$initials = mb_substr($initials, 0, 2);
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="<?= htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
  <title>LibSuggest — Dashboard Petugas</title>
  <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
</head>
<body>

<header class="topbar">
  <div class="container topbar-inner">
    <!-- Logo Dropdown -->
    <div class="topbar-brand-dropdown">
      <button class="topbar-brand" id="brand-menu-toggle" aria-label="Menu">
        <div class="brand-icon">
          <svg viewBox="0 0 24 24"><path d="M4 4h4v16H4zM10 4h4v16h-4zM16 4h4v16h-4z"/></svg>
        </div>
        <div>
          <div class="brand-name">LibSuggest</div>
          <div class="brand-sub">Perpustakaan Unika</div>
        </div>
      </button>
      
      <!-- Dropdown Menu -->
      <div class="brand-dropdown" id="brand-dropdown">
        <a href="dashboard.php" class="dropdown-item">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
          Dashboard
        </a>
        <a href="logout.php" class="dropdown-item logout">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
          Logout
        </a>
      </div>
    </div>
    <nav class="topbar-nav">
      <a href="index.php" class="nav-link">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        Cari Buku
      </a>
      <a href="riwayat.php" class="nav-link">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
        Riwayat Saya
      </a>
      <a href="dashboard.php" class="nav-link active">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
        Dashboard
      </a>
      <?php if ($user['role'] === 'pegawai'): ?>
      <!-- <a href="tambah_buku.php" class="nav-link">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Tambah Buku
      </a> -->
      <a href="import_csv.php" class="nav-link">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><polyline points="13 2 13 9 20 9"/></svg>
        Import CSV
      </a>
      <?php endif; ?>
    </nav>
    <div class="topbar-user">
      <div class="user-avatar">
        <?php if ($user['photo']): ?>
          <img src="<?= htmlspecialchars($user['photo']) ?>" alt="">
        <?php else: ?>
          <?= htmlspecialchars($initials) ?>
        <?php endif; ?>
      </div>
      <div class="user-info">
        <div class="user-name"><?= htmlspecialchars($user['name']) ?></div>
        <div class="user-role"><?= isLocalhostRequest() ? htmlspecialchars($user['display_role']) : ($isPetugas ? 'Petugas' : 'Mahasiswa/Dosen') ?></div>
      </div>
    </div>
  </div>
</header>

<main class="container">
  <div class="page-header">
    <h1 class="page-title">Dashboard Usulan Buku</h1>
    <p class="page-sub">
      <?php if ($isPetugas): ?>
        Kelola dan ubah status usulan buku dari mahasiswa dan dosen.
      <?php else: ?>
        Pantau semua usulan buku yang masuk, diurutkan dari dukungan terbanyak.
      <?php endif; ?>
    </p>
  </div>

  <div class="dashboard-layout">
    <aside class="dashboard-catalog">
      <div class="catalog-heading">
        <div>
          <h2 class="table-title">Katalog</h2>
          <p class="form-hint">Buku dibeli dan ditolak dalam semester ini.</p>
        </div>
        <span class="catalog-count" id="catalog-count">0</span>
      </div>
      <div class="catalog-filters" role="group" aria-label="Filter katalog">
        <button type="button" class="catalog-filter active" data-catalog-status="all">Semua</button>
        <button type="button" class="catalog-filter" data-catalog-status="dibeli">Dibeli</button>
        <button type="button" class="catalog-filter" data-catalog-status="ditolak">Ditolak</button>
      </div>
      <div id="dashboard-catalog-list" class="dashboard-catalog-list" aria-live="polite">
        <p class="chat-empty">Memuat katalog...</p>
      </div>
      <p class="catalog-footnote">Status final diarsipkan dari dashboard setelah enam bulan. Buku tetap ada di pencarian.</p>
    </aside>

    <section class="dashboard-main">
      <!-- Metrics -->
      <div class="metrics-row">
        <div class="metric-card">
          <div class="metric-label">Total buku diusulkan</div>
          <div class="metric-value" id="m-total">—</div>
        </div>
        <div class="metric-card">
          <div class="metric-label">Menunggu review</div>
          <div class="metric-value" id="m-pending">—</div>
        </div>
        <div class="metric-card primary">
          <div class="metric-label">Disetujui</div>
          <div class="metric-value" id="m-disetujui">—</div>
        </div>
        <div class="metric-card accent">
          <div class="metric-label">Total pengusul</div>
          <div class="metric-value" id="m-user">—</div>
        </div>
      </div>

      <!-- Tabel usulan -->
      <div class="table-wrap">
        <div class="table-toolbar">
          <span class="table-title">Polling usulan buku — diurutkan dukungan terbanyak</span>
          <div class="toolbar-filters">
            <select id="filter-status" class="filter-select">
              <option value="all">Semua status aktif</option>
              <option value="pending">Menunggu</option>
              <option value="disetujui">Disetujui</option>
            </select>
            <select id="filter-sort" class="filter-select">
              <option value="dukungan">Terbanyak dukungan</option>
              <option value="terbaru">Terbaru</option>
            </select>
          </div>
        </div>

        <!-- Keterangan status -->
        <div style="margin-top:12px;margin-bottom:16px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;justify-content:center">
          <span style="font-size:12px;color:var(--gray-400)">Keterangan status:</span>
          <span class="badge badge-pending">Menunggu</span>
          <span class="badge badge-disetujui">Disetujui</span>
          <span class="badge badge-dibeli">Dibeli</span>
          <span class="badge badge-tersedia">Tersedia</span>
          <span class="badge badge-ditolak">Ditolak</span>
        </div>

        <table>
          <thead>
            <tr>
              <th style="width:40px">#</th>
              <th>Buku</th>
              <th>Pengusul</th>
              <th style="width:90px">Dukungan</th>
              <th style="width:110px">Status</th>
              <?php if ($isPetugas): ?>
              <th style="width:160px">Aksi</th>
              <?php endif; ?>
            </tr>
          </thead>
          <tbody id="dashboard-tbody">
            <tr>
              <td colspan="<?= $isPetugas ? 6 : 5 ?>" style="text-align:center;padding:2rem;color:var(--gray-400)">
                Memuat data...
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <?php if ($isPetugas): ?>
      <section class="chat-inbox-section">
        <div class="chat-inbox-header">
          <div>
            <h2 class="table-title">Percakapan keberatan</h2>
            <p class="form-hint">Balas pesan pengusul untuk buku yang ditolak.</p>
          </div>
          <button type="button" class="btn btn-outline" id="chat-inbox-refresh">Muat ulang</button>
        </div>
        <div id="admin-chat-inbox" class="chat-inbox-list">
          <p class="form-hint">Memuat percakapan...</p>
        </div>
      </section>
      <?php else: ?>
      <section class="chat-inbox-section">
        <div class="chat-inbox-header">
          <div>
            <h2 class="table-title">Usulan Ditolak</h2>
            <p class="form-hint">Mulai atau lanjutkan percakapan dengan admin untuk menjelaskan kebutuhan bukumu.</p>
          </div>
          <button type="button" class="btn btn-outline" id="requester-chat-refresh">Muat ulang</button>
        </div>
        <div id="requester-chat-list" class="chat-inbox-list">
          <p class="form-hint">Memuat usulan ditolak...</p>
        </div>
      </section>
      <?php endif; ?>
    </section>
  </div>
</main>

<?php if ($isPetugas): ?>
<div id="rejection-modal-backdrop" class="modal-backdrop">
  <section class="modal" role="dialog" aria-modal="true" aria-labelledby="rejection-modal-title">
    <div class="modal-header">
      <h2 class="modal-title" id="rejection-modal-title">Tolak Usulan Buku</h2>
      <button id="rejection-modal-close" class="modal-close" type="button" aria-label="Tutup">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
        </svg>
      </button>
    </div>
    <form id="rejection-form" class="modal-body">
      <label class="form-label" for="rejection-category">Alasan penolakan <span class="required-mark">*</span></label>
      <select id="rejection-category" class="form-input" required>
        <option value="">Pilih alasan</option>
      </select>
      <label class="form-label" for="rejection-note">Penjelasan tambahan <span id="rejection-note-required" class="form-hint">(opsional)</span></label>
      <textarea id="rejection-note" class="form-textarea" rows="3" maxlength="500"></textarea>
      <div class="form-hint">Kategori alasan akan ditampilkan kepada pengusul di halaman Riwayat Saya.</div>
      <div class="modal-actions">
        <button id="rejection-modal-cancel" class="btn btn-outline" type="button">Batal</button>
        <button id="rejection-modal-submit" class="btn btn-primary" type="submit">Konfirmasi Penolakan</button>
      </div>
    </form>
  </section>
</div>
<?php endif; ?>

<div id="chat-modal-backdrop" class="modal-backdrop">
  <section class="modal chat-modal" role="dialog" aria-modal="true" aria-labelledby="chat-modal-title">
    <div class="modal-header">
      <div>
        <h2 class="modal-title" id="chat-modal-title">Percakapan Usulan</h2>
        <p class="form-hint" id="chat-modal-subtitle"></p>
      </div>
      <button id="chat-modal-close" class="modal-close" type="button" aria-label="Tutup">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
        </svg>
      </button>
    </div>
    <div id="chat-messages" class="chat-messages" aria-live="polite"></div>
    <form id="chat-message-form" class="chat-compose">
      <textarea id="chat-message-input" class="form-textarea" rows="2" maxlength="2000" placeholder="Tulis pesan..." required></textarea>
      <button id="chat-send-button" type="submit" class="btn btn-primary">Kirim</button>
    </form>
  </section>
</div>

<!-- Kirim info role ke JS sebelum app.js dimuat -->
<script>
  window.LIBSUGGEST_IS_PETUGAS = <?= $isPetugas ? 'true' : 'false' ?>;
  window.LIBSUGGEST_CHAT_ROLE = <?= $isPetugas ? "'admin'" : "'pengusul'" ?>;
</script>
<script src="assets/js/app.js?v=<?= time() ?>"></script>
<script src="assets/js/chat.js?v=<?= time() ?>"></script>
</body>
</html>