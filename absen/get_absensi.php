<?php
// get_absensi.php
session_start();
require 'koneksi.php';

header('Content-Type: application/json');

if (!isset($_SESSION['username']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Akses ditolak.']);
    exit();
}

// Ambil ID Sesi yang sedang aktif
$id_sesi = trim($_GET['sesi'] ?? '');

if ($id_sesi === '') {
    echo json_encode([]);
    exit();
}

// Ambil data absensi berdasarkan sesi tersebut
$stmt = $conn->prepare("SELECT nama_mahasiswa as nama, DATE_FORMAT(waktu_scan, '%H:%i') as waktu, keterangan, status_lokasi, jarak_meter FROM absensi WHERE id_sesi = ? ORDER BY waktu_scan ASC");
$stmt->bind_param("s", $id_sesi);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while($row = $result->fetch_assoc()){
    $data[] = $row;
}

echo json_encode($data);
?>
