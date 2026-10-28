<?php
/**
 * LibSuggest — config/session.php
 * Disesuaikan dengan session aktual login.php Palapa UNIKA.
 *
 * Session yang di-set oleh login.php:
 *   $_SESSION['nopeg']   → ID pegawai (isi ID yang mulai dengan "58")
 *   $_SESSION['noinduk'] → NIM mahasiswa
 *   $_SESSION['picture'] → URL foto Google
 *   $_SESSION['nama']    → Nama lengkap dari Google (BARU - perlu ditambah di login.php)
 *   $_SESSION['email']   → Email Google (BARU - perlu ditambah di login.php)
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLocalhostRequest(): bool {
    $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
    $remoteAddress = $_SERVER['REMOTE_ADDR'] ?? '';
    if ($host === '') {
        return false;
    }

    $host = strtolower(trim(explode(':', $host)[0]));
    $isLocalHostName = in_array($host, ['localhost', '127.0.0.1', '::1'], true)
        || str_ends_with($host, '.test');

    return $isLocalHostName && in_array($remoteAddress, ['127.0.0.1', '::1'], true);
}

// ──────────────────────────────────────────────
// HELPER: Ambil user ID (nopeg atau noinduk)
// ──────────────────────────────────────────────
function getSessionUID(): string {
    if (isLocalhostRequest()) {
        return !empty($_SESSION['local_auth_uid']) ? $_SESSION['local_auth_uid'] : '';
    }

    if (!empty($_SESSION['nopeg']))   return $_SESSION['nopeg'];
   
    if (!empty($_SESSION['noinduk'])) return $_SESSION['noinduk'];
    // if (!empty($_SESSION['noinduk'] === '23.N1.0016')) return $_SESSION['nopeg'];
    
    return '';
}
function getExceptionPegawai(): array {
    return [
        '23.N1.0016',
        // tambahkan NIM lain di sini jika perlu
    ];
}
// ──────────────────────────────────────────────
// HELPER: Deteksi role dari keberadaan session key
// ──────────────────────────────────────────────
// function getSessionRole(): string {
//     $uid = getSessionUID();
//     if (empty($uid)) return 'guest';

//     if (!empty($_SESSION['nopeg'])) return 'pegawai';

//     return 'mahasiswa';
// }
function getSessionRole(): string {
    if (isLocalhostRequest()) {
        return ($_SESSION['local_auth_role'] ?? '') === 'admin' ? 'pegawai'
            : (!empty($_SESSION['local_auth_uid']) ? 'mahasiswa' : 'guest');
    }

    $uid = getSessionUID();
    if (empty($uid)) return 'guest';

    if (!empty($_SESSION['nopeg'])) return 'pegawai';

    // ✅ Cek apakah noinduk masuk daftar exception pegawai
    if (!empty($_SESSION['noinduk']) &&
        in_array($_SESSION['noinduk'], getExceptionPegawai(), true)) {
        return 'pegawai';
    }

    return 'mahasiswa';
}

function localLoginUrl(): string {
    return localAppUrl('login.php');
}

function localAppUrl(string $file): string {
    $scriptDirectory = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    if (basename($scriptDirectory) === 'api') {
        $scriptDirectory = dirname($scriptDirectory);
    }
    return rtrim($scriptDirectory, '/') . '/' . ltrim($file, '/');
}

// ──────────────────────────────────────────────
// HELPER: Cek login
// ──────────────────────────────────────────────
function requireLogin(): void {
    if (empty(getSessionUID())) {
        if (isLocalhostRequest()) {
            header('Location: ' . localLoginUrl());
            exit;
        }
        header('Location: ../../login.php');
        exit;
    }
}

// ──────────────────────────────────────────────
// HELPER: Ambil current user
// ──────────────────────────────────────────────
function currentUser(): array {
    $uid  = getSessionUID();
    if (isLocalhostRequest() && !empty($_SESSION['local_auth_uid'])) {
        $nama = $_SESSION['local_auth_name'] ?? $uid;
        $words = array_filter(explode(' ', $nama));
        $initials = strtoupper(implode('', array_map(fn($w) => $w[0], $words)));
        return [
            'uid' => $uid,
            'name' => $nama,
            'email' => $_SESSION['local_auth_username'] ?? '',
            'photo' => '',
            'picture' => '',
            'role' => getSessionRole(),
            'display_role' => ($_SESSION['local_auth_role'] ?? '') === 'admin' ? 'Admin' : 'Pengusul',
            'initials' => mb_substr($initials, 0, 2),
            'local_auth_id' => (int)$_SESSION['local_auth_id'],
        ];
    }

    $nama = $_SESSION['nama'] ?? '';

    // Fallback: kalau nama kosong, gunakan uid sebagai display name
    if (empty($nama)) {
        $nama = $uid;
    }

    // Buat inisial dari nama (untuk avatar fallback jika tidak ada foto)
    $words    = array_filter(explode(' ', $nama));
    $initials = strtoupper(implode('', array_map(fn($w) => $w[0], $words)));
    $initials = mb_substr($initials, 0, 2);

    $role = getSessionRole();
    return [
        'uid'      => $uid,
        'name'     => $nama,
        'email'    => $_SESSION['email']   ?? '',
        'photo'    => $_SESSION['picture'] ?? '',
        'picture'  => $_SESSION['picture'] ?? '', // alias backward-compatible
        'role'     => $role,
        'display_role' => $role,
        'initials' => $initials,
    ];
}

// ──────────────────────────────────────────────
// HELPER: Gate-keeping petugas
// ──────────────────────────────────────────────
function isPetugas(): bool {
    return getSessionRole() === 'pegawai';
}

function requirePetugas(): void {
    requireLogin();
    if (!isPetugas()) {
        if (isLocalhostRequest()) {
            header('Location: ' . localAppUrl('index.php'));
            exit;
        }
        header('Location: ../index.php');
        exit;
    }
}

// ──────────────────────────────────────────────
// HELPER: Akses dashboard (semua user login boleh)
// ──────────────────────────────────────────────
function requireDashboardAccess(): void {
    requireLogin(); // cukup pastikan sudah login
}

function getCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function requireValidCsrfToken(): void {
    $submittedToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if ($submittedToken === '' || !hash_equals(getCsrfToken(), $submittedToken)) {
        http_response_code(403);
        die(json_encode(['error' => 'Sesi tidak valid. Muat ulang halaman lalu coba lagi.']));
    }
}

function loginLocalUser(array $account): void {
    session_regenerate_id(true);
    $_SESSION['local_auth_id'] = (int)$account['id'];
    $_SESSION['local_auth_uid'] = 'local_' . (int)$account['id'];
    $_SESSION['local_auth_role'] = $account['role'];
    $_SESSION['local_auth_name'] = $account['display_name'];
    $_SESSION['local_auth_username'] = $account['username'];
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
