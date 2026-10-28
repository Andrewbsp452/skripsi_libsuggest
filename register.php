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
$displayName = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $displayName = trim($_POST['display_name'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $token = $_POST['csrf_token'] ?? '';

    if ($token === '' || !hash_equals(getCsrfToken(), $token)) {
        $error = 'Sesi formulir sudah kedaluwarsa. Muat ulang halaman dan coba lagi.';
    } elseif (!preg_match('/^[A-Za-z0-9._-]{3,50}$/', $username)) {
        $error = 'Username harus 3–50 karakter: huruf, angka, titik, garis bawah, atau tanda hubung.';
    } elseif ($displayName === '' || mb_strlen($displayName) > 150) {
        $error = 'Nama wajib diisi dan maksimal 150 karakter.';
    } elseif (strlen($password) < 10) {
        $error = 'Password minimal 10 karakter.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Konfirmasi password tidak sama.';
    } else {
        try {
            $id = dbInsert(
                'INSERT INTO local_accounts (username, password_hash, display_name, role)
                 VALUES (?, ?, ?, ?)',
                [$username, password_hash($password, PASSWORD_DEFAULT), $displayName, 'pengusul']
            );
            loginLocalUser([
                'id' => $id,
                'username' => $username,
                'display_name' => $displayName,
                'role' => 'pengusul',
            ]);
            header('Location: index.php');
            exit;
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                $error = 'Username tersebut sudah digunakan.';
            } else {
                error_log('Local registration failed: ' . $exception->getMessage());
                $error = 'Pendaftaran tidak dapat diproses. Periksa koneksi database.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Daftar Pengusul — LibSuggest</title>
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth-page">
  <main class="auth-card">
    <div class="brand-icon auth-brand-icon">
      <svg viewBox="0 0 24 24"><path d="M4 4h4v16H4zM10 4h4v16h-4zM16 4h4v16h-4z"/></svg>
    </div>
    <h1>Daftar sebagai Pengusul</h1>
    <p class="auth-subtitle">Akun baru hanya memiliki akses pengusul.</p>
    <?php if ($error !== ''): ?><div class="auth-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <form method="post" class="auth-form">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
      <label class="form-label" for="display_name">Nama</label>
      <input class="form-input" id="display_name" name="display_name" type="text" maxlength="150" autocomplete="name" required value="<?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?>">
      <label class="form-label" for="username">Username</label>
      <input class="form-input" id="username" name="username" type="text" minlength="3" maxlength="50" pattern="[A-Za-z0-9._-]+" autocomplete="username" required value="<?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?>">
      <label class="form-label" for="password">Password</label>
      <input class="form-input" id="password" name="password" type="password" minlength="10" autocomplete="new-password" required>
      <label class="form-label" for="confirm_password">Ulangi password</label>
      <input class="form-input" id="confirm_password" name="confirm_password" type="password" minlength="10" autocomplete="new-password" required>
      <button class="btn btn-primary auth-submit" type="submit">Daftar</button>
    </form>
    <p class="auth-footer">Sudah punya akun? <a href="login.php">Masuk</a></p>
  </main>
</body>
</html>
