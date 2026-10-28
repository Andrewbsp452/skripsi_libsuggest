<?php
/**
 * LibSuggest — index.php
 * Halaman utama: cari buku & usulkan ke perpustakaan.
 */
require_once __DIR__ . '/config/session.php';
requireLogin();
$user = currentUser();
// $initials sudah dihitung di dalam currentUser(), tidak perlu hitung lagi di sini
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="<?= htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
  <title>LibSuggest — Usul Buku</title>
  <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
</head>
<body>

<!-- ── Topbar ── -->
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
      <a href="index.php" class="nav-link active">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        Cari Buku
      </a>
      <a href="riwayat.php" class="nav-link">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
        Riwayat Saya
      </a>
      <a href="dashboard.php" class="nav-link">
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
        <?php if (!empty($user['photo'])): ?>
          <img src="<?= htmlspecialchars($user['photo']) ?>" alt="Foto profil <?= htmlspecialchars($user['name']) ?>">
        <?php else: ?>
          <?= htmlspecialchars($user['initials']) ?>
        <?php endif; ?>
      </div>
      <div class="user-info">
        <div class="user-name"><?= htmlspecialchars($user['name']) ?></div>
        <div class="user-role"><?= htmlspecialchars($user['display_role']) ?></div>
      </div>
    </div>
  </div>
</header>

<!-- ── Main ── -->
<main class="container">
  <div class="page-header">
    <h1 class="page-title">Cari & Usulkan Buku</h1>
    <p class="page-sub">Temukan buku yang kamu butuhkan, lalu usulkan ke perpustakaan.</p>
  </div>

  <!-- Search bar -->
  <form id="search-form">
    <div class="search-wrap">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
      </svg>
      <input
        id="search-input"
        class="search-input"
        type="text"
        placeholder="Judul, penulis, ISBN, atau topik..."
        autocomplete="off"
        value="<?= htmlspecialchars($_GET['q'] ?? '') ?>"
      >
      <button type="submit" class="btn-search">Cari</button>
    </div>
  </form>

  <!-- Results -->
  <div class="results-info">
    <span class="results-count" id="results-count"></span>
    <div style="display:flex;gap:8px;align-items:center">
      <button id="btn-prev" class="page-btn" disabled>← Sebelumnya</button>
      <span class="page-info" id="page-info"></span>
      <button id="btn-next" class="page-btn" disabled>Berikutnya →</button>
    </div>
  </div>

  <div class="book-grid" id="book-grid">
    <div class="empty-state" style="grid-column:1/-1">
      <div class="icon">📖</div>
      <p>Ketik judul atau nama penulis untuk mulai mencari buku.</p>
    </div>
  </div>
</main>

<!-- ── Modal Usulkan Buku ── -->
<div id="modal-backdrop" class="modal-backdrop">
  <div class="modal">
    <div class="modal-header">
      <h2 class="modal-title">Usulkan Buku</h2>
      <button id="modal-close" class="modal-close" aria-label="Tutup">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
        </svg>
      </button>
    </div>
    <div class="modal-body">

      <!-- Baris cover + info -->
      <div class="modal-book-row">
        <div id="modal-cover" class="modal-book-cover" title="Klik untuk memperbesar cover">
          📚
        </div>
        <div class="modal-book-info">
          <div class="title" id="modal-title"></div>
          <div class="meta"  id="modal-meta"></div>
          <div id="modal-badge-wrap" style="margin-top:6px;"></div>
        </div>
      </div>

      <!-- Deskripsi buku -->
      <div id="modal-desc-section" style="display:none; margin-bottom:14px;">
        <div class="modal-section-label">Deskripsi</div>
        <div id="modal-desc" class="modal-desc-text"></div>
        <button id="modal-desc-toggle" class="modal-desc-toggle" style="display:none;">Baca selengkapnya ▾</button>
      </div>

      <div class="modal-divider"></div>

      <!-- Detail buku -->
      <div id="modal-detail-section" style="display:none; margin-bottom:14px;">
        <div class="modal-section-label">Detail Buku</div>
        <div class="modal-detail-grid" id="modal-detail-grid"></div>
        <div class="modal-divider" style="margin-top:14px;"></div>
      </div>

      <!-- Form alasan -->
      <label class="form-label" for="modal-alasan">
        Alasan pengajuan <span style="font-weight:400;color:var(--gray-400)">(opsional)</span>
      </label>
      <textarea
        id="modal-alasan"
        class="form-textarea"
        rows="3"
        placeholder="Contoh: Dibutuhkan sebagai referensi mata kuliah Algoritma..."
        maxlength="1000"
      ></textarea>
      <div class="form-hint">Maks. 1000 karakter. Alasan membantu petugas memprioritaskan usulan.</div>

      <div class="modal-actions">
        <button id="modal-cancel" class="btn btn-outline" onclick="document.getElementById('modal-backdrop').classList.remove('open')">Batal</button>
        <button id="modal-submit" class="btn btn-primary">Kirim Usulan</button>
      </div>
    </div>
  </div>
</div>

<!-- ── Modal Usulan Buku Manual ── -->
<div id="manual-modal-backdrop" class="modal-backdrop">
  <section class="modal manual-modal" role="dialog" aria-modal="true" aria-labelledby="manual-modal-title">
    <div class="modal-header">
      <div>
        <h2 class="modal-title" id="manual-modal-title">Usulkan Buku secara Manual</h2>
        <p class="form-hint" id="manual-search-hint">Buku tidak ditemukan di Google Books. Isi data yang kamu ketahui.</p>
      </div>
      <button id="manual-modal-close" class="modal-close" type="button" aria-label="Tutup">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
        </svg>
      </button>
    </div>
    <form id="manual-proposal-form" class="modal-body manual-form">
      <label class="form-label" for="manual-title">Judul buku <span class="required-mark">*</span></label>
      <input id="manual-title" class="form-input" name="title" type="text" maxlength="500" required>

      <label class="form-label" for="manual-authors">Penulis <span class="form-hint">(pisahkan beberapa nama dengan koma)</span></label>
      <input id="manual-authors" class="form-input" name="authors" type="text" maxlength="500">

      <div class="manual-form-row">
        <div>
          <label class="form-label" for="manual-isbn">ISBN</label>
          <input id="manual-isbn" class="form-input" name="isbn" type="text" maxlength="20">
        </div>
        <div>
          <label class="form-label" for="manual-publisher">Penerbit</label>
          <input id="manual-publisher" class="form-input" name="publisher" type="text" maxlength="255">
        </div>
      </div>

      <div class="manual-form-row">
        <div>
          <label class="form-label" for="manual-year">Tahun terbit</label>
          <input id="manual-year" class="form-input" name="published_year" type="number" min="1901" max="2155">
        </div>
        <div>
          <label class="form-label" for="manual-pages">Jumlah halaman</label>
          <input id="manual-pages" class="form-input" name="page_count" type="number" min="1" max="65535">
        </div>
      </div>

      <label class="form-label" for="manual-alasan">Alasan pengajuan <span class="form-hint">(opsional)</span></label>
      <textarea id="manual-alasan" class="form-textarea" name="alasan" rows="3" maxlength="1000"></textarea>
      <div class="form-hint">Judul wajib diisi; detail lainnya boleh dikosongkan jika tidak diketahui.</div>

      <div class="modal-actions">
        <button id="manual-modal-cancel" class="btn btn-outline" type="button">Batal</button>
        <button id="manual-modal-submit" class="btn btn-primary" type="submit">Kirim Usulan</button>
      </div>
    </form>
  </section>
</div>

<!-- ── Lightbox Cover ── -->
<div id="lightbox-backdrop" class="lightbox-backdrop" aria-label="Preview cover buku">
  <div class="lightbox-inner">
    <button id="lightbox-close" class="lightbox-close" aria-label="Tutup preview">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="18" height="18">
        <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
      </svg>
    </button>
    <div id="lightbox-img-wrap" class="lightbox-img-wrap"></div>
    <div id="lightbox-title" class="lightbox-title"></div>
    <div id="lightbox-author" class="lightbox-author"></div>
  </div>
</div>

<script src="assets/js/app.js?v=<?= time() ?>"></script>
</body>
</html>