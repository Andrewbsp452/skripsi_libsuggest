<?php
/**
 * LibSuggest — api/riwayat.php
 * Ambil riwayat usulan dan vote user yang sedang login.
 *
 * GET params:
 *   page → halaman (default 0)
 *
 * Response JSON:
 *   { proposals: [...], votes: [...] }
 */

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    die(json_encode(['error' => 'Method not allowed']));
}

requireLogin();
$user   = currentUser();
$page   = max(0, (int)($_GET['page'] ?? 0));
$perPage = 20;
$offset  = $page * $perPage;

// Usulan yang dibuat sendiri
$proposals = dbFetchAll(
    'SELECT
        p.id AS proposal_id,
        p.alasan,
        p.status,
        kv.nama AS kategori_verifikasi,
        p.note_petugas,
        p.created_at,
        (SELECT COUNT(*) FROM proposal_messages pm
         WHERE pm.proposal_id = p.id
           AND pm.sender_role = \'admin\'
           AND pm.read_at IS NULL) AS chat_unread,
        b.id         AS book_id,
        b.google_id,
        b.title,
        b.authors,
        b.publisher,
        b.published_year,
        b.cover_url,
        bs.total_dukungan
     FROM proposals p
     JOIN books b           ON b.id = p.book_id
     JOIN v_book_summary bs ON bs.id = b.id
     LEFT JOIN kategori_verifikasi kv ON kv.id = p.kategori_verifikasi_id
     WHERE p.uid = ?
     ORDER BY p.created_at DESC
     LIMIT ? OFFSET ?',
    [$user['uid'], $perPage, $offset]
);

// Buku yang di-vote (dukung, tapi bukan pengusul)
$votes = dbFetchAll(
    'SELECT
        v.id         AS vote_id,
        v.created_at AS voted_at,
        b.id         AS book_id,
        b.title,
        b.authors,
        b.publisher,
        b.cover_url,
        bs.total_dukungan
     FROM votes v
     JOIN books b           ON b.id = v.book_id
     JOIN v_book_summary bs ON bs.id = b.id
     WHERE v.uid = ?
     ORDER BY v.created_at DESC
     LIMIT ? OFFSET ?',
    [$user['uid'], $perPage, $offset]
);

// Decode authors JSON
foreach ($proposals as &$p) {
    $p['authors'] = json_decode($p['authors'] ?? '[]', true);
}
foreach ($votes as &$v) {
    $v['authors'] = json_decode($v['authors'] ?? '[]', true);
}
unset($p, $v);

echo json_encode([
    'proposals' => $proposals,
    'votes'     => $votes,
]);