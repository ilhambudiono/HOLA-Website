<?php
require_once 'config/koneksi.php';

$password_hash = password_hash('admin123', PASSWORD_BCRYPT);

// Hapus admin lama & buat ulang
mysqli_query($koneksi, "DELETE FROM users WHERE username='admin'");
$query = "INSERT INTO users (username, password, nama_lengkap) VALUES ('admin', '$password_hash', 'Administrator HOLA')";

if (mysqli_query($koneksi, $query)) {
    echo "<h3>Akun admin berhasil di-reset!</h3>";
    echo "<p>Username: <b>admin</b><br>Password: <b>admin123</b></p>";
    echo "<a href='login.php'>Klik di sini untuk Login</a>";
} else {
    echo "Gagal mereset admin: " . mysqli_error($koneksi);
}
?>