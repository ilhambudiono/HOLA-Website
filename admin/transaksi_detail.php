<?php
session_start();
require_once '../config/koneksi.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: ../login.php");
    exit();
}

$id = isset($_GET['id']) ? sanitize($_GET['id']) : '';
if (empty($id)) {
    header("Location: riwayat.php");
    exit();
}

$msg = "";

if (isset($_POST['update_status'])) {
    $status_laundry    = sanitize($_POST['status_laundry']);
    $status_pembayaran = sanitize($_POST['status_pembayaran']);

    $q_update = "UPDATE transaksi SET status_laundry = '$status_laundry', status_pembayaran = '$status_pembayaran' WHERE id = '$id'";
    if (mysqli_query($koneksi, $q_update)) {
        $msg = "Status pesanan berhasil diperbarui!";
    }
}

$q_trx = mysqli_query($koneksi, "SELECT t.*, p.nama as nama_pelanggan, p.no_hp, p.alamat 
                                 FROM transaksi t 
                                 JOIN pelanggan p ON t.pelanggan_id = p.id 
                                 WHERE t.id = '$id'");
$trx = mysqli_fetch_assoc($q_trx);

if (!$trx) {
    echo "Transaksi tidak ditemukan!";
    exit();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Detail Transaksi - HOLA Laundry</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8fafc; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .custom-navbar { background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); }
    </style>
</head>
<body>

    <nav class="navbar navbar-expand-lg navbar-dark custom-navbar">
        <div class="container">
            <a class="navbar-brand fw-bold" href="dashboard.php">🧺 HOLA POS Admin</a>
            <a href="riwayat.php" class="btn btn-outline-light btn-sm rounded-pill px-3">← Kembali ke Riwayat</a>
        </div>
    </nav>

    <div class="container my-4" style="max-width: 700px;">
        <?php if ($msg != ""): ?>
            <div class="alert alert-success alert-dismissible fade show rounded-3 mb-3" role="alert">
                ✨ <?= $msg; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="card border-0 shadow-sm p-4 rounded-4">
            <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-3">
                <div>
                    <span class="text-muted small">Kode Resi</span>
                    <h4 class="fw-bold text-primary mb-0"><?= htmlspecialchars($trx['kode_resi']); ?></h4>
                </div>
                <span class="badge bg-primary px-3 py-2 rounded-pill"><?= htmlspecialchars($trx['kecepatan']); ?></span>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-6">
                    <small class="text-muted d-block">Pelanggan</small>
                    <strong><?= htmlspecialchars($trx['nama_pelanggan']); ?></strong>
                    <div class="small text-muted"><?= htmlspecialchars($trx['no_hp']); ?></div>
                </div>
                <div class="col-6">
                    <small class="text-muted d-block">Layanan</small>
                    <strong><?= htmlspecialchars($trx['jenis_layanan']); ?> (<?= htmlspecialchars($trx['berat_qty']); ?> kg)</strong>
                    <div class="small text-muted">Tambahan: <?= htmlspecialchars($trx['tambahan']); ?></div>
                </div>
                <div class="col-6">
                    <small class="text-muted d-block">Total Bayar</small>
                    <h5 class="fw-bold text-success mb-0">Rp <?= number_format($trx['total_bayar'], 0, ',', '.'); ?></h5>
                </div>
                <div class="col-6">
                    <small class="text-muted d-block">Metode Pembayaran</small>
                    <span class="badge bg-light text-dark border"><?= htmlspecialchars($trx['metode_pembayaran']); ?></span>
                </div>
            </div>

            <form action="" method="POST" class="border-top pt-3">
                <h6 class="fw-bold mb-3">Pembaruan Progress Pesanan</h6>
                
                <div class="mb-3">
                    <label class="form-label fw-semibold">Status Pengerjaan Laundry</label>
                    <select name="status_laundry" class="form-select">
                        <option value="menunggu_diproses" <?= $trx['status_laundry'] == 'menunggu_diproses' ? 'selected' : ''; ?>>1. Menunggu Diproses</option>
                        <option value="dicuci" <?= $trx['status_laundry'] == 'dicuci' ? 'selected' : ''; ?>>2. Dicuci</option>
                        <option value="dikeringkan" <?= $trx['status_laundry'] == 'dikeringkan' ? 'selected' : ''; ?>>3. Dikeringkan</option>
                        <option value="disetrika" <?= $trx['status_laundry'] == 'disetrika' ? 'selected' : ''; ?>>4. Disetrika</option>
                        <option value="quality_check" <?= $trx['status_laundry'] == 'quality_check' ? 'selected' : ''; ?>>5. Quality Check</option>
                        <option value="dikemas" <?= $trx['status_laundry'] == 'dikemas' ? 'selected' : ''; ?>>6. Dikemas</option>
                        <option value="siap_diambil" <?= $trx['status_laundry'] == 'siap_diambil' ? 'selected' : ''; ?>>7. Siap Diambil / Diantar</option>
                        <option value="selesai" <?= $trx['status_laundry'] == 'selesai' ? 'selected' : ''; ?>>8. Selesai (Sudah Diterima Pelanggan)</option>
                    </select>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-semibold">Status Pembayaran</label>
                    <select name="status_pembayaran" class="form-select">
                        <option value="belum_lunas" <?= $trx['status_pembayaran'] == 'belum_lunas' ? 'selected' : ''; ?>>Belum Lunas</option>
                        <option value="lunas" <?= $trx['status_pembayaran'] == 'lunas' ? 'selected' : ''; ?>>Lunas</option>
                    </select>
                </div>

                <button type="submit" name="update_status" class="btn btn-primary w-100 fw-bold py-2 rounded-3">
                    💾 Simpan Perubahan Status
                </button>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>