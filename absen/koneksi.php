<?php
$host     = "localhost";
$username = "root"; // Sesuaikan dengan username database Anda
$password = "";     // Sesuaikan dengan password database Anda
$database = "absensi_db";

date_default_timezone_set('Asia/Jakarta');
mysqli_report(MYSQLI_REPORT_OFF);

$conn = new mysqli($host, $username, $password);

if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

if (!$conn->query("CREATE DATABASE IF NOT EXISTS `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci")) {
    die("Gagal membuat database: " . $conn->error);
}

if (!$conn->select_db($database)) {
    die("Gagal memilih database: " . $conn->error);
}

if (!$conn->set_charset("utf8mb4")) {
    die("Gagal mengatur charset database: " . $conn->error);
}

if (!function_exists('ensure_table')) {
    function ensure_table(mysqli $conn, string $sql): void
    {
        if (!$conn->query($sql)) {
            die("Gagal menyiapkan tabel: " . $conn->error);
        }
    }
}

if (!function_exists('ensure_column')) {
    function ensure_column(mysqli $conn, string $table, string $column, string $definition): void
    {
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $column = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
        $result = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");

        if ($result && $result->num_rows === 0) {
            if (!$conn->query("ALTER TABLE `$table` ADD `$column` $definition")) {
                die("Gagal memperbarui tabel `$table`: " . $conn->error);
            }
        }
    }
}

ensure_table($conn, "CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'user') NOT NULL DEFAULT 'user',
    nim VARCHAR(50) NULL,
    dibuat_pada TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

ensure_table($conn, "CREATE TABLE IF NOT EXISTS mata_kuliah (
    id_matkul INT AUTO_INCREMENT PRIMARY KEY,
    nama_matkul VARCHAR(255) NOT NULL UNIQUE,
    kode_matkul VARCHAR(50),
    dibuat_pada TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

ensure_table($conn, "CREATE TABLE IF NOT EXISTS sesi_absensi (
    id_sesi VARCHAR(80) PRIMARY KEY,
    nama_kegiatan VARCHAR(255) NOT NULL,
    mata_kuliah VARCHAR(255) NULL,
    lokasi_label VARCHAR(255) NULL,
    lokasi_lat DOUBLE NULL,
    lokasi_lng DOUBLE NULL,
    radius_meter INT NOT NULL DEFAULT 100,
    tanggal DATE NOT NULL,
    waktu_mulai TIME NOT NULL,
    waktu_selesai TIME NOT NULL,
    dibuat_pada TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

ensure_table($conn, "CREATE TABLE IF NOT EXISTS absensi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_sesi VARCHAR(80) NOT NULL,
    nama_mahasiswa VARCHAR(100) NOT NULL,
    waktu_scan TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    keterangan VARCHAR(20) NOT NULL DEFAULT 'Hadir',
    latitude DOUBLE NULL,
    longitude DOUBLE NULL,
    akurasi_meter DOUBLE NULL,
    jarak_meter DOUBLE NULL,
    status_lokasi VARCHAR(30) NULL,
    UNIQUE KEY uniq_absensi_sesi_mahasiswa (id_sesi, nama_mahasiswa),
    INDEX idx_absensi_sesi (id_sesi)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

ensure_column($conn, 'sesi_absensi', 'mata_kuliah', "VARCHAR(255) NULL AFTER nama_kegiatan");
ensure_column($conn, 'sesi_absensi', 'lokasi_label', "VARCHAR(255) NULL AFTER mata_kuliah");
ensure_column($conn, 'sesi_absensi', 'lokasi_lat', "DOUBLE NULL AFTER lokasi_label");
ensure_column($conn, 'sesi_absensi', 'lokasi_lng', "DOUBLE NULL AFTER lokasi_lat");
ensure_column($conn, 'sesi_absensi', 'radius_meter', "INT NOT NULL DEFAULT 100 AFTER lokasi_lng");
ensure_column($conn, 'sesi_absensi', 'dibuat_pada', "TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
ensure_column($conn, 'users', 'nim', "VARCHAR(50) NULL");
ensure_column($conn, 'absensi', 'latitude', "DOUBLE NULL AFTER keterangan");
ensure_column($conn, 'absensi', 'longitude', "DOUBLE NULL AFTER latitude");
ensure_column($conn, 'absensi', 'akurasi_meter', "DOUBLE NULL AFTER longitude");
ensure_column($conn, 'absensi', 'jarak_meter', "DOUBLE NULL AFTER akurasi_meter");
ensure_column($conn, 'absensi', 'status_lokasi', "VARCHAR(30) NULL AFTER jarak_meter");
?>
