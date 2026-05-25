<?php
session_start();
require 'koneksi.php';

header('Content-Type: application/json');

function hitungJarakMeter(float $lat1, float $lng1, float $lat2, float $lng2): float
{
    $earth_radius = 6371000;
    $d_lat = deg2rad($lat2 - $lat1);
    $d_lng = deg2rad($lng2 - $lng1);
    $a = sin($d_lat / 2) ** 2
        + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($d_lng / 2) ** 2;
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

    return $earth_radius * $c;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_SESSION['role'] ?? '') !== 'user') {
    echo json_encode(['status' => 'error', 'message' => 'Akses ditolak.']);
    exit();
}

$id_sesi = trim($_POST['id_sesi'] ?? '');
$nama_mahasiswa = $_SESSION['username'] ?? '';
$latitude = filter_input(INPUT_POST, 'latitude', FILTER_VALIDATE_FLOAT);
$longitude = filter_input(INPUT_POST, 'longitude', FILTER_VALIDATE_FLOAT);
$akurasi_meter = filter_input(INPUT_POST, 'akurasi_meter', FILTER_VALIDATE_FLOAT);

if ($id_sesi === '') {
    echo json_encode(['status' => 'error', 'message' => 'QR Code tidak terbaca atau tidak valid.']);
    exit();
}

if ($latitude === false || $latitude === null || $latitude < -90 || $latitude > 90 || $longitude === false || $longitude === null || $longitude < -180 || $longitude > 180) {
    echo json_encode(['status' => 'error', 'message' => 'Lokasi tidak terbaca. Izinkan akses lokasi lalu scan ulang.']);
    exit();
}

if ($akurasi_meter === false || $akurasi_meter < 0) {
    $akurasi_meter = null;
}

$stmt_sesi = $conn->prepare("SELECT tanggal, waktu_mulai, waktu_selesai, lokasi_lat, lokasi_lng, radius_meter FROM sesi_absensi WHERE id_sesi = ?");
$stmt_sesi->bind_param("s", $id_sesi);
$stmt_sesi->execute();
$result_sesi = $stmt_sesi->get_result();

if ($result_sesi->num_rows === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Sesi absensi tidak ditemukan.']);
    exit();
}

$sesi = $result_sesi->fetch_assoc();

try {
    $now = new DateTimeImmutable('now');
    $mulai = new DateTimeImmutable($sesi['tanggal'] . ' ' . $sesi['waktu_mulai']);
    $selesai = new DateTimeImmutable($sesi['tanggal'] . ' ' . $sesi['waktu_selesai']);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Jadwal sesi tidak valid.']);
    exit();
}

if ($now < $mulai) {
    echo json_encode(['status' => 'error', 'message' => 'Sesi absensi belum dimulai.']);
    exit();
}

if ($now > $selesai) {
    echo json_encode(['status' => 'error', 'message' => 'Sesi absensi sudah berakhir.']);
    exit();
}

$lokasi_lat = $sesi['lokasi_lat'];
$lokasi_lng = $sesi['lokasi_lng'];
$radius_meter = (int)$sesi['radius_meter'];

if ($lokasi_lat === null || $lokasi_lng === null || $radius_meter <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Sesi ini belum memiliki titik lokasi valid.']);
    exit();
}

$jarak_meter = round(hitungJarakMeter((float)$lokasi_lat, (float)$lokasi_lng, (float)$latitude, (float)$longitude), 2);
$status_lokasi = $jarak_meter <= $radius_meter ? 'Dalam Jangkauan' : 'Di Luar Jangkauan';

// Cek apakah mahasiswa sudah absen
$cek_stmt = $conn->prepare("SELECT id FROM absensi WHERE id_sesi = ? AND nama_mahasiswa = ?");
$cek_stmt->bind_param("ss", $id_sesi, $nama_mahasiswa);
$cek_stmt->execute();
$cek_result = $cek_stmt->get_result();

if ($cek_result->num_rows > 0) {
    echo json_encode(['status' => 'error', 'message' => 'Anda sudah melakukan absensi untuk sesi ini.']);
    exit();
}

// Insert data absen
$insert_stmt = $conn->prepare("INSERT INTO absensi (id_sesi, nama_mahasiswa, keterangan, latitude, longitude, akurasi_meter, jarak_meter, status_lokasi) VALUES (?, ?, 'Hadir', ?, ?, ?, ?, ?)");
$insert_stmt->bind_param("ssdddds", $id_sesi, $nama_mahasiswa, $latitude, $longitude, $akurasi_meter, $jarak_meter, $status_lokasi);

if ($insert_stmt->execute()) {
    $message = $status_lokasi === 'Dalam Jangkauan'
        ? 'Absensi berhasil dicatat. Lokasi dalam jangkauan (' . number_format($jarak_meter, 0, ',', '.') . ' m).'
        : 'Absensi dicatat, tetapi lokasi di luar jangkauan (' . number_format($jarak_meter, 0, ',', '.') . ' m dari titik sesi).';
    echo json_encode(['status' => 'success', 'message' => $message, 'lokasi' => $status_lokasi, 'jarak_meter' => $jarak_meter]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Terjadi kesalahan sistem, coba lagi.']);
}
?>
