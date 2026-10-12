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
    // Mencari berdasarkan 4 digit terakhir dari kode_resi
    $q = mysqli_query($koneksi, "SELECT t.*, p.nama as nama_pelanggan, p.no_hp FROM transaksi t JOIN pelanggan p ON t.pelanggan_id = p.id WHERE t.kode_resi LIKE '%$resi_clean'");
    if (mysqli_num_rows($q) > 0) {
        $data_trx = mysqli_fetch_assoc($q);
    } else {
        $error_msg = "Resi dengan 4 digit akhir <b>" . htmlspecialchars($resi_input) . "</b> tidak ditemukan.";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tracking - <?= htmlspecialchars($toko_info['nama_toko']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background-color: #f1f5f9; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #1e293b; min-height: 100vh; display: flex; flex-direction: column; }
        .mobile-header { background: linear-gradient(135deg, #142448 100%); color: white; padding: 30px 20px 45px 20px; text-align: center; border-radius: 0 0 25px 25px; }
        .main-wrapper { max-width: 480px; width: 100%; margin: -30px auto 20px auto; padding: 0 15px; flex: 1; }
        .content-card { background: #ffffff; border-radius: 20px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); padding: 25px; }
        
        /* Progress Bar Elegan */
        .tracking-steps { display: flex; justify-content: space-between; position: relative; margin: 30px 0 20px 0; }
        .tracking-steps::before { content: ''; position: absolute; top: 20px; left: 15%; right: 15%; height: 3px; background: #e2e8f0; z-index: 1; }
        .step-box { position: relative; z-index: 2; text-align: center; flex: 1; }
        .step-circle { width: 42px; height: 42px; border-radius: 50%; background: #e2e8f0; color: #64748b; display: flex; align-items: center; justify-content: center; margin: 0 auto 8px auto; font-size: 1.1rem; transition: all 0.3s ease; }
        .step-box.active .step-circle { background: #0d6efd; color: #fff; box-shadow: 0 0 12px rgba(13,110,253,0.4); }
        .step-box.completed .step-circle { background: #198754; color: #fff; }
        .step-label { font-size: 0.75rem; font-weight: 600; color: #64748b; }
        .step-box.active .step-label { color: #0d6efd; }
    </style>
</head>
<body>

    <div class="mobile-header">
        <h4 class="fw-bold mb-1">🧺 <?= htmlspecialchars($toko_info['nama_toko']); ?></h4>
        <p class="text-white-50 small mb-0">Cek status cucian cukup masukkan 4 digit terakhir resi</p>
    </div>

    <div class="main-wrapper">
        <div class="content-card">
            <form action="index.php" method="GET" class="mb-4">
                <label class="form-label small fw-bold text-muted mb-2">4 Digit Akhir Kode Resi</label>
                <div class="input-group">
                    <input type="text" name="resi" class="form-control rounded-start-pill px-3 py-2 bg-light border-0" placeholder="Cth: E430" maxlength="6" value="<?= htmlspecialchars($resi_input); ?>" required>
                    <button type="submit" class="btn btn-primary rounded-end-pill px-4 fw-bold">Cek</button>
                </div>
            </form>

            <?php if (!empty($error_msg)): ?>
                <div class="alert alert-danger rounded-4 text-center small py-3 border-0 bg-danger-subtle text-danger"><?= $error_msg; ?></div>
            <?php endif; ?>

            <?php if ($data_trx): ?>
                <div class="bg-light rounded-4 p-3 mb-4 border-0">
                    <div class="d-flex justify-content-between mb-2 small">
                        <span class="text-muted">No. Resi:</span>
                        <strong class="text-primary"><?= $data_trx['kode_resi']; ?></strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2 small">
                        <span class="text-muted">Pelanggan:</span>
                        <span class="fw-semibold"><?= htmlspecialchars($data_trx['nama_pelanggan']); ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2 small">
                        <span class="text-muted">Layanan:</span>
                        <span class="fw-semibold"><?= htmlspecialchars($data_trx['jenis_layanan']); ?> (<?= $data_trx['berat_qty']; ?> kg)</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2 small">
                        <span class="text-muted">Masuk:</span>
                        <span><?= date('d/m/Y H:i', strtotime($data_trx['tanggal_masuk'])); ?></span>
                    </div>
                    <div class="d-flex justify-content-between small">
                        <span class="text-muted">Total:</span>
                        <span><strong>Rp <?= number_format($data_trx['total_bayar'], 0, ',', '.'); ?></strong> <span class="badge <?= $data_trx['status_pembayaran'] == 'lunas' ? 'bg-success' : 'bg-warning text-dark'; ?> ms-1" style="font-size: 0.65rem;"><?= ucfirst($data_trx['status_pembayaran']); ?></span></span>
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

                <h6 class="fw-bold text-center mb-2 small text-uppercase tracking-wider text-muted">Status Pengerjaan</h6>
                <div class="tracking-steps">
                    <div class="step-box completed">
                        <div class="step-circle"><i class="bi bi-box-seam"></i></div>
                        <div class="step-label">Diterima</div>
                    </div>
                    <div class="step-box <?= $step2; ?>">
                        <div class="step-circle">
                            <?php if($step2 == 'completed'): ?>
                                <i class="bi bi-check-lg"></i>
                            <?php else: ?>
                                <i class="bi bi-arrow-repeat spin"></i>
                            <?php endif; ?>
                        </div>
                        <div class="step-label">Diproses</div>
                    </div>
                    <div class="step-box <?= $step3; ?>">
                        <div class="step-circle">
                            <?php if($step3 == 'completed'): ?>
                                <i class="bi bi-check-lg"></i>
                            <?php else: ?>
                                <i class="bi bi-bag-check"></i>
                            <?php endif; ?>
                        </div>
                        <div class="step-label">Siap Ambil</div>
                    </div>
                </div>

                <div class="text-center mt-4">
                    <?php if ($status == 'selesai'): ?>
                        <div class="alert alert-success rounded-4 py-2 small fw-bold border-0">🎉 Cucian sudah diambil. Terima kasih!</div>
                    <?php elseif (in_array($status, ['siap_diambil', 'siap_diantar'])): ?>
                        <div class="alert alert-info rounded-4 py-2 small fw-bold text-white border-0 bg-primary">✨ Cucian sudah siap diambil / diantar!</div>
                    <?php else: ?>
                        <div class="alert alert-warning rounded-4 py-2 small fw-bold border-0 bg-warning-subtle text-warning-emphasis">🧺 Cucian sedang dicuci dan dikerjakan oleh kami</div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="text-center mt-4 pt-2 border-top">
                <small class="text-muted" style="font-size: 0.75rem;">Butuh bantuan? Hubungi WhatsApp: <br><strong class="text-dark"><?= htmlspecialchars($toko_info['no_wa']); ?></strong></small>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>