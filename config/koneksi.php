<?php
// Konfigurasi Database
$host = "localhost";
$user = "root";     // Sesuaikan dengan username database Anda
$pass = "";         // Sesuaikan dengan password database Anda
$db   = "hola_laundry";

$koneksi = mysqli_connect($host, $user, $pass, $db);

if (!$koneksi) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}
?>