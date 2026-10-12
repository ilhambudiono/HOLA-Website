<?php
session_start();
date_default_timezone_set('Asia/Jakarta');

require_once '../config/koneksi.php';

if (!function_exists('sanitize')) {
    function sanitize($data) {
        global $koneksi;
        $data = $data ?? '';
        return mysqli_real_escape_string($koneksi, trim(htmlspecialchars($data, ENT_QUOTES, 'UTF-8')));
    }
}

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: ../login.php");
    exit();
}

$q_toko = mysqli_query($koneksi, "SELECT * FROM pengaturan_toko WHERE id = 1");
$toko_info = mysqli_fetch_assoc($q_toko) ?? [
    'nama_toko'   => 'HOLA LAUNDRY',
    'alamat_toko' => 'Jl. Raya Vokasi No. 123, Surabaya',
    'no_wa'       => '0812-3456-7890',
    'pesan_struk' => 'Simpan struk ini sebagai bukti pengambilan.'
];

$search = sanitize($_GET['search'] ?? '');
$where_clause = "";
if (!empty($search)) {
    $where_clause = "WHERE t.kode_resi LIKE '%$search%' OR p.nama LIKE '%$search%' OR p.no_hp LIKE '%$search%'";
}

$q_trx = mysqli_query($koneksi, "SELECT t.*, p.nama as nama_pelanggan, p.no_hp, p.alamat 
                                 FROM transaksi t 
                                 JOIN pelanggan p ON t.pelanggan_id = p.id 
                                 $where_clause 
                                 ORDER BY t.id DESC");
$list_transaksi = [];
if ($q_trx) {
    while ($row = mysqli_fetch_assoc($q_trx)) { $list_transaksi[] = $row; }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Riwayat Transaksi - <?= htmlspecialchars($toko_info['nama_toko']); ?></title>
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
            --table-text: #f1f5f9;
            --input-bg: #0f172a;
            --input-border: #475569;
            --input-text: #f8fafc;
        }

        body { background-color: var(--bg-body); color: var(--text-color); font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; transition: background-color 0.3s ease, color 0.3s ease; }
        .custom-navbar { background: var(--navbar-bg); box-shadow: 0 4px 15px rgba(0,0,0,0.08); padding: 0.75rem 0; }
        .dashboard-card { background-color: var(--card-bg); border: 1px solid var(--card-border); color: var(--text-color); }
        .catatan-badge { background-color: rgba(255, 193, 7, 0.15); color: #d97706; border: 1px solid rgba(255, 193, 7, 0.3); border-radius: 6px; padding: 3px 8px; font-size: 0.75rem; font-weight: 600; display: inline-block; }
        
        .btn-print-shadow { background: transparent; border: none; font-size: 1.25rem; padding: 4px 8px; border-radius: 8px; box-shadow: 0 2px 6px rgba(0,0,0,0.12); transition: all 0.2s ease; cursor: pointer; }
        .btn-print-shadow:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.22); transform: translateY(-2px); background: rgba(0,0,0,0.03); }

        .form-control, .form-select { background-color: var(--input-bg); border-color: var(--input-border); color: var(--input-text); }
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

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark custom-navbar sticky-top">
        <div class="container-fluid px-3 px-lg-5">
            <a class="navbar-brand d-flex align-items-center gap-2 fw-bold text-white fs-6 fs-lg-5 mb-1 mb-lg-0" href="dashboard.php">
                <span>🧺</span> <?= htmlspecialchars($toko_info['nama_toko']); ?> POS
            </a>
            <div class="d-flex align-items-center flex-wrap gap-2 justify-content-end">
                <span class="text-white-50 small d-none d-xl-inline">Halo, <strong class="text-white"><?= htmlspecialchars($_SESSION['admin_nama'] ?? 'Administrator'); ?></strong></span>
                
                <a href="dashboard.php" class="btn btn-outline-light btn-sm rounded-pill px-3 py-1">← Dashboard</a>
                
                <button type="button" class="btn btn-sm btn-dark rounded-pill border border-secondary px-2 py-1 d-flex align-items-center gap-1" id="themeToggleBtn" onclick="toggleTheme()">
                    <span id="themeIcon">🌙</span> <span id="themeText" class="d-none d-sm-inline">Mode</span>
                </button>
            </div>
        </div>
    </nav>

    <!-- Konten Utama -->
    <div class="container-fluid px-4 px-lg-5 my-4">
        
        <div class="mb-4">
            <h3 class="fw-bold mb-1">Riwayat & Laporan Penjualan</h3>
            <p class="opacity-75 small mb-0">Rekapitulasi seluruh pesanan laundry yang telah tercatat di sistem.</p>
        </div>

        <div class="card dashboard-card border-0 shadow-sm p-4 rounded-4">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
                <h5 class="fw-bold mb-0">Daftar Transaksi (<?= count($list_transaksi); ?>)</h5>
                
                <form action="riwayat.php" method="GET" class="d-flex gap-2" style="max-width: 350px; width: 100%;">
                    <input type="text" name="search" class="form-control form-control-sm rounded-pill px-3" placeholder="🔍 Cari Resi / Nama / No. HP..." value="<?= htmlspecialchars($search); ?>">
                    <?php if(!empty($search)): ?>
                        <a href="riwayat.php" class="btn btn-sm btn-secondary rounded-pill px-3">Reset</a>
                    <?php endif; ?>
                </form>
            </div>

            <?php if (count($list_transaksi) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Resi & Waktu</th>
                                <th>Pelanggan</th>
                                <th>Layanan</th>
                                <th>Catatan</th>
                                <th>Total</th>
                                <th>Metode</th>
                                <th>Bayar</th>
                                <th>Status</th>
                                <th class="text-center">Cetak</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no = 1; foreach($list_transaksi as $item): ?>
                                <tr>
                                    <td class="fw-bold opacity-75"><?= $no++; ?></td>
                                    <td>
                                        <strong class="text-primary d-block"><?= $item['kode_resi']; ?></strong>
                                        <small class="opacity-75">🕒 <?= date('d/m/Y H:i', strtotime($item['tanggal_masuk'])); ?> WIB</small>
                                    </td>
                                    <td>
                                        <div class="fw-semibold"><?= htmlspecialchars($item['nama_pelanggan'] ?? ''); ?></div>
                                        <small class="opacity-75">(<?= htmlspecialchars($item['no_hp'] ?? '-'); ?>)</small>
                                    </td>
                                    <td>
                                        <div class="fw-semibold"><?= htmlspecialchars($item['jenis_layanan'] ?? ''); ?> (<?= $item['berat_qty'] ?? 0; ?> kg)</div>
                                    </td>
                                    <td>
                                        <?php if (!empty($item['catatan'])): ?>
                                            <span class="catatan-badge">📌 <?= htmlspecialchars($item['catatan']); ?></span>
                                        <?php else: ?>
                                            <span class="opacity-50">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="fw-bold text-nowrap">Rp <?= number_format($item['total_bayar'] ?? 0, 0, ',', '.'); ?></td>
                                    <td><span class="badge bg-light text-dark border px-2 py-1"><?= htmlspecialchars($item['metode_pembayaran'] ?? 'Tunai'); ?></span></td>
                                    <td>
                                        <?php if (($item['status_pembayaran'] ?? '') == 'lunas'): ?>
                                            <span class="badge bg-success bg-opacity-10 text-success border border-success px-2 py-1 fw-bold">Lunas</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning bg-opacity-10 text-dark border border-warning px-2 py-1 fw-bold">Belum Lunas</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($item['status_laundry'] == 'selesai'): ?>
                                            <span class="badge bg-success px-2 py-1">Selesai</span>
                                        <?php elseif (in_array($item['status_laundry'], ['siap_diambil', 'siap_diantar'])): ?>
                                            <span class="badge bg-info text-white px-2 py-1">Siap</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark px-2 py-1">Proses</span>
                                        <?php endif; ?>
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
                <div class="text-center py-5 rounded-4 border" style="background-color: rgba(0,0,0,0.02);">
                    <div class="fs-1 mb-2">🔍</div>
                    <h6 class="fw-bold mb-1">Tidak Ada Data Transaksi</h6>
                    <small class="opacity-75">Tidak ditemukan transaksi yang sesuai.</small>
                </div>
            <?php endif; ?>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
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

            new bootstrap.Modal(document.getElementById('modalStruk')).show();
        }
    </script>
</body>
</html>