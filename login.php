<?php
session_start();
require_once 'config/koneksi.php';

$error = "";

if (isset($_POST['login'])) {
    $username = sanitize($_POST['username']);
    $password = sanitize($_POST['password']);

    $q = mysqli_query($koneksi, "SELECT * FROM admin WHERE username = '$username'");
    if (mysqli_num_rows($q) > 0) {
        $row = mysqli_fetch_assoc($q);
        if ($password === $row['password']) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_nama'] = $row['nama'];
            header("Location: admin/dashboard.php");
            exit();
        } else {
            $error = "Password salah!";
        }
    } else {
        $error = "Username tidak ditemukan!";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Login Admin - HOLA Laundry</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center justify-content-center vh-100">
    <div class="card border-0 shadow-sm p-4 rounded-4" style="max-width: 400px; width: 100%;">
        <div class="text-center mb-4">
            <h3 class="fw-bold">🧺 HOLA POS</h3>
            <p class="text-muted small">Masuk ke Sistem Kasir Admin</p>
        </div>
        <?php if ($error != ""): ?>
            <div class="alert alert-danger py-2 small"><?= $error; ?></div>
        <?php endif; ?>
        <form action="" method="POST">
            <div class="mb-3">
                <label class="form-label fw-semibold">Username</label>
                <input type="text" name="username" class="form-control" required placeholder="admin">
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Password</label>
                <input type="password" name="password" class="form-control" required placeholder="admin123">
            </div>
            <button type="submit" name="login" class="btn btn-primary w-100 fw-bold py-2 rounded-3">Masuk</button>
        </form>
    </div>
</body>
</html>