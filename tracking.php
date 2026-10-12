<?php
date_default_timezone_set('Asia/Jakarta');
require_once 'config/koneksi.php';

$resi_input = trim($_GET['resi'] ?? '');
$data_trx = null;
$error_msg = "";

$q_toko = mysqli_query($koneksi, "SELECT * FROM pengaturan_toko WHERE id = 1");
$toko_info = mysqli_fetch_assoc($q_toko) ?? [
    'nama_toko'   => 'HOLA LAUNDRY',
    'alamat_toko' => 'Jl. Raya Vokasi No. 123, Surabaya',
    'no_wa'       => '0812-3456-7890'
];

if (!empty($resi_input)) {
    $resi_clean = mysqli_real_escape_string($koneksi, $resi_input);
    $q = mysqli_query($koneksi, "SELECT t.*, p.nama as nama_pelanggan, p.no_hp FROM transaksi t JOIN pelanggan p ON t.pelanggan_id = p.id WHERE t.kode_resi = '$resi_clean'");
    if (mysqli_num_rows($q) > 0) {
        $data_trx = mysqli_fetch_assoc($q);
    } else {
        $error_msg = "Maaf, kode resi <b>" . htmlspecialchars($resi_input) . "</b> tidak ditemukan di sistem kami.";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cek Status Laundry - <?= htmlspecialchars($toko_info['nama_toko']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8fafc; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #1e293b; }
        .hero-section { background: linear-gradient(135deg, #1e293b 0%, #334155 100%); color: white; padding: 60px 20px; border-radius: 0 0 30px 30px; margin-bottom: 30px; text-align: center; }
        .main-card { max-width: 600px; margin: -40px auto 40px auto; background: #ffffff; border-radius: 20px; box-shadow: 0 15px 35px rgba(0,0,0,0.08); padding: 30px; position: relative; z-index: 10; }
        .progress-step { display: flex; align-items: center; justify-content: space-between; position: relative; margin: 30px 0; }
        .step-item { text-align: center; z-index: 2; flex: 1; }
        .step-icon { width: 45px; height: 45px; border-radius: 50%; background: #e2e8f0; color: #64748b; display: flex; align-items: center; justify-content: center; margin: 0 auto 8px auto; font-weight: bold; font-size: 1.1rem; }
        .step-item.active .step-icon { background: #0d6efd; color: #fff; box-shadow: 0 4px 12px rgba(13,110,253,0.3); }
        .step-item.completed .step-icon { background: #198754; color: #fff; }
        .step-text { font-size: 0.8rem; font-weight: 600; color: #64748b; }
        .step-item.active .step-text { color: #0d6efd; }
    </style>
</head>
<body>

    <!-- Header Banner -->
    <div class="hero-section">
        <h1 class="fw-bold mb-2">🧺 <?= htmlspecialchars($toko_info['nama_toko']); ?></h1>
        <p class="text-white-50 mb-0">Layanan cuci pakaian bersih, wangi, dan cepat. Cek status cucianmu di sini!</p>
    </div>

    <div class="container px-3">
        <div class="main-card">
            <div class="text-center mb-4">
                <h4 class="fw-bold">Lacak Status Cucian</h4>
                <p class="text-muted small">Masukkan kode resi yang tertera pada nota atau pesan WhatsApp kamu.</p>
            </div>

            <!-- Form Pencarian Resi -->
            <form action="index.php" method="GET" class="mb-4">
                <div class="input-group">
                    <input type="text" name="resi" class="form-control rounded-start-pill px-3 py-2" placeholder="Contoh: HLA-20261008-XXXX" value="<?= htmlspecialchars($resi_input); ?>" required>
                    <button type="submit" class="btn btn-primary rounded-end-pill px-4 fw-bold">Cek Status</button>
                </div>
            </form>

            <?php if (!empty($error_msg)): ?>
                <div class="alert alert-danger rounded-4 text-center small py-3"><?= $error_msg; ?></div>
            <?php endif; ?>

            <?php if ($data_trx): ?>
                <div class="card border-0 bg-light rounded-4 p-3 mb-4">
                    <div class="d-flex justify-content-between mb-2">
                        <small class="text-muted">Kode Resi:</small>
                        <strong class="text-primary"><?= $data_trx['kode_resi']; ?></strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <small class="text-muted">Nama Pelanggan:</small>
                        <span class="fw-semibold"><?= htmlspecialchars($data_trx['nama_pelanggan']); ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <small class="text-muted">Jenis Layanan:</small>
                        <span class="fw-semibold"><?= htmlspecialchars($data_trx['jenis_layanan']); ?> (<?= $data_trx['berat_qty']; ?> kg)</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <small class="text-muted">Tanggal Masuk:</small>
                        <span><?= date('d/m/Y H:i', strtotime($data_trx['tanggal_masuk'])); ?> WIB</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <small class="text-muted">Total & Pembayaran:</small>
                        <span>
                            <strong>Rp <?= number_format($data_trx['total_bayar'], 0, ',', '.'); ?></strong>
                            <?php if ($data_trx['status_pembayaran'] == 'lunas'): ?>
                                <span class="badge bg-success ms-1">Lunas</span>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark ms-1">Belum Lunas</span>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>

                <?php 
                    $status = $data_trx['status_laundry'];
                    $step1 = 'completed';
                    $step2 = 'active';
                    $step3 = '';

                    if ($status == 'siap_diambil' || $status == 'siap_diantar') {
                        $step2 = 'completed';
                        $step3 = 'active';
                    } elseif ($status == 'selesai') {
                        $step2 = 'completed';
                        $step3 = 'completed';
                    }
                ?>

                <!-- Indikator Progress Status -->
                <h6 class="fw-bold text-center mb-3">Progress Pengerjaan</h6>
                <div class="progress-step px-2">
                    <div class="step-item completed">
                        <div class="step-icon">✓</div>
                        <div class="step-text">Diterima</div>
                    </div>
                    <div class="step-item <?= $step2; ?>">
                        <div class="step-icon"><?= ($step2 == 'completed') ? '✓' : '2'; ?></div>
                        <div class="step-text">Diproses</div>
                    </div>
                    <div class="step-item <?= $step3; ?>">
                        <div class="step-icon">3</div>
                        <div class="step-text">Siap Ambil</div>
                    </div>
                </div>

                <div class="text-center mt-4">
                    <?php if ($status == 'selesai'): ?>
                        <div class="alert alert-success rounded-4 py-2 small fw-bold">🎉 Cucian telah selesai dan sudah diambil. Terima kasih!</div>
                    <?php elseif (in_array($status, ['siap_diambil', 'siap_diantar'])): ?>
                        <div class="alert alert-info rounded-4 py-2 small fw-bold text-white">✨ Cucian sudah siap! Silakan ambil ke toko atau menunggu kurir.</div>
                    <?php else: ?>
                        <div class="alert alert-warning rounded-4 py-2 small fw-bold">🧺 Cucian kamu sedang dicuci dan disetrika oleh tim kami.</div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <hr class="my-4 text-muted opacity-25">

            <div class="text-center">
                <small class="text-muted">Butuh bantuan atau antar-jemput? Hubungi WhatsApp kami di <br><strong class="text-primary"><?= htmlspecialchars($toko_info['no_wa']); ?></strong></small>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>