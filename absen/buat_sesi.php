<?php
session_start();
require 'koneksi.php';

if (!isset($_SESSION['username']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: index.php");
    exit();
}

$nama_admin = ucfirst($_SESSION['username']);
$error = '';
$form_values = [
    'nama_kegiatan' => '',
    'mata_kuliah' => '',
    'tanggal' => date('Y-m-d'),
    'waktu_mulai' => '',
    'waktu_selesai' => '',
    'lokasi_label' => 'Ruang Kelas',
    'lokasi_lat' => '',
    'lokasi_lng' => '',
    'radius_meter' => '100',
];

// Fetch semua mata kuliah untuk dropdown
$list_mata_kuliah = [];
    $stmt_matkul = $conn->query("SELECT nama_matkul FROM mata_kuliah ORDER BY nama_matkul ASC");
if ($stmt_matkul) {
    while($row = $stmt_matkul->fetch_assoc()) {
        $list_mata_kuliah[] = $row['nama_matkul'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_kegiatan = trim($_POST['nama_kegiatan'] ?? '');
    $mata_kuliah   = trim($_POST['mata_kuliah'] ?? '');
    $tanggal       = trim($_POST['tanggal'] ?? '');
    $waktu_mulai   = trim($_POST['waktu_mulai'] ?? '');
    $waktu_selesai = trim($_POST['waktu_selesai'] ?? '');
    $lokasi_label  = trim($_POST['lokasi_label'] ?? '');
    $lokasi_lat    = trim($_POST['lokasi_lat'] ?? '');
    $lokasi_lng    = trim($_POST['lokasi_lng'] ?? '');
    $radius_meter  = trim($_POST['radius_meter'] ?? '');

    $form_values = [
        'nama_kegiatan' => $nama_kegiatan,
        'mata_kuliah' => $mata_kuliah,
        'tanggal' => $tanggal,
        'waktu_mulai' => $waktu_mulai,
        'waktu_selesai' => $waktu_selesai,
        'lokasi_label' => $lokasi_label,
        'lokasi_lat' => $lokasi_lat,
        'lokasi_lng' => $lokasi_lng,
        'radius_meter' => $radius_meter,
    ];

    $lat_float = filter_var($lokasi_lat, FILTER_VALIDATE_FLOAT);
    $lng_float = filter_var($lokasi_lng, FILTER_VALIDATE_FLOAT);
    $radius_int = filter_var($radius_meter, FILTER_VALIDATE_INT);

    if ($nama_kegiatan === '' || $mata_kuliah === '' || $tanggal === '' || $waktu_mulai === '' || $waktu_selesai === '' || $lokasi_lat === '' || $lokasi_lng === '' || $radius_meter === '') {
        $error = "Semua field wajib diisi.";
    } elseif (strtotime($tanggal . ' ' . $waktu_mulai) >= strtotime($tanggal . ' ' . $waktu_selesai)) {
        $error = "Waktu selesai harus lebih besar dari waktu mulai.";
    } elseif ($lat_float === false || $lat_float < -90 || $lat_float > 90) {
        $error = "Latitude lokasi tidak valid.";
    } elseif ($lng_float === false || $lng_float < -180 || $lng_float > 180) {
        $error = "Longitude lokasi tidak valid.";
    } elseif ($radius_int === false || $radius_int < 10 || $radius_int > 10000) {
        $error = "Radius harus antara 10 sampai 10000 meter.";
    } else {
        $stmt_cek_matkul = $conn->prepare("SELECT id_matkul FROM mata_kuliah WHERE nama_matkul = ?");
        $stmt_cek_matkul->bind_param("s", $mata_kuliah);
        $stmt_cek_matkul->execute();
        $result_cek_matkul = $stmt_cek_matkul->get_result();

        if ($result_cek_matkul->num_rows === 0) {
            $error = "Mata kuliah tidak valid.";
        } else {
            $id_sesi = "SESI-" . date('YmdHis') . "-" . random_int(100, 999);
            // Query dengan penambahan kolom mata_kuliah
            $stmt = $conn->prepare("INSERT INTO sesi_absensi (id_sesi, nama_kegiatan, mata_kuliah, lokasi_label, lokasi_lat, lokasi_lng, radius_meter, tanggal, waktu_mulai, waktu_selesai) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssddisss", $id_sesi, $nama_kegiatan, $mata_kuliah, $lokasi_label, $lat_float, $lng_float, $radius_int, $tanggal, $waktu_mulai, $waktu_selesai);

            if ($stmt->execute()) {
                header("Location: qr.php?sesi=" . urlencode($id_sesi));
                exit();
            } else {
                $error = "Terjadi kesalahan. Gagal membuat sesi absensi.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buat Sesi – Absensi Digital</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .grid-3 { display: grid; grid-template-columns: 1fr 1fr 0.8fr; gap: 12px; }
        .btn-location {
            width: 100%; border: 1px solid var(--primary); color: var(--primary);
            background: var(--primary-light); border-radius: var(--radius-sm);
            padding: 10px 12px; font-family: inherit; font-weight: 600; cursor: pointer;
            margin-bottom: 14px;
        }
        .btn-location:hover { opacity: 0.9; }
        .location-note { font-size: 12px; color: var(--text-muted); margin-top: -6px; margin-bottom: 12px; }
        .location-status { display: none; margin-bottom: 12px; font-size: 12px; }
        @media (max-width: 480px) { .grid-2 { grid-template-columns: 1fr; } }
        @media (max-width: 600px) { .grid-3 { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

<header class="header">
    <a href="#" class="user-info">
        <img src="https://ui-avatars.com/api/?name=<?= urlencode($nama_admin) ?>&background=fff&color=2563EB" alt="Admin">
        <span><?= htmlspecialchars($nama_admin) ?></span>
    </a>
    <div class="menu-container">
        <button class="menu-btn" onclick="toggleMenu()"><i class="fas fa-ellipsis-v"></i></button>
        <div class="dropdown-menu" id="dropdownMenu">
            <a href="buat_sesi.php" class="active-menu"><i class="fas fa-plus-circle"></i> Buat Sesi</a>
            <a href="edit.php"><i class="fas fa-edit"></i> Edit Absensi</a>
            <a href="qr.php"><i class="fas fa-qrcode"></i> Lihat QR Aktif</a>
            <a href="logout.php" style="color:var(--danger)"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>
</header>

<main class="main-content w-400">
    <div class="page-header">
        <h2>Buat Sesi Absensi</h2>
        <p>Isi detail kegiatan, lalu generate QR Code</p>
    </div>

    <div class="form-card">
        <?php if ($error): ?>
            <div class="error-msg"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form action="" method="POST">
            <div class="form-group">
                <label>Nama Kegiatan</label>
                <input type="text" name="nama_kegiatan" class="form-control" required placeholder="Contoh: UTS, Ujian Praktik" value="<?= htmlspecialchars($form_values['nama_kegiatan'], ENT_QUOTES) ?>">
            </div>
            <div class="form-group">
                <label>Mata Kuliah</label>
                <select name="mata_kuliah" class="form-control" required>
                    <option value="">-- Pilih Mata Kuliah --</option>
                    <?php foreach ($list_mata_kuliah as $matkul): ?>
                        <option value="<?= htmlspecialchars($matkul, ENT_QUOTES) ?>" <?= $form_values['mata_kuliah'] === $matkul ? 'selected' : '' ?>><?= htmlspecialchars($matkul) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Tanggal</label>
                <input type="date" name="tanggal" class="form-control" value="<?= htmlspecialchars($form_values['tanggal'], ENT_QUOTES) ?>" required>
            </div>
            <div class="grid-2">
                <div class="form-group">
                    <label>Waktu Mulai</label>
                    <input type="time" name="waktu_mulai" class="form-control" value="<?= htmlspecialchars($form_values['waktu_mulai'], ENT_QUOTES) ?>" required>
                </div>
                <div class="form-group">
                    <label>Waktu Selesai</label>
                    <input type="time" name="waktu_selesai" class="form-control" value="<?= htmlspecialchars($form_values['waktu_selesai'], ENT_QUOTES) ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label>Nama Lokasi</label>
                <input type="text" name="lokasi_label" class="form-control" placeholder="Contoh: Lab Komputer 1" value="<?= htmlspecialchars($form_values['lokasi_label'], ENT_QUOTES) ?>">
            </div>
            <button type="button" class="btn-location" onclick="ambilLokasiAdmin()">
                <i class="fas fa-location-crosshairs"></i> Ambil Lokasi Saat Ini
            </button>
            <div id="locationStatus" class="location-status"></div>
            <div class="grid-3">
                <div class="form-group">
                    <label>Latitude</label>
                    <input type="number" step="0.0000001" min="-90" max="90" name="lokasi_lat" id="lokasi_lat" class="form-control" value="<?= htmlspecialchars($form_values['lokasi_lat'], ENT_QUOTES) ?>" required>
                </div>
                <div class="form-group">
                    <label>Longitude</label>
                    <input type="number" step="0.0000001" min="-180" max="180" name="lokasi_lng" id="lokasi_lng" class="form-control" value="<?= htmlspecialchars($form_values['lokasi_lng'], ENT_QUOTES) ?>" required>
                </div>
                <div class="form-group">
                    <label>Radius Meter</label>
                    <input type="number" min="10" max="10000" name="radius_meter" class="form-control" value="<?= htmlspecialchars($form_values['radius_meter'], ENT_QUOTES) ?>" required>
                </div>
            </div>
            <div class="location-note">Mahasiswa akan ditandai dalam jangkauan jika jaraknya berada di bawah radius ini.</div>
            <button type="submit" class="btn-submit"><i class="fas fa-qrcode"></i> Generate QR Code</button>
        </form>
    </div>
</main>

<script>
function toggleMenu() { document.getElementById("dropdownMenu").classList.toggle("show"); }
window.onclick = function(e) {
    if (!e.target.closest('.menu-container')) {
        document.querySelectorAll('.dropdown-menu').forEach(m => m.classList.remove('show'));
    }
}

function setLocationStatus(message, type) {
    const box = document.getElementById('locationStatus');
    box.style.display = 'block';
    box.className = 'location-status ' + (type === 'error' ? 'error-msg' : 'success-msg');
    box.textContent = message;
}

function ambilLokasiAdmin() {
    if (!navigator.geolocation) {
        setLocationStatus('Browser tidak mendukung deteksi lokasi.', 'error');
        return;
    }

    setLocationStatus('Mengambil lokasi...', 'info');
    navigator.geolocation.getCurrentPosition(
        pos => {
            document.getElementById('lokasi_lat').value = pos.coords.latitude.toFixed(7);
            document.getElementById('lokasi_lng').value = pos.coords.longitude.toFixed(7);
            setLocationStatus('Lokasi berhasil diambil.', 'success');
        },
        () => setLocationStatus('Gagal mengambil lokasi. Izinkan akses lokasi di browser atau isi manual.', 'error'),
        { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
    );
}

</script>
</body>
</html>
