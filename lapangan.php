<?php
include 'koneksi.php';
$data = mysqli_query($conn, "SELECT * FROM lapangan");
?>

<h2>Data Lapangan</h2>
<a href="tambah.php">+ Tambah Data</a>
<table border="1" cellpadding="10">
    <tr>
        <th>Id Lapangan</th>
        <th>Nama lapangan</th>
        <th>Jenis Lantai</th>
        <th>Harga per jam</th>
        <th>Jam Buka</th>
        <th>Jam Tutup</th>
        <th>Status</th>
        <th>Aksi</th>
    </tr>

    <?php $no = 1; ?>
    <?php while($row = mysqli_fetch_assoc($data)) : ?>
    <tr>
        <td><?= $no++; ?></td>
        <td><?= $row['id_lapangan']; ?></td>
        <td><?= $row['nama_lapangan']; ?></td>
        <td><?= $row['jenis_lantai']; ?></td>
        <td><?= $row['harga_per_jam']; ?></td>
        <td><?= $row['jam_buka']; ?></td>
        <td><?= $row['jam_tutup']; ?></td>
        <td><?= $row['status']; ?></td>
        <td>
            <a href="edit.php?id=<?= $row['id']; ?>">Edit</a>
            <a href="hapus.php?id=<?= $row['id']; ?>" onclick="return confirm('Yakin?')">Hapus</a>
        </td>
    </tr>
    <?php endwhile; ?>
</table>