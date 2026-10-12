<?php
session_start();
require_once '../config/koneksi.php';

if (!function_exists('sanitize')) {
    function sanitize($data) {
        global $koneksi;
        return mysqli_real_escape_string($koneksi, trim(htmlspecialchars($data)));
    }
}

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: ../login.php");
    exit();
}

$msg_del   = "";
$msg_error = "";

// Proses Hapus Pelanggan
if (isset($_GET['hapus'])) {
    $id_hapus = sanitize($_GET['hapus']);
    
    $q_get_name = mysqli_query($koneksi, "SELECT nama FROM pelanggan WHERE id = '$id_hapus'");
    $d_name = mysqli_fetch_assoc($q_get_name);
    $nama_dihapus = $d_name['nama'] ?? 'Pelanggan';

    $q_del = "DELETE FROM pelanggan WHERE id = '$id_hapus'";
    if (mysqli_query($koneksi, $q_del)) {
        $msg_del = "Data <b>" . htmlspecialchars($nama_dihapus) . "</b> beserta riwayatnya berhasil dihapus!";
    } else {
        $msg_error = "Gagal menghapus pelanggan: " . mysqli_error($koneksi);
    }
}

// Ambil Seluruh Data Pelanggan
$pelanggan = mysqli_query($koneksi, "SELECT p.*, COUNT(t.id) as total_transaksi 
                                      FROM pelanggan p 
                                      LEFT JOIN transaksi t ON p.id = t.pelanggan_id 
                                      GROUP BY p.id 
                                      ORDER BY p.id DESC");
$total_pelanggan = mysqli_num_rows($pelanggan);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Kelola Data Pelanggan - HOLA Laundry</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8fafc; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .custom-navbar { background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); }
        
        /* Toast Floating */
        .toast-container-custom { position: fixed; top: 25px; left: 50%; transform: translateX(-50%); z-index: 9999; }
        .bubble-toast { min-width: 300px; max-width: 450px; border-radius: 30px; padding: 12px 24px; color: #fff; font-weight: 500; box-shadow: 0 10px 30px rgba(0,0,0,0.22); display: flex; align-items: center; justify-content: center; gap: 12px; backdrop-filter: blur(10px); animation: slideDown 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards; }
        .toast-del { background: linear-gradient(135deg, #dc3545 0%, #b02a37 100%); }
        .toast-err { background: linear-gradient(135deg, #fd7e14 0%, #d9480f 100%); }
        .bubble-toast.hide-toast { animation: slideUp 0.6s cubic-bezier(0.6, -0.28, 0.735, 0.045) forwards; }
        @keyframes slideDown { 0% { opacity: 0; transform: translateY(-50px) scale(0.85); } 100% { opacity: 1; transform: translateY(0) scale(1); } }
        @keyframes slideUp { 0% { opacity: 1; transform: translateY(0) scale(1); } 100% { opacity: 0; transform: translateY(-60px) scale(0.8); } }

        /* Delete Button */
        .btn-delete-action { display: inline-flex; align-items: center; gap: 6px; background-color: #fff0f1; color: #dc3545; border: 1px solid #fecdd3; padding: 6px 14px; border-radius: 20px; font-size: 0.825rem; font-weight: 600; text-decoration: none; transition: all 0.2s ease-in-out; }
        .btn-delete-action:hover { background-color: #dc3545; color: #ffffff; border-color: #dc3545; box-shadow: 0 4px 12px rgba(220, 53, 69, 0.25); transform: translateY(-1px); }
    </style>
</head>
<body>

    <!-- Toast Floating Notification -->
    <div class="toast-container-custom">
        <?php if ($msg_del != ""): ?>
            <div class="bubble-toast toast-del" id="hpToast"><span class="fs-5">🗑️</span><div><?= $msg_del; ?></div></div>
        <?php endif; ?>
        <?php if ($msg_error != ""): ?>
            <div class="bubble-toast toast-err" id="hpToast"><span class="fs-5">⚠️</span><div><?= $msg_error; ?></div></div>
        <?php endif; ?>
    </div>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark custom-navbar">
        <div class="container">
            <a class="navbar-brand fw-bold" href="dashboard.php">🧺 HOLA POS Admin</a>
            <a href="dashboard.php" class="btn btn-outline-light btn-sm rounded-pill px-3">← Kembali ke Dashboard</a>
        </div>
    </nav>

    <div class="container my-4">
        <!-- Header & Search Bar -->
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
            <div>
                <h3 class="fw-bold mb-1">Data Pelanggan HOLA</h3>
                <p class="text-muted mb-0">Total <strong class="text-primary"><?= $total_pelanggan; ?> pelanggan</strong> terdaftar di sistem HOLA Laundry.</p>
            </div>
            
            <div class="d-flex gap-2" style="max-width: 350px; width: 100%;">
                <input type="text" id="searchInput" class="form-control rounded-pill px-3 shadow-sm" placeholder="🔍 Cari nama / no. HP..." onkeyup="cariPelanggan()">
            </div>
        </div>

        <!-- Tabel Pelanggan Full Width -->
        <div class="card border-0 shadow-sm p-4 rounded-4">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="tabelPelanggan">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 50px;">#</th>
                            <th>Nama Lengkap</th>
                            <th>Nomor HP / WhatsApp</th>
                            <th>Alamat</th>
                            <th class="text-center">Total Transaksi</th>
                            <th class="text-center" style="width: 120px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($total_pelanggan > 0): ?>
                            <?php $no=1; while($row = mysqli_fetch_assoc($pelanggan)): ?>
                                <tr>
                                    <td><?= $no++; ?></td>
                                    <td class="fw-bold text-dark"><?= htmlspecialchars($row['nama']); ?></td>
                                    <td>
                                        <span class="badge bg-light text-dark border px-2 py-1 fs-6 fw-normal">
                                            📞 <?= htmlspecialchars($row['no_hp']); ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($row['alamat'] ?? '-'); ?></td>
                                    <td class="text-center">
                                        <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-1 rounded-pill fw-bold">
                                            <?= $row['total_transaksi']; ?> Pesanan
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <a href="pelanggan.php?hapus=<?= $row['id']; ?>" class="btn-delete-action" onclick="return confirm('Apakah kamu yakin ingin menghapus pelanggan ini? Semua riwayat transaksinya akan ikut terhapus!');">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="currentColor" viewBox="0 0 16 16"><path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0V6z"/><path fill-rule="evenodd" d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1v1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4H4.118zM2.5 3V2h11v1h-11z"/></svg>
                                            <span>Hapus</span>
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">Belum ada data pelanggan registered.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        // Fitur Pencarian Real-Time
        function cariPelanggan() {
            let input = document.getElementById("searchInput").value.toLowerCase();
            let rows = document.querySelectorAll("#tabelPelanggan tbody tr");
            
            rows.forEach(row => {
                let text = row.innerText.toLowerCase();
                row.style.display = text.includes(input) ? "" : "none";
            });
        }

        // Clean URL parameter
        if (window.history.replaceState && window.location.search.includes('hapus=')) {
            window.history.replaceState(null, null, window.location.pathname);
        }

        // Toast auto hide
        const toast = document.getElementById('hpToast');
        if (toast) {
            setTimeout(() => { toast.classList.add('hide-toast'); }, 2500);
            setTimeout(() => { toast.remove(); }, 3100);
        }
    </script>
</body>
</html>