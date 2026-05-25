<?php
require 'koneksi.php';

// Create mata_kuliah table
$sql = "CREATE TABLE IF NOT EXISTS mata_kuliah (
    id_matkul INT AUTO_INCREMENT PRIMARY KEY,
    nama_matkul VARCHAR(255) NOT NULL UNIQUE,
    kode_matkul VARCHAR(50),
    dibuat_pada TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($sql) === TRUE) {
    echo "✓ Tabel mata_kuliah berhasil dibuat<br>";
} else {
    echo "✗ Error membuat tabel: " . $conn->error . "<br>";
}

// Check if mata_kuliah table has data
$check = $conn->query("SELECT COUNT(*) as total FROM mata_kuliah");
$result = $check->fetch_assoc();

if ($result['total'] == 0) {
    echo "Tabel kosong. Silakan tambahkan data mata kuliah melalui admin panel.<br>";
} else {
    echo "Tabel sudah memiliki " . $result['total'] . " data mata kuliah.<br>";
}
?>
