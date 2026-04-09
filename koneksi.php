<?php
$conn = mysqli_connect("localhost", "root", "", "reservasi_badminton1");

if (!$conn) {
    die("Koneksi gagal: " . mysqli_connect_error());
}
?>