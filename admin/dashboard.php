<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
require_once '../config/koneksi.php';

if (!function_exists('sanitize')) {
    function sanitize($data) {
        global $koneksi;
        $data = $data ?? '';
        return mysqli_real_escape_string($koneksi, trim($data));
    }
}

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: ../login.php");
    exit();
}

$msg_add   = "";
$msg_error = "";

// AUTO-CREATE TABEL PENGATURAN TOKO
mysqli_query($koneksi, "CREATE TABLE IF NOT EXISTS pengaturan_toko (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nama_toko VARCHAR(100) NOT NULL,
    alamat_toko TEXT NOT NULL,
    no_wa VARCHAR(20) NOT NULL,
    pesan_struk TEXT NOT NULL
)");    

$q_check_empty = mysqli_query($koneksi, "SELECT id FROM pengaturan_toko WHERE id = 1");
if (mysqli_num_rows($q_check_empty) == 0) {
    mysqli_query($koneksi, "INSERT INTO pengaturan_toko (id, nama_toko, alamat_toko, no_wa, pesan_struk) VALUES 
    (1, 'HOLA LAUNDRY', 'Jl. Raya Vokasi No. 123, Surabaya', '0812-3456-7890', 'Simpan struk ini sebagai bukti pengambilan.')");
}

// Handle Simpan / Update Pengaturan Profil Toko
if (isset($_POST['simpan_profil_toko'])) {
    $nama_toko   = sanitize($_POST['nama_toko'] ?? '');
    $alamat_toko = sanitize($_POST['alamat_toko'] ?? '');
    $no_wa       = sanitize($_POST['no_wa'] ?? '');
    $pesan_struk = sanitize($_POST['pesan_struk'] ?? '');

    $q_up = "UPDATE pengaturan_toko SET nama_toko = '$nama_toko', alamat_toko = '$alamat_toko', no_wa = '$no_wa', pesan_struk = '$pesan_struk' WHERE id = 1";
    if (mysqli_query($koneksi, $q_up)) {
        $msg_add = "Profil Toko berhasil diperbarui!";
    } else {
        $msg_error = "Gagal menyimpan profil toko: " . mysqli_error($koneksi);
    }
}

$q_toko = mysqli_query($koneksi, "SELECT * FROM pengaturan_toko WHERE id = 1");
$toko_info = mysqli_fetch_assoc($q_toko) ?? [
    'nama_toko'   => 'HOLA LAUNDRY',
    'alamat_toko' => 'Jl. Raya Vokasi No. 123, Surabaya',
    'no_wa'       => '0812-3456-7890',
    'pesan_struk' => 'Simpan struk ini sebagai bukti pengambilan.'
];

// Handle Simpan Transaksi POS Baru (Tanpa opsi kecepatan manual)
if (isset($_POST['simpan_transaksi_flow'])) {
    $pelanggan_id      = sanitize($_POST['pelanggan_id'] ?? '');
    $jenis_layanan     = sanitize($_POST['jenis_layanan'] ?? '');
    $berat_qty         = floatval($_POST['berat_qty'] ?? 0);
    $harga_layanan     = floatval($_POST['harga_layanan'] ?? 0);
    $catatan           = sanitize($_POST['catatan'] ?? '');
    $status_pembayaran = sanitize($_POST['status_pembayaran'] ?? 'belum_lunas');
    $metode_pembayaran = sanitize($_POST['metode_pembayaran'] ?? 'Tunai');

    if ($pelanggan_id == 'baru') {
        $nama_baru  = sanitize($_POST['nama_baru'] ?? '');
        $no_hp_baru = sanitize($_POST['no_hp_baru'] ?? '');
        $alamat_baru= sanitize($_POST['alamat_baru'] ?? '');

        if (!empty($nama_baru) && ctype_digit($no_hp_baru)) {
            $q_add_p = "INSERT INTO pelanggan (nama, no_hp, alamat) VALUES ('$nama_baru', '$no_hp_baru', '$alamat_baru')";
            if (mysqli_query($koneksi, $q_add_p)) {
                $pelanggan_id = mysqli_insert_id($koneksi);
            }
        }
    }

    if (empty($pelanggan_id) || $pelanggan_id == 'baru' || $berat_qty <= 0) {
        $msg_error = "Harap isi data pelanggan dan berat/jumlah pakaian dengan benar!";
    } else {
        $total_bayar = ($berat_qty * $harga_layanan);
        $kode_resi   = "HLA-" . date('Ymd') . "-" . strtoupper(substr(md5(time() . rand()), 0, 4));
        $tanggal_masuk = date('Y-m-d H:i:s');
        $status_laundry = 'sedang_diproses';

        $q_insert = "INSERT INTO transaksi 
                     (kode_resi, pelanggan_id, jenis_layanan, kecepatan, berat_qty, harga_per_unit, total_bayar, status_laundry, status_pembayaran, metode_pembayaran, catatan, tanggal_masuk) 
                     VALUES 
                     ('$kode_resi', '$pelanggan_id', '$jenis_layanan', '-', '$berat_qty', '$harga_layanan', '$total_bayar', '$status_laundry', '$status_pembayaran', '$metode_pembayaran', '$catatan', '$tanggal_masuk')";

        if (mysqli_query($koneksi, $q_insert)) {
            $msg_add = "Transaksi tersimpan! Resi: <b>" . $kode_resi . "</b>";
        } else {
            $msg_error = "Gagal menyimpan transaksi: " . mysqli_error($koneksi);
        }
    }
}

// Handle Ubah Status Cepat
if (isset($_POST['update_status_cepat'])) {
    $id_trx            = sanitize($_POST['id_transaksi'] ?? '');
    $status_laundry    = sanitize($_POST['status_laundry'] ?? '');
    $status_pembayaran = sanitize($_POST['status_pembayaran'] ?? 'belum_lunas');

    if ($status_laundry == 'selesai' && $status_pembayaran != 'lunas') {
        $msg_error = "Gagal! Transaksi wajib berstatus LUNAS sebelum diselesaikan.";
    } else {
        $q_up = "UPDATE transaksi SET status_laundry = '$status_laundry', status_pembayaran = '$status_pembayaran' WHERE id = '$id_trx'";
        if (mysqli_query($koneksi, $q_up)) {
            $msg_add = "Status & Pembayaran berhasil diperbarui!";
        } else {
            $msg_error = "Gagal memperbarui data: " . mysqli_error($koneksi);
        }
    }
}

$q_proses  = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM transaksi WHERE status_laundry IN ('sedang_diproses', 'dicuci', 'dikeringkan', 'disetrika', 'quality_check', 'dikemas', 'menunggu_diproses')");
$total_proses = mysqli_fetch_assoc($q_proses)['total'] ?? 0;

$q_siap  = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM transaksi WHERE status_laundry IN ('siap_diambil', 'siap_diantar')");
$total_siap = mysqli_fetch_assoc($q_siap)['total'] ?? 0;

$q_selesai = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM transaksi WHERE status_laundry = 'selesai'");
$total_selesai = mysqli_fetch_assoc($q_selesai)['total'] ?? 0;

$q_omset   = mysqli_query($koneksi, "SELECT SUM(total_bayar) as total FROM transaksi WHERE status_pembayaran = 'lunas'");
$total_omset = mysqli_fetch_assoc($q_omset)['total'] ?? 0;

function getTrxByStatusGroup($koneksi, $statuses) {
    $status_str = "'" . implode("','", $statuses) . "'";
    $q = mysqli_query($koneksi, "SELECT t.*, p.nama as nama_pelanggan, p.no_hp, p.alamat 
                                 FROM transaksi t 
                                 JOIN pelanggan p ON t.pelanggan_id = p.id 
                                 WHERE t.status_laundry IN ($status_str) 
                                 ORDER BY t.id DESC");
    $data = [];
    if ($q) {
        while ($row = mysqli_fetch_assoc($q)) { $data[] = $row; }
    }
    return $data;
}

$list_proses  = getTrxByStatusGroup($koneksi, ['sedang_diproses', 'dicuci', 'dikeringkan', 'disetrika', 'quality_check', 'dikemas', 'menunggu_diproses']);
$list_siap    = getTrxByStatusGroup($koneksi, ['siap_diambil', 'siap_diantar']);
$list_selesai = getTrxByStatusGroup($koneksi, ['selesai']);
$list_antrean_aktif = getTrxByStatusGroup($koneksi, ['sedang_diproses', 'dicuci', 'dikeringkan', 'disetrika', 'quality_check', 'dikemas', 'menunggu_diproses', 'siap_diambil', 'siap_diantar']);

$q_pelanggan = mysqli_query($koneksi, "SELECT * FROM pelanggan ORDER BY nama ASC");
$pelanggan_list = [];
if ($q_pelanggan) {
    while ($p = mysqli_fetch_assoc($q_pelanggan)) { $pelanggan_list[] = $p; }
}

$layanan_list = [
    ["nama" => "Cuci Basah", "harga" => 4000, "unit" => "kg", "icon" => "🧺", "waktu" => "1 hari"],
    ["nama" => "Cuci Kering", "harga" => 7000, "unit" => "kg", "icon" => "🧺", "waktu" => "2 hari"],
    ["nama" => "Cuci Setrika", "harga" => 10000, "unit" => "kg", "icon" => "👕", "waktu" => "2 hari"],
    ["nama" => "Cuci Ekspress", "harga" => 15000, "unit" => "kg", "icon" => "⚡", "waktu" => "8 jam"],
    ["nama" => "Selimut", "harga" => 20000, "unit" => "buah", "icon" => "🛌", "waktu" => "3 hari"],
    ["nama" => "Bed Cover", "harga" => 30000, "unit" => "buah", "icon" => "🛏️", "waktu" => "3 hari"]
];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Dashboard POS Admin - <?= htmlspecialchars($toko_info['nama_toko']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --bg-body: #f8fafc;
            --text-color: #1e293b;
            --card-bg: #ffffff;
            --card-border: #e2e8f0;
            --navbar-bg: linear-gradient(135deg, #1e293b 0%, #334155 100%);
            --table-head-bg: #f1f5f9;
            --table-text: #334155;
            --input-bg: #ffffff;
            --input-border: #cbd5e1;
            --input-text: #0f172a;
        }

        [data-bs-theme="dark"] {
            --bg-body: #0f172a;
            --text-color: #f8fafc;
            --card-bg: #1e293b;
            --card-border: #334155;
            --navbar-bg: linear-gradient(135deg, #020617 0%, #0f172a 100%);
            --table-head-bg: #334155;
            --table-text: #f8fafc;
            --input-bg: #0f172a;
            --input-border: #475569;
            --input-text: #f8fafc;
        }

        body { background-color: var(--bg-body); color: var(--text-color); font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; transition: background-color 0.3s ease, color 0.3s ease; }
        .custom-navbar { background: var(--navbar-bg); box-shadow: 0 4px 15px rgba(0,0,0,0.08); padding: 0.75rem 0; }
        
        .toast-container-custom { position: fixed; top: 25px; left: 50%; transform: translateX(-50%); z-index: 9999; }
        .bubble-toast { min-width: 320px; max-width: 480px; border-radius: 30px; padding: 12px 24px; color: #fff; font-weight: 500; box-shadow: 0 10px 30px rgba(0,0,0,0.22); display: flex; align-items: center; justify-content: center; gap: 10px; backdrop-filter: blur(10px); animation: slideDown 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards; }
        .toast-add { background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%); }
        .toast-err { background: linear-gradient(135deg, #fd7e14 0%, #d9480f 100%); }
        .bubble-toast.hide-toast { animation: slideUp 0.6s cubic-bezier(0.6, -0.28, 0.735, 0.045) forwards; }
        @keyframes slideDown { 0% { opacity: 0; transform: translateY(-50px) scale(0.85); } 100% { opacity: 1; transform: translateY(0) scale(1); } }
        @keyframes slideUp { 0% { opacity: 1; transform: translateY(0) scale(1); } 100% { opacity: 0; transform: translateY(-60px) scale(0.8); } }

        .dashboard-card { background-color: var(--card-bg); border: 1px solid var(--card-border); color: var(--text-color); }
        .stat-card { border: none; border-radius: 16px; cursor: pointer; transition: all 0.25s ease; position: relative; overflow: hidden; }
        .stat-card:hover { transform: translateY(-4px); box-shadow: 0 12px 24px rgba(0, 0, 0, 0.18) !important; }
        
        .action-pill-glass { background: rgba(255, 255, 255, 0.22); backdrop-filter: blur(8px); border: 1px solid rgba(255, 255, 255, 0.3); border-radius: 20px; padding: 4px 10px; font-size: 0.75rem; font-weight: 600; display: inline-flex; align-items: center; gap: 4px; }
        .service-card { border: 1px solid var(--card-border); border-radius: 16px; background: var(--card-bg); color: var(--text-color); transition: transform 0.2s ease, box-shadow 0.2s ease; cursor: pointer; }
        .service-card:hover { transform: translateY(-4px); box-shadow: 0 10px 20px rgba(13, 110, 253, 0.2) !important; border-color: #0d6efd; }
        
        .catatan-badge { background-color: rgba(255, 193, 7, 0.15); color: #d97706; border: 1px solid rgba(255, 193, 7, 0.3); border-radius: 6px; padding: 3px 8px; font-size: 0.75rem; font-weight: 600; display: inline-block; margin-top: 4px; }
        
        .btn-print-shadow { background: transparent; border: none; font-size: 1.25rem; padding: 4px 8px; border-radius: 8px; box-shadow: 0 2px 6px rgba(0,0,0,0.12); transition: all 0.2s ease; cursor: pointer; }
        .btn-print-shadow:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.22); transform: translateY(-2px); background: rgba(0,0,0,0.03); }

        .btn-gear-clean { background: transparent; border: none; font-size: 1.35rem; padding: 2px 6px; color: #fff; cursor: pointer; transition: transform 0.2s ease; }
        .btn-gear-clean:hover { transform: rotate(45deg); color: #cbd5e1; }
        .dropdown-toggle::after { display: none !important; }

        .form-control, .form-select { background-color: var(--input-bg); border-color: var(--input-border); color: var(--input-text); }
        .modal-content { background-color: var(--card-bg); color: var(--text-color); border: 1px solid var(--card-border); }
        .table { color: var(--text-color); }
        .table-light { background-color: var(--table-head-bg) !important; color: var(--table-text) !important; }

        @media print {
            body * { visibility: hidden; }
            #areaStrukPrint, #areaStrukPrint * { visibility: visible; }
            #areaStrukPrint { position: absolute; left: 0; top: 0; width: 58mm; padding: 0; margin: 0; font-family: 'Courier New', Courier, monospace; color: #000; }
        }
        .struk-preview { width: 280px; margin: 0 auto; background: #fff; color: #000; padding: 15px; border: 1px dashed #ccc; font-family: 'Courier New', Courier, monospace; font-size: 12px; }
    </style>
</head>
<body>

    <div class="toast-container-custom">
        <?php if ($msg_add != ""): ?>
            <div class="bubble-toast toast-add" id="hpToast"><span class="fs-5">🎉</span><div><?= $msg_add; ?></div></div>
        <?php endif; ?>
        <?php if ($msg_error != ""): ?>
            <div class="bubble-toast toast-err" id="hpToast"><span class="fs-5">⚠️</span><div><?= $msg_error; ?></div></div>
        <?php endif; ?>
    </div>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark custom-navbar sticky-top">
        <div class="container-fluid px-3 px-lg-5">
            <a class="navbar-brand d-flex align-items-center gap-2 fw-bold text-white fs-6 fs-lg-5 mb-1 mb-lg-0" href="dashboard.php">
                <span>🧺</span> <?= htmlspecialchars($toko_info['nama_toko']); ?> POS
            </a>
            
            <div class="d-flex align-items-center flex-wrap gap-2 justify-content-end">
                <span class="text-white-50 small d-none d-xl-inline">Halo, <strong class="text-white"><?= htmlspecialchars($_SESSION['admin_nama'] ?? 'Administrator'); ?></strong></span>
                
                <a href="riwayat.php" class="btn btn-outline-light btn-sm rounded-pill px-3 py-1">Riwayat Transaksi</a>
                
                <button type="button" class="btn btn-sm btn-dark rounded-pill border border-secondary px-2 py-1 d-flex align-items-center gap-1" id="themeToggleBtn" onclick="toggleTheme()">
                    <span id="themeIcon">🌙</span> <span id="themeText" class="d-none d-sm-inline">Mode</span>
                </button>

                <div class="dropdown">
                    <button class="btn-gear-clean dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Pengaturan Toko & Akun">
                        ⚙️
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0 rounded-3 mt-2">
                        <li>
                            <a class="dropdown-item py-2 d-flex align-items-center gap-2 fw-semibold" href="pelanggan.php">
                                👥 Kelola Data Pelanggan
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item py-2 d-flex align-items-center gap-2 fw-semibold" href="#" data-bs-toggle="modal" data-bs-target="#modalProfilToko">
                                🏪 Profil Toko & Struk
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item py-2 d-flex align-items-center gap-2 text-danger fw-bold" href="../logout.php">
                                🚪 Keluar / Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <!-- Konten Utama -->
    <div class="container-fluid px-4 px-lg-5 my-4">
        
        <!-- Statistik Operasional (3 Kolom Sejajar) -->
        <div class="row g-3 mb-3">
            <div class="col-12 col-md-4">
                <div class="card bg-warning text-dark p-3 shadow-sm stat-card" data-bs-toggle="modal" data-bs-target="#modalProses">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="fw-bold text-uppercase opacity-75 small">Sedang Diproses</span>
                        <div class="action-pill-glass text-dark"><span>Detail</span> →</div>
                    </div>
                    <h3 class="fw-bold mb-0 mt-1"><?= $total_proses; ?> <small class="fs-6 opacity-75 fw-normal">Pesanan</small></h3>
                </div>
            </div>

            <div class="col-12 col-md-4">
                <div class="card bg-info text-white p-3 shadow-sm stat-card" data-bs-toggle="modal" data-bs-target="#modalSiap">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="fw-bold text-uppercase opacity-90 small">Siap Diambil / Diantar</span>
                        <div class="action-pill-glass text-white"><span>Detail</span> →</div>
                    </div>
                    <h3 class="fw-bold mb-0 mt-1"><?= $total_siap; ?> <small class="fs-6 opacity-90 fw-normal">Pesanan</small></h3>
                </div>
            </div>

            <div class="col-12 col-md-4">
                <div class="card bg-success text-white p-3 shadow-sm stat-card" data-bs-toggle="modal" data-bs-target="#modalSelesai">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="fw-bold text-uppercase opacity-90 small">Selesai / Diambil</span>
                        <div class="action-pill-glass text-white"><span>Detail</span> →</div>
                    </div>
                    <h3 class="fw-bold mb-0 mt-1"><?= $total_selesai; ?> <small class="fs-6 opacity-90 fw-normal">Pesanan</small></h3>
                </div>
            </div>
        </div>

        <!-- BANNER LAPORAN KEUANGAN / OMSET TOKO -->
        <div class="card dashboard-card border shadow-sm rounded-4 p-4 mb-4">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="badge bg-success bg-opacity-10 text-success px-3 py-1 rounded-pill fw-semibold" style="font-size: 0.75rem;">💰 Laporan Keuangan</span>
                        <span class="text-muted small">Update Real-time</span>
                    </div>
                    <h5 class="fw-bold mb-1">Total Omset Toko (Lunas)</h5>
                    <p class="text-muted small mb-0">Akumulasi pendapatan bersih dari seluruh transaksi laundry yang sudah lunas.</p>
                </div>
                <div class="col-md-4 text-md-end mt-3 mt-md-0">
                    <h2 class="fw-bold text-success mb-0 fs-2">Rp <?= number_format($total_omset, 0, ',', '.'); ?></h2>
                </div>
            </div>
        </div>

        <!-- Layanan Kasir -->
        <div class="card dashboard-card border-0 shadow-sm p-4 rounded-4 mb-5">
            <div class="mb-3">
                <h4 class="fw-bold mb-0">Pilih Layanan Kasir</h4>
                <small class="opacity-75">Klik salah satu kartu layanan untuk langsung memulai transaksi baru.</small>
            </div>
            <div class="row g-3">
                <?php foreach($layanan_list as $l): ?>
                    <div class="col-6 col-md-4 col-lg-2">
                        <div class="card service-card text-center p-3 h-100" onclick="bukaModalFlow('<?= $l['nama']; ?>', <?= $l['harga']; ?>, '<?= $l['unit']; ?>')">
                            <div class="fs-1 mb-2"><?= $l['icon']; ?></div>
                            <h6 class="fw-bold mb-1"><?= $l['nama']; ?></h6>
                            <div class="text-primary fw-semibold small mb-1">Rp <?= number_format($l['harga'], 0, ',', '.'); ?>/<?= $l['unit']; ?></div>
                            <div class="text-muted" style="font-size: 0.75rem;">⏱️ <?= $l['waktu']; ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- SECTION: ANTREAN LAUNDRY AKTIF + LIVE SEARCH -->
        <div class="card dashboard-card border-0 shadow-sm p-4 rounded-4 mb-4">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-3">
                <div>
                    <h4 class="fw-bold mb-0">🧺 Antrean Laundry Aktif</h4>
                    <small class="opacity-75">Daftar pesanan yang sedang diproses atau siap diambil.</small>
                </div>
                
                <!-- Search Bar Otomatis (Live Search JS) -->
                <div style="max-width: 320px; width: 100%;">
                    <input type="text" id="inputCariAntrean" class="form-control form-control-sm rounded-pill px-3" placeholder="🔍 Ketik nama / resi / no hp..." onkeyup="filterAntreanAktif()">
                </div>
            </div>

            <?php if (count($list_antrean_aktif) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="tabelAntreanAktif">
                        <thead class="table-light">
                            <tr>
                                <th style="min-width: 200px;">Resi & Waktu</th>
                                <th style="min-width: 170px;">Pelanggan</th>
                                <th style="min-width: 200px;">Layanan & Catatan</th>
                                <th style="min-width: 110px;">Total</th>
                                <th style="min-width: 120px;">Status</th>
                                <th style="min-width: 320px;" class="text-center">Ubah Status & Pembayaran</th>
                                <th class="text-center">Cetak</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($list_antrean_aktif as $item): ?>
                                <tr class="row-antrean" data-search="<?= strtolower($item['kode_resi'] . ' ' . $item['nama_pelanggan'] . ' ' . $item['no_hp'] . ' ' . $item['jenis_layanan']); ?>">
                                    <td>
                                        <strong class="text-primary d-block"><?= $item['kode_resi']; ?></strong>
                                        <small class="opacity-75">🕒 <?= date('d/m/Y H:i', strtotime($item['tanggal_masuk'])); ?> WIB</small>
                                    </td>
                                    <td>
                                        <div class="fw-semibold"><?= htmlspecialchars($item['nama_pelanggan'] ?? ''); ?></div>
                                        <small class="opacity-75">(<?= $item['no_hp'] ?? '-'; ?>)</small>
                                    </td>
                                    <td>
                                        <div class="fw-semibold"><?= htmlspecialchars($item['jenis_layanan'] ?? ''); ?> (<?= $item['berat_qty'] ?? 0; ?> kg)</div>
                                        <?php if (!empty($item['catatan'])): ?>
                                            <div><span class="catatan-badge">📌 <?= htmlspecialchars($item['catatan']); ?></span></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="fw-bold text-nowrap fs-6">Rp <?= number_format($item['total_bayar'] ?? 0, 0, ',', '.'); ?></td>
                                    <td>
                                        <?php if (in_array($item['status_laundry'], ['siap_diambil','siap_diantar'])): ?>
                                            <span class="badge bg-info text-white px-2 py-1">✨ Siap Diambil</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark px-2 py-1">🧺 Diproses</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <form action="dashboard.php" method="POST" class="d-flex flex-column align-items-center gap-1 form-update-status">
                                            <input type="hidden" name="id_transaksi" value="<?= $item['id']; ?>">
                                            <div class="d-flex align-items-center gap-2 w-100">
                                                <select name="status_laundry" class="form-select form-select-sm fw-semibold select-status-laundry select-change" data-init="<?= $item['status_laundry']; ?>">
                                                    <?php if (in_array($item['status_laundry'], ['siap_diambil','siap_diantar'])): ?>
                                                        <option value="siap_diambil" selected>Siap Diambil</option>
                                                        <option value="selesai">Selesai (Sudah Diambil) ➔</option>
                                                    <?php else: ?>
                                                        <option value="sedang_diproses" selected>Sedang Diproses</option>
                                                        <option value="siap_diambil">Siap Diambil/Antar ➔</option>
                                                    <?php endif; ?>
                                                </select>
                                                <select name="status_pembayaran" class="form-select form-select-sm select-status-pembayaran select-change" data-init="<?= $item['status_pembayaran'] ?? 'belum_lunas'; ?>">
                                                    <option value="belum_lunas" <?= ($item['status_pembayaran'] ?? '') == 'belum_lunas' ? 'selected' : ''; ?>>Belum Lunas</option>
                                                    <option value="lunas" <?= ($item['status_pembayaran'] ?? '') == 'lunas' ? 'selected' : ''; ?>>Lunas</option>
                                                </select>
                                                <button type="submit" name="update_status_cepat" class="btn btn-sm btn-primary fw-bold px-3 btn-submit-action" disabled>Simpan</button>
                                            </div>
                                            <small class="text-danger fw-bold warn-lunas-text" style="display: none; font-size: 0.72rem;">⚠️ Wajib Pelunasan Sebelum Selesai!</small>
                                        </form>
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="btn-print-shadow" title="Cetak Struk Nota" onclick='cetakStruk(<?= json_encode($item); ?>)'>
                                            🖨️
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-4 rounded-4 border" style="background-color: rgba(0,0,0,0.02);">
                    <div class="fs-1 mb-2">🎉</div>
                    <h6 class="fw-bold mb-1">Tidak Ada Antrean Laundry Aktif</h6>
                    <small class="opacity-75">Semua cucian telah selesai dikerjakan.</small>
                </div>
            <?php endif; ?>
        </div>

    </div>

    <!-- MODAL PROFIL TOKO -->
    <div class="modal fade" id="modalProfilToko" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header bg-dark text-white rounded-top-4">
                    <h5 class="fw-bold mb-0">🏪 Pengaturan Profil Toko & Struk</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="dashboard.php" method="POST">
                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Nama Toko Laundry</label>
                            <input type="text" name="nama_toko" class="form-control" value="<?= htmlspecialchars($toko_info['nama_toko']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Nomor WhatsApp Toko</label>
                            <input type="text" name="no_wa" class="form-control" value="<?= htmlspecialchars($toko_info['no_wa']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Alamat Singkat Toko</label>
                            <textarea name="alamat_toko" class="form-control" rows="2" required><?= htmlspecialchars($toko_info['alamat_toko']); ?></textarea>
                        </div>
                        <div class="mb-2">
                            <label class="form-label fw-bold">Pesan Kaki Struk (Footer)</label>
                            <textarea name="pesan_struk" class="form-control" rows="2"><?= htmlspecialchars($toko_info['pesan_struk']); ?></textarea>
                        </div>
                    </div>
                    <div class="modal-footer bg-light rounded-bottom-4">
                        <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="simpan_profil_toko" class="btn btn-primary rounded-pill px-4 fw-bold">💾 Simpan Profil</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL CETAK STRUK -->
    <div class="modal fade" id="modalStruk" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header bg-dark text-white rounded-top-4">
                    <h5 class="fw-bold mb-0">🖨️ Cetak Struk Nota</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4 text-center">
                    <div id="areaStrukPrint" class="struk-preview shadow-sm text-start">
                        <div class="text-center fw-bold text-uppercase fs-6 mb-1"><?= htmlspecialchars($toko_info['nama_toko']); ?></div>
                        <div class="text-center small mb-2"><?= nl2br(htmlspecialchars($toko_info['alamat_toko'])); ?><br>WA: <?= htmlspecialchars($toko_info['no_wa']); ?></div>
                        <div class="border-top border-bottom border-dark py-1 my-2 small">
                            <div>Resi  : <strong id="sResi">-</strong></div>
                            <div>Tgl   : <span id="sTgl">-</span></div>
                            <div>Nama  : <span id="sNama">-</span></div>
                            <div>HP    : <span id="sHP">-</span></div>
                        </div>
                        <div class="small fw-bold mb-1">DETAIL PESANAN:</div>
                        <div class="d-flex justify-content-between small">
                            <span id="sLayanan">-</span>
                            <span id="sTotal">-</span>
                        </div>
                        <div id="sBoxCatatan" class="mt-2 small border-top border-secondary pt-1" style="display: none;">
                            <strong>Catatan:</strong> <span id="sCatatan">-</span>
                        </div>
                        <div class="border-top border-dark my-2 pt-1">
                            <div class="d-flex justify-content-between fw-bold">
                                <span>TOTAL BAYAR:</span>
                                <span id="sGrandTotal">Rp 0</span>
                            </div>
                            <div class="d-flex justify-content-between small mt-1">
                                <span>Metode   :</span>
                                <span id="sMetode">-</span>
                            </div>
                            <div class="d-flex justify-content-between small">
                                <span>Status   :</span>
                                <strong id="sStatusBayar">-</strong>
                            </div>
                        </div>
                        <div class="text-center small mt-3 border-top border-dashed pt-2">
                            *** TERIMA KASIH ***<br>
                            <?= nl2br(htmlspecialchars($toko_info['pesan_struk'])); ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light rounded-bottom-4 justify-content-center">
                    <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Tutup</button>
                    <button type="button" class="btn btn-primary rounded-pill px-4 fw-bold" onclick="window.print()">🖨️ Print Struk</button>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL LIST SEDANG DIPROSES -->
    <div class="modal fade" id="modalProses" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header bg-warning text-dark rounded-top-4">
                    <h5 class="fw-bold mb-0">🧺 Pesanan Sedang Diproses (<?= count($list_proses); ?>)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <?php if (count($list_proses) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Resi & Tanggal</th>
                                        <th>Pelanggan</th>
                                        <th>Layanan</th>
                                        <th>Total</th>
                                        <th class="text-center">Ubah Status</th>
                                        <th>Cetak</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($list_proses as $item): ?>
                                        <tr>
                                            <td>
                                                <strong class="text-primary d-block"><?= $item['kode_resi']; ?></strong>
                                                <small class="opacity-75">🕒 <?= date('d/m/Y H:i', strtotime($item['tanggal_masuk'])); ?> WIB</small>
                                            </td>
                                            <td>
                                                <div class="fw-semibold"><?= htmlspecialchars($item['nama_pelanggan'] ?? ''); ?></div>
                                                <small class="opacity-75">(<?= $item['no_hp'] ?? '-'; ?>)</small>
                                            </td>
                                            <td>
                                                <div class="fw-semibold"><?= htmlspecialchars($item['jenis_layanan'] ?? ''); ?> (<?= $item['berat_qty'] ?? 0; ?> kg)</div>
                                                <?php if (!empty($item['catatan'])): ?>
                                                    <div><span class="catatan-badge">📌 <?= htmlspecialchars($item['catatan']); ?></span></div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="fw-bold text-nowrap">Rp <?= number_format($item['total_bayar'] ?? 0, 0, ',', '.'); ?></td>
                                            <td>
                                                <form action="dashboard.php" method="POST" class="d-flex align-items-center gap-2 form-update-status">
                                                    <input type="hidden" name="id_transaksi" value="<?= $item['id']; ?>">
                                                    <select name="status_laundry" class="form-select form-select-sm select-change" data-init="sedang_diproses">
                                                        <option value="sedang_diproses" selected>Sedang Diproses</option>
                                                        <option value="siap_diambil">Siap Diambil/Antar ➔</option>
                                                    </select>
                                                    <select name="status_pembayaran" class="form-select form-select-sm select-change" data-init="<?= $item['status_pembayaran'] ?? 'belum_lunas'; ?>">
                                                        <option value="belum_lunas" <?= ($item['status_pembayaran'] ?? '') == 'belum_lunas' ? 'selected' : ''; ?>>Belum Lunas</option>
                                                        <option value="lunas" <?= ($item['status_pembayaran'] ?? '') == 'lunas' ? 'selected' : ''; ?>>Lunas</option>
                                                    </select>
                                                    <button type="submit" name="update_status_cepat" class="btn btn-sm btn-primary fw-bold px-3 btn-submit-action" disabled>Simpan</button>
                                                </form>
                                            </td>
                                            <td>
                                                <button type="button" class="btn-print-shadow" onclick='cetakStruk(<?= json_encode($item); ?>)'>🖨️</button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-center opacity-75 my-3">Tidak ada pesanan yang sedang diproses.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL LIST SIAP -->
    <div class="modal fade" id="modalSiap" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header bg-info text-white rounded-top-4">
                    <h5 class="fw-bold mb-0">✨ Pesanan Siap Diambil / Diantar (<?= count($list_siap); ?>)</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <?php if (count($list_siap) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Resi & Tanggal</th>
                                        <th>Pelanggan</th>
                                        <th>Layanan</th>
                                        <th>Total</th>
                                        <th class="text-center">Ubah Status</th>
                                        <th>Cetak</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($list_siap as $item): ?>
                                        <tr>
                                            <td>
                                                <strong class="text-primary d-block"><?= $item['kode_resi']; ?></strong>
                                                <small class="opacity-75">🕒 <?= date('d/m/Y H:i', strtotime($item['tanggal_masuk'])); ?> WIB</small>
                                            </td>
                                            <td>
                                                <div class="fw-semibold"><?= htmlspecialchars($item['nama_pelanggan'] ?? ''); ?></div>
                                                <small class="opacity-75">(<?= $item['no_hp'] ?? '-'; ?>)</small>
                                            </td>
                                            <td>
                                                <div class="fw-semibold"><?= htmlspecialchars($item['jenis_layanan'] ?? ''); ?> (<?= $item['berat_qty'] ?? 0; ?> kg)</div>
                                                <?php if (!empty($item['catatan'])): ?>
                                                    <div><span class="catatan-badge">📌 <?= htmlspecialchars($item['catatan']); ?></span></div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="fw-bold text-nowrap">Rp <?= number_format($item['total_bayar'] ?? 0, 0, ',', '.'); ?></td>
                                            <td>
                                                <form action="dashboard.php" method="POST" class="d-flex flex-column align-items-center gap-1 form-update-status">
                                                    <input type="hidden" name="id_transaksi" value="<?= $item['id']; ?>">
                                                    <div class="d-flex align-items-center gap-2 w-100">
                                                        <select name="status_laundry" class="form-select form-select-sm select-status-laundry select-change" data-init="siap_diambil">
                                                            <option value="siap_diambil" selected>Siap Diambil</option>
                                                            <option value="selesai">Selesai (Sudah Diambil) ➔</option>
                                                        </select>
                                                        <select name="status_pembayaran" class="form-select form-select-sm select-status-pembayaran select-change" data-init="<?= $item['status_pembayaran'] ?? 'belum_lunas'; ?>">
                                                            <option value="belum_lunas" <?= ($item['status_pembayaran'] ?? '') == 'belum_lunas' ? 'selected' : ''; ?>>Belum Lunas</option>
                                                            <option value="lunas" <?= ($item['status_pembayaran'] ?? '') == 'lunas' ? 'selected' : ''; ?>>Lunas</option>
                                                        </select>
                                                        <button type="submit" name="update_status_cepat" class="btn btn-sm btn-success fw-bold px-3 btn-submit-action" disabled>Simpan</button>
                                                    </div>
                                                    <small class="text-danger fw-bold warn-lunas-text" style="display: none; font-size: 0.72rem;">⚠️ Wajib Pelunasan Sebelum Selesai!</small>
                                                </form>
                                            </td>
                                            <td>
                                                <button type="button" class="btn-print-shadow" onclick='cetakStruk(<?= json_encode($item); ?>)'>🖨️</button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-center opacity-75 my-3">Tidak ada pesanan yang siap diambil.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL LIST SELESAI -->
    <div class="modal fade" id="modalSelesai" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header bg-success text-white rounded-top-4">
                    <h5 class="fw-bold mb-0">✅ Pesanan Selesai / Diambil (<?= count($list_selesai); ?>)</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <?php if (count($list_selesai) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Resi & Tanggal</th>
                                        <th>Pelanggan</th>
                                        <th>Layanan</th>
                                        <th>Total Harga</th>
                                        <th>Status Pembayaran</th>
                                        <th>Cetak</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($list_selesai as $item): ?>
                                        <tr>
                                            <td>
                                                <strong class="text-primary d-block"><?= $item['kode_resi']; ?></strong>
                                                <small class="opacity-75">🕒 <?= date('d/m/Y H:i', strtotime($item['tanggal_masuk'])); ?> WIB</small>
                                            </td>
                                            <td>
                                                <div class="fw-semibold"><?= htmlspecialchars($item['nama_pelanggan'] ?? ''); ?></div>
                                                <small class="opacity-75">(<?= $item['no_hp'] ?? '-'; ?>)</small>
                                            </td>
                                            <td>
                                                <div><?= htmlspecialchars($item['jenis_layanan'] ?? ''); ?> (<?= $item['berat_qty'] ?? 0; ?> kg)</div>
                                                <?php if (!empty($item['catatan'])): ?>
                                                    <div><span class="catatan-badge">📌 <?= htmlspecialchars($item['catatan']); ?></span></div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="fw-bold text-nowrap">Rp <?= number_format($item['total_bayar'] ?? 0, 0, ',', '.'); ?></td>
                                            <td>
                                                <?php if (($item['status_pembayaran'] ?? '') == 'lunas'): ?>
                                                    <span class="badge bg-success px-3 py-1">Lunas</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning text-dark px-3 py-1">Belum Lunas</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <button type="button" class="btn-print-shadow" onclick='cetakStruk(<?= json_encode($item); ?>)'>🖨️</button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-center opacity-75 my-3">Belum ada pesanan yang selesai.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL FORM KASIR (DIPERBARUI: Tanpa Pilihan Kecepatan, Tata Letak Lebih Longgar & Rapi) -->
    <div class="modal fade" id="modalFlow" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header bg-dark text-white rounded-top-4">
                    <h5 class="fw-bold mb-0" id="modalTitle">Form Kasir Transaksi</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="dashboard.php" method="POST">
                    <div class="modal-body p-4">
                        <input type="hidden" name="jenis_layanan" id="inputLayanan">
                        <input type="hidden" name="harga_layanan" id="inputHargaLayanan">

                        <div class="row g-4">
                            <!-- Data Pelanggan -->
                            <div class="col-md-12">
                                <label class="form-label fw-bold">1. Data Pelanggan</label>
                                <select name="pelanggan_id" id="selectPelanggan" class="form-select" onchange="togglePelangganBaru(this.value)" required>
                                    <option value="">-- Pilih Pelanggan --</option>
                                    <option value="baru" class="fw-bold text-primary">+ Pelanggan Baru</option>
                                    <?php foreach($pelanggan_list as $pl): ?>
                                        <option value="<?= $pl['id']; ?>"><?= htmlspecialchars($pl['nama']); ?> (<?= htmlspecialchars($pl['no_hp']); ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                                <div id="boxPelangganBaru" class="p-3 bg-light rounded-3 mt-2 border" style="display: none;">
                                    <small class="fw-bold text-primary d-block mb-2">Pendaftaran Pelanggan Baru</small>
                                    <div class="row g-2">
                                        <div class="col-md-4">
                                            <input type="text" name="nama_baru" class="form-control form-control-sm" placeholder="Nama Lengkap">
                                        </div>
                                        <div class="col-md-4">
                                            <input type="tel" name="no_hp_baru" class="form-control form-control-sm" placeholder="Nomor HP/WA" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                                        </div>
                                        <div class="col-md-4">
                                            <input type="text" name="alamat_baru" class="form-control form-control-sm" placeholder="Alamat Singkat">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Berat / Jumlah Pakaian -->
                            <div class="col-md-12">
                                <label class="form-label fw-bold">2. Berat / Jumlah Pakaian (<span id="unitSpan">kg</span>)</label>
                                <input type="number" step="0.1" name="berat_qty" id="inputBerat" class="form-control form-control-lg" placeholder="Masukan hasil timbangan..." required oninput="hitungTotalFlow()">
                            </div>

                            <!-- Catatan Pakaian -->
                            <div class="col-12">
                                <label class="form-label fw-bold">3. Catatan Pakaian (Opsional)</label>
                                <textarea name="catatan" class="form-control" rows="2" placeholder="Contoh: Baju putih dipisah..."></textarea>
                            </div>

                            <!-- Pembayaran & Kalkulasi Total -->
                            <div class="col-md-6">
                                <label class="form-label fw-bold">4. Pembayaran</label>
                                <select name="status_pembayaran" class="form-select mb-2" required>
                                    <option value="lunas">Lunas (Bayar Sekarang)</option>
                                    <option value="belum_lunas">Belum Lunas (Bayar Nanti)</option>
                                </select>
                                <select name="metode_pembayaran" class="form-select" required>
                                    <option value="Tunai">Tunai / Cash</option>
                                    <option value="QRIS">QRIS / E-Wallet</option>
                                    <option value="Transfer Bank">Transfer Bank</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <div class="p-3 bg-primary bg-opacity-10 border border-primary border-opacity-25 rounded-3 text-center h-100 d-flex flex-column justify-content-center">
                                    <small class="text-uppercase fw-bold text-primary opacity-75">Total Kalkulasi</small>
                                    <h2 class="fw-bold text-primary mb-0" id="totalDisplay">Rp 0</h2>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-body-tertiary rounded-bottom-4">
                        <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="simpan_transaksi_flow" class="btn btn-primary rounded-pill px-4 fw-bold">💾 Simpan Pesanan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let hargaBase = 0;
        function toggleTheme() {
            const htmlTag = document.documentElement;
            const currentTheme = htmlTag.getAttribute('data-bs-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            htmlTag.setAttribute('data-bs-theme', newTheme);
            localStorage.setItem('hola_theme', newTheme);
            updateThemeButton(newTheme);
        }
        function updateThemeButton(theme) {
            const icon = document.getElementById('themeIcon');
            const text = document.getElementById('themeText');
            if (theme === 'dark') { icon.innerText = '☀️'; if(text) text.innerText = 'Terang'; }
            else { icon.innerText = '🌙'; if(text) text.innerText = 'Malam'; }
        }
        (function() {
            const savedTheme = localStorage.getItem('hola_theme') || 'light';
            document.documentElement.setAttribute('data-bs-theme', savedTheme);
            document.addEventListener('DOMContentLoaded', () => updateThemeButton(savedTheme));
        })();

        // Fungsi Live Search Real-Time
        function filterAntreanAktif() {
            let input = document.getElementById('inputCariAntrean').value.toLowerCase();
            let rows = document.querySelectorAll('.row-antrean');

            rows.forEach(row => {
                let textData = row.getAttribute('data-search');
                if (textData.includes(input)) {
                    row.style.display = ""; 
                } else {
                    row.style.display = "none"; 
                }
            });
        }

        function cetakStruk(item) {
            document.getElementById('sResi').innerText = item.kode_resi;
            document.getElementById('sTgl').innerText = item.tanggal_masuk;
            document.getElementById('sNama').innerText = item.nama_pelanggan;
            document.getElementById('sHP').innerText = item.no_hp || '-';
            document.getElementById('sLayanan').innerText = item.jenis_layanan + ' (' + item.berat_qty + ' kg)';
            document.getElementById('sTotal').innerText = 'Rp ' + parseInt(item.total_bayar).toLocaleString('id-ID');
            document.getElementById('sGrandTotal').innerText = 'Rp ' + parseInt(item.total_bayar).toLocaleString('id-ID');
            document.getElementById('sMetode').innerText = item.metode_pembayaran || 'Tunai';
            
            let isLunas = (item.status_pembayaran === 'lunas');
            let statusElem = document.getElementById('sStatusBayar');
            statusElem.innerText = isLunas ? 'LUNAS' : 'BELUM LUNAS';
            statusElem.style.color = isLunas ? 'green' : 'red';

            let boxCatatan = document.getElementById('sBoxCatatan');
            if (item.catatan && item.catatan.trim() !== '') {
                document.getElementById('sCatatan').innerText = item.catatan;
                boxCatatan.style.display = 'block';
            } else {
                boxCatatan.style.display = 'none';
            }

            let modal = new bootstrap.Modal(document.getElementById('modalStruk'));
            modal.show();
        }

        function bukaModalFlow(nama, harga, unit) {
            hargaBase = harga;
            document.getElementById('modalTitle').innerText = 'Kasir - ' + nama;
            document.getElementById('inputLayanan').value = nama;
            document.getElementById('inputHargaLayanan').value = harga;
            document.getElementById('unitSpan').innerText = unit;
            document.getElementById('inputBerat').value = '';
            document.getElementById('totalDisplay').innerText = 'Rp 0';
            document.getElementById('selectPelanggan').value = '';
            document.getElementById('boxPelangganBaru').style.display = 'none';
            new bootstrap.Modal(document.getElementById('modalFlow')).show();
        }

        function togglePelangganBaru(val) {
            document.getElementById('boxPelangganBaru').style.display = (val === 'baru') ? 'block' : 'none';
        }

        function hitungTotalFlow() {
            let qty = parseFloat(document.getElementById('inputBerat').value) || 0;
            let total = (qty * hargaBase);
            document.getElementById('totalDisplay').innerText = 'Rp ' + total.toLocaleString('id-ID');
        }

        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.form-update-status').forEach(form => {
                const selects = form.querySelectorAll('.select-change');
                const submitBtn = form.querySelector('.btn-submit-action');
                const statusLaundrySelect = form.querySelector('.select-status-laundry');
                const statusPembayaranSelect = form.querySelector('.select-status-pembayaran');
                const warnText = form.querySelector('.warn-lunas-text');

                function checkValidity() {
                    let hasChanged = false;
                    selects.forEach(s => { if (s.value !== s.getAttribute('data-init')) hasChanged = true; });
                    let isSelesaiNotLunas = false;
                    if (statusLaundrySelect && statusPembayaranSelect) {
                        if (statusLaundrySelect.value === 'selesai' && statusPembayaranSelect.value === 'belum_lunas') isSelesaiNotLunas = true;
                    }
                    if (warnText) warnText.style.display = isSelesaiNotLunas ? 'block' : 'none';
                    submitBtn.disabled = !hasChanged || isSelesaiNotLunas;
                }
                selects.forEach(select => select.addEventListener('change', checkValidity));
            });
        });

        const toast = document.getElementById('hpToast');
        if (toast) {
            setTimeout(() => { toast.classList.add('hide-toast'); }, 2500);
            setTimeout(() => { toast.remove(); }, 3100);
        }
    </script>
</body>
</html>