<?php
session_start();
require_once '../config/koneksi.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: ../login.php");
    exit();
}

$msg = "";
$error = "";

if (isset($_POST['ubah_password'])) {
    $admin_id   = $_SESSION['admin_id'];
    $pass_lama  = $_POST['pass_lama'];
    $pass_baru  = $_POST['pass_baru'];
    $konfirmasi = $_POST['konfirmasi_pass'];

    $q = mysqli_query($koneksi, "SELECT password FROM users WHERE id = '$admin_id'");
    $u = mysqli_fetch_assoc($q);

    if (password_verify($pass_lama, $u['password'])) {
        if ($pass_baru === $konfirmasi) {
            $hash_baru = password_hash($pass_baru, PASSWORD_BCRYPT);
            mysqli_query($koneksi, "UPDATE users SET password = '$hash_baru' WHERE id = '$admin_id'");
            $msg = "Password berhasil diperbarui!";
        } else {
            $error = "Konfirmasi password baru tidak cocok!";
        }
    } else {
        $error = "Password lama salah!";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Ganti Password - HOLA Laundry</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand fw-bold" href="dashboard.php">🧺 HOLA POS Admin</a>
            <a href="dashboard.php" class="btn btn-outline-light btn-sm">← Kembali ke Dashboard</a>
        </div>
    </nav>

    <div class="container my-4">
        <div class="card border-0 shadow-sm p-4 mx-auto" style="max-width: 450px;">
            <h4 class="fw-bold mb-3">Ubah Password Admin</h4>

            <?php if ($msg != ""): ?>
                <div class="alert alert-success"><?= $msg; ?></div>
            <?php endif; ?>
            <?php if ($error != ""): ?>
                <div class="alert alert-danger"><?= $error; ?></div>
            <?php endif; ?>

            <form action="" method="POST">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Password Lama</label>
                    <input type="password" name="pass_lama" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Password Baru</label>
                    <input type="password" name="pass_baru" class="form-control" required>
                </div>
                <div class="mb-4">
                    <label class="form-label fw-semibold">Konfirmasi Password Baru</label>
                    <input type="password" name="konfirmasi_pass" class="form-control" required>
                </div>
                <button type="submit" name="ubah_password" class="btn btn-primary w-100 fw-bold">Simpan Password Baru</button>
            </form>
        </div>
    </div>

</body>
</html>