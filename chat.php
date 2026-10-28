<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');
requireLogin();

$action = $_GET['action'] ?? '';
$user = currentUser();
$isAdmin = isPetugas();

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'my_rejected') {
    if ($isAdmin) {
        http_response_code(403);
        die(json_encode(['error' => 'Daftar ini hanya tersedia untuk pengusul']));
    }

    $proposals = dbFetchAll(
        "SELECT
            p.id AS proposal_id,
            p.user_name,
            p.status,
            p.note_petugas,
            kv.nama AS kategori_verifikasi,
            b.title,
            (SELECT COUNT(*) FROM proposal_messages pm
             WHERE pm.proposal_id = p.id) AS message_count,
            (SELECT COUNT(*) FROM proposal_messages pm
             WHERE pm.proposal_id = p.id
               AND pm.sender_role = 'admin'
               AND pm.read_at IS NULL) AS unread_count
         FROM proposals p
         JOIN books b ON b.id = p.book_id
         LEFT JOIN kategori_verifikasi kv ON kv.id = p.kategori_verifikasi_id
         WHERE p.uid = ? AND p.status = 'ditolak'
         ORDER BY p.updated_at DESC",
        [$user['uid']]
    );
    echo json_encode(['proposals' => $proposals]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'inbox') {
    requirePetugas();
    $inbox = dbFetchAll(
        "SELECT
            p.id AS proposal_id,
            p.user_name,
            p.user_role,
            b.title,
            (SELECT pm.message FROM proposal_messages pm
             WHERE pm.proposal_id = p.id ORDER BY pm.id DESC LIMIT 1) AS last_message,
            (SELECT pm.created_at FROM proposal_messages pm
             WHERE pm.proposal_id = p.id ORDER BY pm.id DESC LIMIT 1) AS last_message_at,
            (SELECT COUNT(*) FROM proposal_messages pm
             WHERE pm.proposal_id = p.id
               AND pm.sender_role = 'pengusul'
               AND pm.read_at IS NULL) AS unread_count
         FROM proposals p
         JOIN books b ON b.id = p.book_id
         WHERE p.status = 'ditolak'
           AND EXISTS (
             SELECT 1 FROM proposal_messages pm WHERE pm.proposal_id = p.id
           )
         ORDER BY COALESCE(
             (SELECT pm.created_at FROM proposal_messages pm
              WHERE pm.proposal_id = p.id ORDER BY pm.id DESC LIMIT 1),
             p.updated_at
         ) DESC"
    );
    echo json_encode(['conversations' => $inbox]);
    exit;
}

$body = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrfToken();
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    if (!is_array($body)) {
        http_response_code(400);
        die(json_encode(['error' => 'Request body tidak valid']));
    }
    $action = $body['action'] ?? $action;
}

$proposalId = (int)($_GET['proposal_id'] ?? $body['proposal_id'] ?? 0);
if ($proposalId < 1) {
    http_response_code(400);
    die(json_encode(['error' => 'proposal_id wajib diisi']));
}

$proposal = dbFetch(
    'SELECT p.id, p.uid, p.status, b.title
     FROM proposals p JOIN books b ON b.id = p.book_id
     WHERE p.id = ?',
    [$proposalId]
);
if (!$proposal) {
    http_response_code(404);
    die(json_encode(['error' => 'Usulan tidak ditemukan']));
}

if (!$isAdmin && $proposal['uid'] !== $user['uid']) {
    http_response_code(403);
    die(json_encode(['error' => 'Kamu tidak memiliki akses ke percakapan ini']));
}
if ($proposal['status'] !== 'ditolak') {
    http_response_code(409);
    die(json_encode(['error' => 'Percakapan hanya tersedia untuk usulan yang ditolak']));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'mark_read') {
    dbQuery(
        'UPDATE proposal_messages
         SET read_at = CURRENT_TIMESTAMP
         WHERE proposal_id = ? AND sender_role <> ? AND read_at IS NULL',
        [$proposalId, $isAdmin ? 'admin' : 'pengusul']
    );
    echo json_encode(['success' => true]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'messages') {
    $messages = dbFetchAll(
        'SELECT id, sender_uid, sender_name, sender_role, message, read_at, created_at
         FROM proposal_messages
         WHERE proposal_id = ?
         ORDER BY id ASC',
        [$proposalId]
    );
    echo json_encode([
        'proposal' => ['id' => $proposalId, 'title' => $proposal['title']],
        'messages' => $messages,
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'send') {
    if (!isset($body['message']) || !is_string($body['message'])) {
        http_response_code(400);
        die(json_encode(['error' => 'Format pesan tidak valid']));
    }
    $message = trim($body['message']);
    if ($message === '' || mb_strlen($message) > 2000) {
        http_response_code(400);
        die(json_encode(['error' => 'Pesan wajib diisi dan maksimal 2000 karakter']));
    }

    dbInsert(
        'INSERT INTO proposal_messages
            (proposal_id, sender_uid, sender_name, sender_role, message)
         VALUES (?, ?, ?, ?, ?)',
        [$proposalId, $user['uid'], $user['name'], $isAdmin ? 'admin' : 'pengusul', $message]
    );
    echo json_encode(['success' => true]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Action tidak dikenali']);
