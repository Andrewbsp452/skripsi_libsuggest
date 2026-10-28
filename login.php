<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/db.php';

if (!isLocalhostRequest()) {
    header('Location: ../../login.php');
    exit;
}

if (getSessionUID() !== '') {
    header('Location: ' . (isPetugas() ? 'dashboard.php' : 'index.php'));
    exit;
}

$error = '';
$username = '';
$role = 'pengusul';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'pengusul';
    $token = $_POST['csrf_token'] ?? '';

    if ($token === '' || !hash_equals(getCsrfToken(), $token)) {
        $error = 'Sesi formulir sudah kedaluwarsa. Muat ulang halaman dan coba lagi.';
    } elseif (!in_array($role, ['pengusul', 'admin'], true)) {
        $error = 'Jenis akun tidak valid.';
    } elseif ($username === '' || $password === '') {
        $error = 'Jenis akun, username, dan password wajib diisi.';
    } else {
        try {
            $account = dbFetch(
                'SELECT id, username, password_hash, display_name, role
                 FROM local_accounts WHERE username = ?',
                [$username]
            );
            if (!$account || $account['role'] !== $role || !password_verify($password, $account['password_hash'])) {
                $error = 'Username atau password tidak valid.';
            } else {
                loginLocalUser($account);
                header('Location: ' . ($role === 'admin' ? 'dashboard.php' : 'index.php'));
                exit;
            }
        } catch (Throwable $exception) {
            error_log('Local login failed: ' . $exception->getMessage());
            $error = 'Login tidak dapat diproses. Periksa koneksi database.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login — LibSuggest</title>
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth-page">
  <main class="auth-card">
    <div class="brand-icon auth-brand-icon">
      <svg viewBox="0 0 24 24"><path d="M4 4h4v16H4zM10 4h4v16h-4zM16 4h4v16h-4z"/></svg>
    </div>
    <h1>Masuk ke LibSuggest</h1>
    <p class="auth-subtitle">Masuk sebagai pengusul atau admin perpustakaan.</p>
    <?php if ($error !== ''): ?><div class="auth-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <form method="post" class="auth-form">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
      <label class="form-label" for="role">Masuk sebagai</label>
      <select class="form-input" id="role" name="role" required>
        <option value="pengusul" <?= $role === 'pengusul' ? 'selected' : '' ?>>Pengusul</option>
        <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>Admin Perpustakaan</option>
      </select>
      <label class="form-label" for="username">Username</label>
      <input class="form-input" id="username" name="username" type="text" maxlength="50" autocomplete="username" required value="<?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?>">
      <label class="form-label" for="password">Password</label>
      <input class="form-input" id="password" name="password" type="password" autocomplete="current-password" required>
      <button class="btn btn-primary auth-submit" type="submit">Masuk</button>
    </form>
    <p class="auth-footer">Belum punya akun pengusul? <a href="register.php">Daftar di sini</a></p>
  </main>
</body>
</html>
