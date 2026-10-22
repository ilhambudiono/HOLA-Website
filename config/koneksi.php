<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$host = "localhost";
$user = "root";
$pass = "";
$db   = "hola_laundry";

$koneksi = mysqli_connect($host, $user, $pass, $db);

if (!$koneksi) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}

if (!function_exists('sanitize')) {
    function sanitize($data) {
        global $koneksi;
        return mysqli_real_escape_string($koneksi, trim(htmlspecialchars($data)));
    }
}
?>



