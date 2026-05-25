<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Jalankan seed_database.php lewat CLI.');
}

require 'koneksi.php';

$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));

function run_stmt(mysqli $conn, string $sql, string $types = '', array $params = []): void
{
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException($conn->error);
    }

    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }

    if (!$stmt->execute()) {
        throw new RuntimeException($stmt->error);
    }
}

function seed_user(mysqli $conn, string $username, string $password, string $role, ?string $nim): void
{
    run_stmt(
        $conn,
        "INSERT INTO users (username, password, role, nim)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE password = VALUES(password), role = VALUES(role), nim = VALUES(nim)",
        "ssss",
        [$username, md5($password), $role, $nim]
    );
}

function seed_matkul(mysqli $conn, string $nama, string $kode): void
{
    run_stmt(
        $conn,
        "INSERT INTO mata_kuliah (nama_matkul, kode_matkul)
         VALUES (?, ?)
         ON DUPLICATE KEY UPDATE kode_matkul = VALUES(kode_matkul)",
        "ss",
        [$nama, $kode]
    );
}

function seed_sesi(
    mysqli $conn,
    string $id,
    string $kegiatan,
    string $matkul,
    string $lokasi_label,
    float $lokasi_lat,
    float $lokasi_lng,
    int $radius_meter,
    string $tanggal,
    string $mulai,
    string $selesai
): void {
    run_stmt(
        $conn,
        "INSERT INTO sesi_absensi (id_sesi, nama_kegiatan, mata_kuliah, lokasi_label, lokasi_lat, lokasi_lng, radius_meter, tanggal, waktu_mulai, waktu_selesai)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            nama_kegiatan = VALUES(nama_kegiatan),
            mata_kuliah = VALUES(mata_kuliah),
            lokasi_label = VALUES(lokasi_label),
            lokasi_lat = VALUES(lokasi_lat),
            lokasi_lng = VALUES(lokasi_lng),
            radius_meter = VALUES(radius_meter),
            tanggal = VALUES(tanggal),
            waktu_mulai = VALUES(waktu_mulai),
            waktu_selesai = VALUES(waktu_selesai)",
        "ssssddisss",
        [$id, $kegiatan, $matkul, $lokasi_label, $lokasi_lat, $lokasi_lng, $radius_meter, $tanggal, $mulai, $selesai]
    );
}

function seed_absensi(
    mysqli $conn,
    string $id_sesi,
    string $username,
    string $keterangan,
    string $waktu_scan,
    float $latitude,
    float $longitude,
    float $akurasi_meter,
    float $jarak_meter,
    string $status_lokasi
): void {
    run_stmt(
        $conn,
        "INSERT INTO absensi (id_sesi, nama_mahasiswa, keterangan, waktu_scan, latitude, longitude, akurasi_meter, jarak_meter, status_lokasi)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            keterangan = VALUES(keterangan),
            waktu_scan = VALUES(waktu_scan),
            latitude = VALUES(latitude),
            longitude = VALUES(longitude),
            akurasi_meter = VALUES(akurasi_meter),
            jarak_meter = VALUES(jarak_meter),
            status_lokasi = VALUES(status_lokasi)",
        "ssssdddds",
        [$id_sesi, $username, $keterangan, $waktu_scan, $latitude, $longitude, $akurasi_meter, $jarak_meter, $status_lokasi]
    );
}

try {
    seed_user($conn, 'admin', 'admin123', 'admin', null);
    seed_user($conn, 'budi', 'user123', 'user', '2024001');
    seed_user($conn, 'siti', 'user123', 'user', '2024002');
    seed_user($conn, 'andi', 'user123', 'user', '2024003');
    seed_user($conn, 'rina', 'user123', 'user', '2024004');
    seed_user($conn, 'dewi', 'user123', 'user', '2024005');

    seed_matkul($conn, 'Pemrograman Web', 'IF401');
    seed_matkul($conn, 'Basis Data', 'IF302');
    seed_matkul($conn, 'Jaringan Komputer', 'IF305');

    seed_sesi($conn, 'SESI-DEMO-AKTIF', 'Pertemuan Demo', 'Pemrograman Web', 'Titik Demo Kampus', -6.200000, 106.816666, 150, $today, '00:00:00', '23:59:00');
    seed_sesi($conn, 'SESI-DEMO-KEMARIN', 'Latihan Praktikum', 'Basis Data', 'Titik Demo Kampus', -6.200000, 106.816666, 150, $yesterday, '08:00:00', '10:00:00');

    seed_absensi($conn, 'SESI-DEMO-AKTIF', 'budi', 'Hadir', $today . ' 08:05:00', -6.200020, 106.816700, 12, 4, 'Dalam Jangkauan');
    seed_absensi($conn, 'SESI-DEMO-AKTIF', 'siti', 'Izin', $today . ' 08:10:00', -6.200120, 106.816780, 16, 18, 'Dalam Jangkauan');
    seed_absensi($conn, 'SESI-DEMO-AKTIF', 'andi', 'Terlambat', $today . ' 08:22:00', -6.210000, 106.826000, 25, 1515, 'Di Luar Jangkauan');
    seed_absensi($conn, 'SESI-DEMO-KEMARIN', 'budi', 'Hadir', $yesterday . ' 08:03:00', -6.200010, 106.816650, 10, 2, 'Dalam Jangkauan');
    seed_absensi($conn, 'SESI-DEMO-KEMARIN', 'rina', 'Sakit', $yesterday . ' 08:12:00', -6.200150, 106.816600, 15, 18, 'Dalam Jangkauan');
    seed_absensi($conn, 'SESI-DEMO-KEMARIN', 'dewi', 'Hadir', $yesterday . ' 08:15:00', -6.215000, 106.832000, 30, 2370, 'Di Luar Jangkauan');

    echo "Database percobaan berhasil dibuat.\n";
    echo "Admin: admin / admin123\n";
    echo "Mahasiswa: budi, siti, andi, rina, dewi / user123\n";
    echo "Sesi aktif: SESI-DEMO-AKTIF\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Seed gagal: " . $e->getMessage() . "\n");
    exit(1);
}
?>
