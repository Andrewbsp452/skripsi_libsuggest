/**
 * LibSuggest — assets/js/app.js
 * Dipakai oleh index.php, riwayat.php, dashboard.php
 */

'use strict';

/* ══════════════════════════════════════════════
   BRAND DROPDOWN MENU
══════════════════════════════════════════════ */
const BrandMenu = (() => {
  const toggle = document.getElementById('brand-menu-toggle');
  const dropdown = document.getElementById('brand-dropdown');
  
  if (!toggle || !dropdown) return { open: () => {}, close: () => {} };

  function close() {
    dropdown.classList.remove('open');
  }

  function open() {
    dropdown.classList.add('open');
  }

  function toggleMenu() {
    if (dropdown.classList.contains('open')) {
      close();
    } else {
      open();
    }
  }

  // Click toggle button
  toggle.addEventListener('click', toggleMenu);

  // Close dropdown when clicking outside
  document.addEventListener('click', (e) => {
    if (!toggle.contains(e.target) && !dropdown.contains(e.target)) {
      close();
    }
  });

  // Close dropdown when clicking on a menu item
  dropdown.querySelectorAll('.dropdown-item').forEach(item => {
    item.addEventListener('click', close);
  });

  // Close dropdown on Escape key
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') close();
  });

  return { open, close, toggleMenu };
})();

/* ══════════════════════════════════════════════
   TOAST
══════════════════════════════════════════════ */
const Toast = (() => {
  let container = null;

  function getContainer() {
    if (!container) {
      container = document.createElement('div');
      container.className = 'toast-container';
      document.body.appendChild(container);
    }
    return container;
  }

  function show(msg, type = 'info', duration = 3000) {
    const icons = {
      success: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>`,
      error:   `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>`,
      info:    `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>`,
    };
    const t = document.createElement('div');
    t.className = `toast ${type}`;
    t.innerHTML = (icons[type] || icons.info) + `<span>${msg}</span>`;
    getContainer().appendChild(t);
    requestAnimationFrame(() => { requestAnimationFrame(() => { t.classList.add('show'); }); });
    setTimeout(() => {
      t.classList.remove('show');
      setTimeout(() => t.remove(), 400);
    }, duration);
  }

  return { success: m => show(m, 'success'), error: m => show(m, 'error'), info: m => show(m, 'info') };
})();


/* ══════════════════════════════════════════════
   API HELPER
══════════════════════════════════════════════ */
async function apiGet(url) {
  const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
  const data = await res.json();
  if (data.error) throw new Error(data.error);
  return data;
}

async function apiPost(url, body) {
  const res = await fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    body: JSON.stringify(body),
  });
  const data = await res.json();
  if (data.error) throw new Error(data.error);
  return data;
}


/* ══════════════════════════════════════════════
   LIGHTBOX (preview cover besar)
══════════════════════════════════════════════ */
const Lightbox = (() => {
  const backdrop  = document.getElementById('lightbox-backdrop');
  const imgWrap   = document.getElementById('lightbox-img-wrap');
  const titleEl   = document.getElementById('lightbox-title');
  const authorEl  = document.getElementById('lightbox-author');
  const btnClose  = document.getElementById('lightbox-close');

  if (!backdrop) return { open: () => {} };

  function close() {
    backdrop.classList.remove('open');
  }

  btnClose?.addEventListener('click', close);
  backdrop.addEventListener('click', e => { if (e.target === backdrop) close(); });
  document.addEventListener('keydown', e => { if (e.key === 'Escape') close(); });

  function open(book) {
    // Isi gambar
    if (book.cover_url) {
      imgWrap.innerHTML = `<img src="${book.cover_url}" alt="${escHtml(book.title)}">`;
    } else {
      imgWrap.innerHTML = '📚';
      imgWrap.style.fontSize = '60px';
    }

    // Isi teks
    if (titleEl)  titleEl.textContent  = book.title || '';
    if (authorEl) {
      const authors = Array.isArray(book.authors) ? book.authors.join(', ') : (book.authors || '');
      authorEl.textContent = [authors, book.publisher, book.published_year].filter(Boolean).join(' · ');
    }

    backdrop.classList.add('open');
  }

  return { open, close };
})();


/* ══════════════════════════════════════════════
   MODAL (usul buku)
══════════════════════════════════════════════ */
const Modal = (() => {
  let currentBook  = null;
  let onSuccess    = null;

  const backdrop   = document.getElementById('modal-backdrop');
  const formAlasan = document.getElementById('modal-alasan');
  const btnSubmit  = document.getElementById('modal-submit');

  if (!backdrop) return { open: () => {}, close: () => {} };

  function close() {
    backdrop.classList.remove('open');
    setTimeout(() => { if (currentBook) currentBook = null; }, 220);
  }

  backdrop.addEventListener('click', e => { if (e.target === backdrop) close(); });
  document.getElementById('modal-close')?.addEventListener('click', close);
  document.addEventListener('keydown', e => { if (e.key === 'Escape') close(); });

  // Submit
  btnSubmit?.addEventListener('click', async () => {
    if (!currentBook) return;
    btnSubmit.disabled = true;
    btnSubmit.textContent = 'Mengirim...';

    try {
      const payload = {
        google_id:      currentBook.google_id,
        title:          currentBook.title,
        authors:        currentBook.authors || [],
        publisher:      currentBook.publisher,
        published_year: currentBook.published_year,
        cover_url:      currentBook.cover_url,
        isbn:           currentBook.isbn,
        page_count:     currentBook.page_count,
        language:       currentBook.language,
        alasan:         formAlasan?.value.trim() || '',
      };

      const result = await apiPost('api/propose.php', payload);

      if (result.already_proposed) {
        Toast.info('Kamu sudah mengusulkan buku ini sebelumnya.');
      } else {
        Toast.success('Usulan berhasil dikirim!');
        if (typeof onSuccess === 'function') onSuccess(currentBook, result);
      }
      close();
    } catch (err) {
      Toast.error(err.message || 'Gagal mengirim usulan');
    } finally {
      btnSubmit.disabled = false;
      btnSubmit.textContent = 'Kirim Usulan';
    }
  });

  /* ── Render detail buku (seperti Gramedia) ── */
  function renderDetailGrid(book) {
    const section = document.getElementById('modal-detail-section');
    const grid    = document.getElementById('modal-detail-grid');
    if (!section || !grid) return;

    const items = [
      { label: 'Penerbit',       value: book.publisher      || '-' },
      { label: 'Tanggal Terbit', value: book.published_year || '-' },
      { label: 'ISBN',           value: book.isbn           || '-' },
      { label: 'Halaman',        value: book.page_count ? `${book.page_count} hlm` : '-' },
      { label: 'Bahasa',         value: langLabel(book.language) },
      { label: 'Kategori',       value: book.categories     || '-' },
    ];

    // Hapus item yang tidak ada nilainya agar tidak berantakan
    const filtered = items.filter(i => i.value !== '-');
    if (!filtered.length) { section.style.display = 'none'; return; }

    // Kalau ganjil, tambah satu slot kosong supaya grid rapi
    const rows = filtered.length % 2 !== 0
      ? [...filtered, { label: '', value: '' }]
      : filtered;

    grid.innerHTML = rows.map(({ label, value }) => `
      <div class="modal-detail-item">
        <div class="modal-detail-label">${escHtml(label)}</div>
        <div class="modal-detail-value">${escHtml(value)}</div>
      </div>
    `).join('');

    section.style.display = '';
  }

  /* ── Render deskripsi ── */
  function renderDescription(book) {
    const section    = document.getElementById('modal-desc-section');
    const descEl     = document.getElementById('modal-desc');
    const toggleBtn  = document.getElementById('modal-desc-toggle');
    if (!section || !descEl) return;

    const desc = book.description || '';
    if (!desc) { section.style.display = 'none'; return; }

    const MAX_CHARS = 180;
    const isLong    = desc.length > MAX_CHARS;

    descEl.textContent = isLong ? desc.slice(0, MAX_CHARS) + '...' : desc;
    descEl.className   = 'modal-desc-text';

    if (isLong && toggleBtn) {
      let expanded = false;
      toggleBtn.style.display = 'block';
      toggleBtn.textContent   = 'Baca selengkapnya ▾';
      toggleBtn.onclick = () => {
        expanded = !expanded;
        descEl.textContent   = expanded ? desc : desc.slice(0, MAX_CHARS) + '...';
        toggleBtn.textContent = expanded ? 'Sembunyikan ▴' : 'Baca selengkapnya ▾';
      };
    } else if (toggleBtn) {
      toggleBtn.style.display = 'none';
    }

    section.style.display = '';
  }

  /* ── Open modal ── */
  function open(book, successCallback) {
    currentBook = book;
    onSuccess   = successCallback;
    if (formAlasan) formAlasan.value = '';

    const coverEl   = document.getElementById('modal-cover');
    const titleEl   = document.getElementById('modal-title');
    const metaEl    = document.getElementById('modal-meta');

    const authors = Array.isArray(book.authors) ? book.authors.join(', ') : (book.authors || '');

    // Cover thumbnail + overlay zoom
    if (coverEl) {
      const zoomOverlay = `
        <div class="cover-zoom-overlay">
          <svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
            <circle cx="11" cy="11" r="7"/>
            <line x1="21" y1="21" x2="15.65" y2="15.65"/>
            <line x1="11" y1="8" x2="11" y2="14"/>
            <line x1="8" y1="11" x2="14" y2="11"/>
          </svg>
        </div>`;

      if (book.cover_url) {
        coverEl.innerHTML = `<img src="${book.cover_url}" alt="">${zoomOverlay}`;
      } else {
        coverEl.innerHTML = `📚${zoomOverlay}`;
      }

      // Klik cover → buka lightbox
      coverEl.onclick = () => Lightbox.open(book);
    }

    if (titleEl) titleEl.textContent = book.title;
    if (metaEl)  metaEl.textContent  = [authors, book.publisher, book.published_year].filter(Boolean).join(' · ');

    // Render deskripsi & detail
    renderDescription(book);
    renderDetailGrid(book);

    // Show form section (in case it was hidden by openInfo)
    const dividers = backdrop.querySelectorAll('.modal-divider');
    const formLabel = backdrop.querySelector('.form-label');
    const formTextarea = document.getElementById('modal-alasan');
    const formHint = backdrop.querySelector('.form-hint');
    const modalActions = backdrop.querySelector('.modal-actions');

    if (dividers.length > 0) {
      dividers[dividers.length - 1].style.display = '';
    }
    if (formLabel) formLabel.style.display = '';
    if (formTextarea) formTextarea.style.display = '';
    if (formHint) formHint.style.display = '';
    if (modalActions) modalActions.style.display = '';

    backdrop.classList.add('open');
    formAlasan?.focus();
  }

  /* ── Open modal (info only, no form) ── */
  function openInfo(book) {
    currentBook = book;
    onSuccess   = null;
    if (formAlasan) formAlasan.value = '';

    const coverEl   = document.getElementById('modal-cover');
    const titleEl   = document.getElementById('modal-title');
    const metaEl    = document.getElementById('modal-meta');

    const authors = Array.isArray(book.authors) ? book.authors.join(', ') : (book.authors || '');

    // Cover thumbnail + overlay zoom
    if (coverEl) {
      const zoomOverlay = `
        <div class="cover-zoom-overlay">
          <svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
            <circle cx="11" cy="11" r="7"/>
            <line x1="21" y1="21" x2="15.65" y2="15.65"/>
            <line x1="11" y1="8" x2="11" y2="14"/>
            <line x1="8" y1="11" x2="14" y2="11"/>
          </svg>
        </div>`;

      if (book.cover_url) {
        coverEl.innerHTML = `<img src="${book.cover_url}" alt="">${zoomOverlay}`;
      } else {
        coverEl.innerHTML = `📚${zoomOverlay}`;
      }

      // Klik cover → buka lightbox
      coverEl.onclick = () => Lightbox.open(book);
    }

    if (titleEl) titleEl.textContent = book.title;
    if (metaEl)  metaEl.textContent  = [authors, book.publisher, book.published_year].filter(Boolean).join(' · ');

    // Render deskripsi & detail
    renderDescription(book);
    renderDetailGrid(book);

    // Hide form section - get all dividers and hide elements more carefully
    const dividers = backdrop.querySelectorAll('.modal-divider');
    const formLabel = backdrop.querySelector('.form-label');
    const formTextarea = document.getElementById('modal-alasan');
    const formHint = backdrop.querySelector('.form-hint');
    const modalActions = backdrop.querySelector('.modal-actions');

    // Hide the last divider (between detail and form)
    if (dividers.length > 0) {
      dividers[dividers.length - 1].style.display = 'none';
    }
    if (formLabel) formLabel.style.display = 'none';
    if (formTextarea) formTextarea.style.display = 'none';
    if (formHint) formHint.style.display = 'none';
    if (modalActions) modalActions.style.display = 'none';

    backdrop.classList.add('open');
  }

  return { open, close, openInfo };
})();

/* ══════════════════════════════════════════════
   MANUAL PROPOSAL FORM
══════════════════════════════════════════════ */
const ManualProposal = (() => {
  const backdrop = document.getElementById('manual-modal-backdrop');
  const form = document.getElementById('manual-proposal-form');
  const submitButton = document.getElementById('manual-modal-submit');

  if (!backdrop || !form || !submitButton) return { open: () => {} };

  let onSuccess = null;
  let currentSearchTerm = '';

  function close() {
    backdrop.classList.remove('open');
  }

  document.getElementById('manual-modal-close')?.addEventListener('click', close);
  document.getElementById('manual-modal-cancel')?.addEventListener('click', close);
  backdrop.addEventListener('click', event => {
    if (event.target === backdrop) close();
  });
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape') close();
  });

  function open(searchTerm, successCallback) {
    form.reset();
    currentSearchTerm = searchTerm;
    document.getElementById('manual-title').value = searchTerm;
    onSuccess = successCallback;
    backdrop.classList.add('open');
    document.getElementById('manual-title').focus();
  }

  form.addEventListener('submit', async event => {
    event.preventDefault();
    if (!form.reportValidity()) return;

    submitButton.disabled = true;
    submitButton.textContent = 'Mengirim...';

    try {
      const formData = new FormData(form);
      const result = await apiPost('api/propose_manual.php', {
        title: formData.get('title'),
        authors: formData.get('authors'),
        isbn: formData.get('isbn'),
        publisher: formData.get('publisher'),
        published_year: formData.get('published_year'),
        page_count: formData.get('page_count'),
        alasan: formData.get('alasan'),
        kata_kunci: currentSearchTerm,
      });

      if (result.already_proposed) {
        Toast.info('Kamu sudah mengusulkan buku ini sebelumnya.');
      } else {
        Toast.success('Usulan buku manual berhasil dikirim!');
        if (typeof onSuccess === 'function') onSuccess();
      }
      close();
    } catch (error) {
      Toast.error(error.message || 'Gagal mengirim usulan');
    } finally {
      submitButton.disabled = false;
      submitButton.textContent = 'Kirim Usulan';
    }
  });

  return { open, close };
})();


/* ══════════════════════════════════════════════
   BOOK CARD RENDERER
══════════════════════════════════════════════ */
function renderBookCard(book) {
  const authors = Array.isArray(book.authors) ? book.authors.join(', ') : (book.authors || '');
  const dukungan = book.total_dukungan || 0;
  const isProposed = book.already_proposed || book.already_voted;

  const openBookInfo = () => Modal.openInfo(book);

  const coverHtml = book.cover_url
    ? `<img src="${book.cover_url}" alt="${escHtml(book.title)}" loading="lazy">`
    : `<div class="book-cover-placeholder">📚</div>`;

  const badgeClass = dukungan > 0 ? 'has-votes' : '';

  let btnHtml;
  if (book.already_proposed) {
    btnHtml = `
      <button class="btn btn-outline btn-info">ℹ Info Buku</button>
      <button class="btn btn-success" disabled>✓ Sudah Diusulkan</button>
    `;
  } else if (book.already_voted) {
    btnHtml = `
      <button class="btn btn-outline btn-info">ℹ Info Buku</button>
      <button class="btn btn-success btn-unvote" data-book-id="${book.book_id}">✓ Didukung</button>
    `;
  } else if (book.book_id) {
    btnHtml = `
      <button class="btn btn-outline btn-info">ℹ Info Buku</button>
      <button class="btn btn-outline btn-vote" data-book-id="${book.book_id}">+ Dukung</button>
    `;
  } else {
    btnHtml = `
      <button class="btn btn-outline btn-info">ℹ Info Buku</button>
      <button class="btn btn-primary btn-propose">Usulkan</button>
    `;
  }

  const card = document.createElement('div');
  card.className = `book-card${isProposed ? ' is-proposed' : ''}`;
  card.dataset.googleId = book.google_id;
  card.tabIndex = 0;
  card.setAttribute('role', 'button');
  card.setAttribute('aria-label', `Lihat info buku ${book.title}`);
  card.innerHTML = `
    <div class="book-cover-wrap">
      ${coverHtml}
      ${dukungan > 0 ? `<span class="badge-dukungan ${badgeClass}">${dukungan} dukungan</span>` : ''}
    </div>
    <div class="book-body">
      <div class="book-title">${escHtml(book.title)}</div>
      <div class="book-author">${escHtml(authors)}</div>
      <div class="book-publisher">${escHtml([book.publisher, book.published_year].filter(Boolean).join(' · '))}</div>
      <div class="book-footer">${btnHtml}</div>
    </div>
  `;

  // Event: Usulkan
  card.querySelector('.btn-propose')?.addEventListener('click', () => {
    Modal.open(book, (b, result) => {
      card.classList.add('is-proposed');
      const footer = card.querySelector('.book-footer');
      footer.innerHTML = `
        <button class="btn btn-outline btn-info">ℹ Info Buku</button>
        <button class="btn btn-success" disabled>✓ Sudah Diusulkan</button>
      `;
      // Re-attach info button listener
      footer.querySelector('.btn-info')?.addEventListener('click', e => {
        e.stopPropagation();
        openBookInfo();
      });
      
      const badge = card.querySelector('.badge-dukungan');
      if (badge) {
        badge.textContent = `${(dukungan + 1)} dukungan`;
        badge.classList.add('has-votes');
      } else {
        card.querySelector('.book-cover-wrap').insertAdjacentHTML(
          'beforeend',
          `<span class="badge-dukungan has-votes">1 dukungan</span>`
        );
      }
      book.book_id = result.book_id;
      book.total_dukungan = (book.total_dukungan || 0) + 1;
      book.already_proposed = true;
    });
  });

  // Event: Info Buku (untuk semua tombol info di card)
  const infoButtons = card.querySelectorAll('.btn-info');
  infoButtons.forEach(btn => {
    btn.addEventListener('click', e => {
      e.stopPropagation();
      openBookInfo();
    });
  });

  card.addEventListener('click', e => {
    if (e.target.closest('button, a, input, textarea, select, label')) return;
    openBookInfo();
  });

  card.addEventListener('keydown', e => {
    if (e.key !== 'Enter' && e.key !== ' ') return;
    if (e.target.closest('button, a, input, textarea, select, label')) return;
    e.preventDefault();
    openBookInfo();
  });

  // Event: Vote / Unvote
  const btnVote   = card.querySelector('.btn-vote');
  const btnUnvote = card.querySelector('.btn-unvote');

  async function doVote(isUnvote) {
    const btn = isUnvote ? btnUnvote : btnVote;
    btn.disabled = true;
    try {
      const res = await apiPost('api/vote.php', { book_id: book.book_id });
      const newDukungan = res.total_dukungan;

      if (res.voted) {
        btn.className = 'btn btn-success btn-unvote';
        btn.textContent = '✓ Didukung';
        btn.removeEventListener('click', voteHandler);
        btn.addEventListener('click', () => doVote(true));
        Toast.success('Dukungan berhasil ditambahkan!');
      } else {
        btn.className = 'btn btn-outline btn-vote';
        btn.textContent = '+ Dukung';
        btn.removeEventListener('click', unvoteHandler);
        btn.addEventListener('click', () => doVote(false));
        Toast.info('Dukungan dibatalkan.');
      }

      const existingBadge = card.querySelector('.badge-dukungan');
      if (newDukungan > 0) {
        if (existingBadge) {
          existingBadge.textContent = `${newDukungan} dukungan`;
          existingBadge.classList.toggle('has-votes', newDukungan > 0);
        } else {
          card.querySelector('.book-cover-wrap').insertAdjacentHTML(
            'beforeend',
            `<span class="badge-dukungan has-votes">${newDukungan} dukungan</span>`
          );
        }
      } else if (existingBadge) {
        existingBadge.remove();
      }
      book.total_dukungan = newDukungan;
    } catch (err) {
      Toast.error(err.message || 'Gagal memproses dukungan');
    } finally {
      btn.disabled = false;
    }
  }

  function voteHandler()   { doVote(false); }
  function unvoteHandler() { doVote(true);  }
  btnVote?.addEventListener('click',   voteHandler);
  btnUnvote?.addEventListener('click', unvoteHandler);

  return card;
}


/* ══════════════════════════════════════════════
   HALAMAN: SEARCH (index.php)
══════════════════════════════════════════════ */
function initSearchPage() {
  const form      = document.getElementById('search-form');
  const input     = document.getElementById('search-input');
  const grid      = document.getElementById('book-grid');
  const countInfo = document.getElementById('results-count');
  const prevBtn   = document.getElementById('btn-prev');
  const nextBtn   = document.getElementById('btn-next');
  const pageInfo  = document.getElementById('page-info');

  if (!form) return;

  let currentPage  = 0;
  let currentQuery = '';
  let totalResults = 0;
  const perPage    = 12;

  async function doSearch(page = 0) {
    const q = input.value.trim();
    if (!q) { Toast.info('Masukkan kata kunci terlebih dahulu'); return; }

    currentQuery = q;
    currentPage  = page;

    grid.innerHTML = renderSkeletons(6);
    countInfo.textContent = 'Mencari...';

    try {
      const data = await apiGet(`api/search.php?q=${encodeURIComponent(q)}&page=${page}`);
      totalResults = data.total || 0;

      grid.innerHTML = '';
      if (!data.books.length) {
        grid.innerHTML = `
          <div class="empty-state" style="grid-column:1/-1">
            <div class="icon">🔍</div>
            <p>Buku tidak ditemukan di Google Books maupun koleksi LibSuggest.</p>
            <button type="button" class="btn btn-primary" id="btn-manual-proposal">Usulkan buku secara manual</button>
          </div>`;
        document.getElementById('btn-manual-proposal')?.addEventListener('click', () => {
          ManualProposal.open(currentQuery, () => doSearch(0));
        });
        countInfo.textContent = 'Tidak ada hasil';
      } else {
        data.books.forEach(b => grid.appendChild(renderBookCard(b)));
        const start = page * perPage + 1;
        const end   = Math.min(start + data.books.length - 1, totalResults);
        countInfo.innerHTML = `Menampilkan <strong>${start}–${end}</strong> dari <strong>${totalResults.toLocaleString()}</strong> buku`;
      }

      if (prevBtn && nextBtn && pageInfo) {
        prevBtn.disabled = page === 0;
        nextBtn.disabled = (page + 1) * perPage >= totalResults;
        pageInfo.textContent = `Halaman ${page + 1}`;
      }
    } catch (err) {
      Toast.error(err.message || 'Pencarian gagal');
      grid.innerHTML = `<div class="empty-state" style="grid-column:1/-1"><div class="icon">⚠️</div><p>${err.message}</p></div>`;
    }
  }

  form.addEventListener('submit', e => { e.preventDefault(); doSearch(0); });
  prevBtn?.addEventListener('click', () => doSearch(currentPage - 1));
  nextBtn?.addEventListener('click', () => doSearch(currentPage + 1));

  const urlQ = new URLSearchParams(location.search).get('q');
  if (urlQ) { input.value = urlQ; doSearch(0); }
}


/* ══════════════════════════════════════════════
   HALAMAN: RIWAYAT (riwayat.php)
══════════════════════════════════════════════ */
function initRiwayatPage() {
  const wrap = document.getElementById('riwayat-wrap');
  if (!wrap) return;

  async function load() {
    wrap.innerHTML = '<p style="color:var(--gray-400);font-size:13px;">Memuat...</p>';
    try {
      const data = await apiGet('api/riwayat.php');
      renderRiwayat(data.proposals, data.votes);
    } catch (err) {
      wrap.innerHTML = `<p style="color:var(--danger)">${err.message}</p>`;
    }
  }

  function renderRiwayat(proposals, votes) {
    const tabProposal  = document.getElementById('tab-proposal');
    const tabVote      = document.getElementById('tab-vote');
    const paneProposal = document.getElementById('pane-proposal');
    const paneVote     = document.getElementById('pane-vote');

    wrap.innerHTML = '';

    function showPane(active) {
      [tabProposal, tabVote].forEach(t => t.classList.remove('active'));
      [paneProposal, paneVote].forEach(p => p.style.display = 'none');
      if (active === 'proposal') { tabProposal?.classList.add('active'); paneProposal.style.display = ''; }
      else                        { tabVote?.classList.add('active');     paneVote.style.display = ''; }
    }

    tabProposal?.addEventListener('click', () => showPane('proposal'));
    tabVote?.addEventListener('click',     () => showPane('vote'));

    paneProposal.innerHTML = proposals.length ? proposals.map(p => riwayatCardHtml(p, 'proposal')).join('') :
      `<div class="empty-state"><div class="icon">📭</div><p>Belum ada usulan buku.</p></div>`;

    paneVote.innerHTML = votes.length ? votes.map(v => riwayatCardHtml(v, 'vote')).join('') :
      `<div class="empty-state"><div class="icon">👍</div><p>Belum ada buku yang didukung.</p></div>`;

    showPane('proposal');
  }

  function riwayatCardHtml(item, type) {
    const authors  = Array.isArray(item.authors) ? item.authors.join(', ') : '';
    const date     = new Date(type === 'vote' ? item.voted_at : item.created_at);
    const dateStr  = date.toLocaleDateString('id-ID', { day:'numeric', month:'short', year:'numeric' });
    const coverHtml = item.cover_url
      ? `<img class="riwayat-cover" src="${item.cover_url}" alt="">`
      : `<div class="riwayat-cover">📚</div>`;

    const rightHtml = type === 'proposal'
      ? `<span class="badge badge-${item.status}">${labelStatus(item.status)}</span>`
      : `<span class="badge badge-disetujui" style="background:#E1F5EE;color:#0F6E56">Didukung</span>`;
    const rejectionHtml = type === 'proposal' && item.status === 'ditolak' && item.kategori_verifikasi
      ? `<div class="rejection-reason"><strong>Alasan penolakan:</strong> ${escHtml(item.kategori_verifikasi)}${item.note_petugas ? `<br>${escHtml(item.note_petugas)}` : ''}</div>`
      : '';
    const chatButton = type === 'proposal' && item.status === 'ditolak'
      ? `<button type="button" class="btn btn-outline btn-chat-open" data-proposal-id="${Number(item.proposal_id)}">${Number(item.chat_unread) > 0 ? `Balasan baru (${Number(item.chat_unread)})` : 'Diskusikan dengan admin'}</button>`
      : '';

    return `
      <div class="riwayat-card">
        ${coverHtml}
        <div class="riwayat-info">
          <div class="riwayat-title">${escHtml(item.title)}</div>
          <div class="riwayat-meta">${escHtml([authors, item.publisher].filter(Boolean).join(' · '))}</div>
          ${item.alasan ? `<div style="font-size:11px;color:var(--gray-400);margin-top:3px;font-style:italic">"${escHtml(item.alasan)}"</div>` : ''}
          ${rejectionHtml}
        </div>
        <div class="riwayat-right">
          ${rightHtml}
          <span class="riwayat-date">${dateStr}</span>
          <span style="font-size:11px;color:var(--gray-400)">${item.total_dukungan || 0} dukungan</span>
          ${chatButton}
        </div>
      </div>`;
  }

  load();
}


/* ══════════════════════════════════════════════
   HALAMAN: DASHBOARD (dashboard.php)
══════════════════════════════════════════════ */
function initDashboardPage() {
  const tbody        = document.getElementById('dashboard-tbody');
  const filterStatus = document.getElementById('filter-status');
  const filterSort   = document.getElementById('filter-sort');
  const rejectionBackdrop = document.getElementById('rejection-modal-backdrop');
  const rejectionForm = document.getElementById('rejection-form');
  const rejectionCategory = document.getElementById('rejection-category');
  const rejectionNote = document.getElementById('rejection-note');
  const rejectionNoteRequired = document.getElementById('rejection-note-required');
  const rejectionSubmit = document.getElementById('rejection-modal-submit');

  if (!tbody) return;

  // Ambil flag dari PHP yang disisipkan di dashboard.php
  const isPetugas = window.LIBSUGGEST_IS_PETUGAS === true;

  // Jumlah kolom menyesuaikan role (ada/tidaknya kolom Aksi)
  const colSpan = isPetugas ? 6 : 5;
  let rejectionCategories = null;
  let rejectionContext = null;

  function closeRejectionModal() {
    rejectionBackdrop?.classList.remove('open');
    rejectionContext = null;
  }

  document.getElementById('rejection-modal-close')?.addEventListener('click', closeRejectionModal);
  document.getElementById('rejection-modal-cancel')?.addEventListener('click', closeRejectionModal);
  rejectionBackdrop?.addEventListener('click', event => {
    if (event.target === rejectionBackdrop) closeRejectionModal();
  });
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape') closeRejectionModal();
  });

  rejectionCategory?.addEventListener('change', () => {
    const selected = rejectionCategory.selectedOptions[0];
    const requiresNote = selected?.dataset.requiresNote === '1';
    rejectionNote.required = requiresNote;
    rejectionNoteRequired.textContent = requiresNote ? '(wajib untuk kategori Lainnya)' : '(opsional)';
  });

  async function openRejectionModal(bookId, cell, book) {
    try {
      if (!rejectionCategories) {
        const response = await apiGet('api/dashboard.php?action=rejection_reasons');
        rejectionCategories = response.categories;
      }
      rejectionForm.reset();
      rejectionCategory.innerHTML = '<option value="">Pilih alasan</option>';
      rejectionCategories.forEach(category => {
        const option = document.createElement('option');
        option.value = category.id;
        option.textContent = category.nama;
        option.dataset.requiresNote = String(category.perlu_penjelasan);
        rejectionCategory.appendChild(option);
      });
      rejectionNote.required = false;
      rejectionNoteRequired.textContent = '(opsional)';
      rejectionContext = { bookId, cell, book };
      rejectionBackdrop.classList.add('open');
      rejectionCategory.focus();
    } catch (error) {
      Toast.error(error.message || 'Daftar alasan penolakan gagal dimuat');
    }
  }

  rejectionForm?.addEventListener('submit', async event => {
    event.preventDefault();
    if (!rejectionContext || !rejectionForm.reportValidity()) return;

    rejectionSubmit.disabled = true;
    try {
      const updated = await updateStatus(
        rejectionContext.bookId,
        'ditolak',
        rejectionContext.cell,
        rejectionContext.book,
        {
          categoryId: Number(rejectionCategory.value),
          note: rejectionNote.value.trim(),
        }
      );
      if (updated) closeRejectionModal();
    } finally {
      rejectionSubmit.disabled = false;
    }
  });

  async function load() {
    const status = filterStatus?.value || 'all';
    const sort   = filterSort?.value   || 'dukungan';
    tbody.innerHTML = `<tr><td colspan="${colSpan}" style="text-align:center;padding:2rem;color:var(--gray-400)">Memuat data...</td></tr>`;

    try {
      const data = await apiGet(`api/dashboard.php?action=list&status=${status}&sort=${sort}`);

      const m = data.metrics || {};
      document.getElementById('m-total')?.setAttribute('data-val', m.total_buku   || 0);
      document.getElementById('m-pending')?.setAttribute('data-val', m.pending     || 0);
      document.getElementById('m-disetujui')?.setAttribute('data-val', m.disetujui || 0);
      document.getElementById('m-user')?.setAttribute('data-val', m.total_user     || 0);
      ['m-total','m-pending','m-disetujui','m-user'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.textContent = el.getAttribute('data-val');
      });

      if (!data.books.length) {
        tbody.innerHTML = `<tr><td colspan="${colSpan}"><div class="empty-state"><div class="icon">📚</div><p>Belum ada usulan.</p></div></td></tr>`;
        return;
      }

      tbody.innerHTML = '';
      data.books.forEach((book, i) => {
        const authors = Array.isArray(book.authors) ? book.authors.join(', ') : '';
        const coverHtml = book.cover_url
          ? `<img class="td-cover" src="${book.cover_url}" alt="">`
          : `<div class="td-cover">📚</div>`;
        const statusBadge = `<span class="badge badge-${book.status_proposal || 'pending'}">${labelStatus(book.status_proposal || 'pending')}</span>`;

        const tr = document.createElement('tr');

        // Kolom Aksi hanya dirender kalau pegawai
        const aksiCell = isPetugas ? `<td id="action-${book.id}"></td>` : `<td id="action-${book.id}" style="display:none;"></td>`;

        tr.innerHTML = `
          <td><span class="rank-num">${i + 1}</span></td>
          <td>
            <div class="td-book">
              ${coverHtml}
              <div>
                <div class="td-title">${escHtml(book.title)}</div>
                <div class="td-author">${escHtml([authors, book.publisher].filter(Boolean).join(' · '))}</div>
              </div>
            </div>
          </td>
          <td style="font-size:12px;max-width:160px;">${escHtml(book.pengusul_list || '-')}</td>
          <td><span class="dukungan-num">${book.total_dukungan}</span></td>
          <td id="status-${book.id}">${statusBadge}</td>
          ${aksiCell}`;

        tbody.appendChild(tr);

        // Render tombol aksi hanya untuk pegawai
        if (isPetugas) {
          const actionCell = document.getElementById(`action-${book.id}`);
          renderActionBtns(actionCell, book);
        }
      });
    } catch (err) {
      tbody.innerHTML = `<tr><td colspan="${colSpan}" style="color:var(--danger);padding:1rem">${err.message}</td></tr>`;
    }
  }

  function renderActionBtns(cell, book) {
    cell.innerHTML = '';
    if (book.status_proposal === 'pending') {
      const btnS = document.createElement('button');
      btnS.className = 'btn-aksi btn-setujui';
      btnS.textContent = 'Setujui';
      btnS.addEventListener('click', () => updateStatus(book.id, 'disetujui', cell, book));

      const btnT = document.createElement('button');
      btnT.className = 'btn-aksi btn-tolak';
      btnT.textContent = 'Tolak';
      btnT.style.marginLeft = '6px';
      btnT.addEventListener('click', () => openRejectionModal(book.id, cell, book));

      cell.appendChild(btnS);
      cell.appendChild(btnT);
    } else if (book.status_proposal === 'disetujui') {
      const btnB = document.createElement('button');
      btnB.className = 'btn-aksi btn-setujui';
      btnB.textContent = 'Tandai Dibeli';
      btnB.addEventListener('click', () => updateStatus(book.id, 'dibeli', cell, book));
      cell.appendChild(btnB);
    } else {
      cell.textContent = '—';
    }
  }

  async function updateStatus(bookId, newStatus, cell, book, rejection = null) {
    try {
      await apiPost('api/dashboard.php?action=update_status', {
        book_id: bookId,
        new_status: newStatus,
        ...(rejection ? {
          kategori_verifikasi_id: rejection.categoryId,
          note: rejection.note,
        } : {}),
      });
      book.status_proposal = newStatus;
      document.getElementById(`status-${bookId}`).innerHTML =
        `<span class="badge badge-${newStatus}">${labelStatus(newStatus)}</span>`;
      renderActionBtns(cell, book);
      Toast.success(`Status diubah ke "${labelStatus(newStatus)}"`);

      if (newStatus === 'disetujui') {
        const el = document.getElementById('m-pending');
        if (el) el.textContent = Math.max(0, parseInt(el.textContent) - 1);
        const elA = document.getElementById('m-disetujui');
        if (elA) elA.textContent = parseInt(elA.textContent) + 1;
      }
      return true;
    } catch (err) {
      Toast.error(err.message);
      return false;
    }
  }

  filterStatus?.addEventListener('change', load);
  filterSort?.addEventListener('change', load);

  load();
}


/* ══════════════════════════════════════════════
   UTILITIES
══════════════════════════════════════════════ */
function escHtml(str) {
  if (!str) return '';
  return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function labelStatus(s) {
  const map = { pending:'Menunggu', disetujui:'Disetujui', dibeli:'Dibeli', tersedia:'Tersedia', ditolak:'Ditolak' };
  return map[s] || s;
}

function langLabel(code) {
  const map = { id:'Indonesia', en:'Inggris', ms:'Melayu', de:'Jerman', fr:'Perancis', ja:'Jepang', zh:'Mandarin' };
  return map[code] || code || '-';
}

function renderSkeletons(n) {
  return Array.from({length: n}, () => `
    <div class="book-card">
      <div class="book-cover-wrap skeleton skeleton-box"></div>
      <div class="book-body">
        <div class="skeleton-box" style="height:14px;border-radius:4px;margin-bottom:8px;width:90%"></div>
        <div class="skeleton-box" style="height:11px;border-radius:4px;margin-bottom:6px;width:60%"></div>
        <div class="skeleton-box" style="height:28px;border-radius:6px;margin-top:12px;"></div>
      </div>
    </div>`).join('');
}

/* ── Init ── */
document.addEventListener('DOMContentLoaded', () => {
  initSearchPage();
  initRiwayatPage();
  initDashboardPage();
});
