<?php
session_start();
require 'koneksi.php';

if (!isset($_SESSION['username']) || ($_SESSION['role'] ?? '') !== 'user') {
    header("Location: index.php");
    exit();
}

$nama_user = ucfirst($_SESSION['username']);
$nim_user = !empty($_SESSION['nim']) ? $_SESSION['nim'] : "NIM Belum Diatur";

$stmt = $conn->prepare("SELECT id_sesi, waktu_scan, keterangan, status_lokasi, jarak_meter FROM absensi WHERE nama_mahasiswa = ? ORDER BY waktu_scan DESC");
$stmt->bind_param("s", $_SESSION['username']);
$stmt->execute();
$result = $stmt->get_result();

$history_data = [];
$bulan_inggris = ['January','February','March','April','May','June','July','August','September','October','November','December'];
$bulan_indo = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];

$total_hadir = 0;
$total_alpha = 0;

while ($row = $result->fetch_assoc()) {
    $ts = strtotime($row['waktu_scan']);
    $bulan_tahun = str_replace($bulan_inggris, $bulan_indo, date('F Y', $ts));
    $tanggal = date('d', $ts) . ' ' . str_replace($bulan_inggris, $bulan_indo, date('F', $ts)) . ' ' . date('Y', $ts);
    $history_data[$bulan_tahun][] = [
        'kegiatan' => $row['id_sesi'],
        'tanggal' => $tanggal,
        'waktu' => date('H:i', $ts) . ' WIB',
        'keterangan' => $row['keterangan'],
        'status_lokasi' => $row['status_lokasi'] ?: 'Belum Dicek',
        'jarak_meter' => $row['jarak_meter']
    ];
    if ($row['keterangan'] === 'Hadir') $total_hadir++;
    if ($row['keterangan'] === 'Alpha' || !$row['keterangan']) $total_alpha++;
}
$total_sesi = array_sum(array_map('count', $history_data));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Riwayat – Absensi Digital</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .summary-row { display: flex; gap: 10px; width: 100%; margin-bottom: 8px; }
        .s-card { flex: 1; background: var(--surface); border: 1px solid var(--border);
            border-radius: var(--radius-md); padding: 12px 10px; text-align: center; }
        .s-num { font-size: 20px; font-weight: 700; }
        .s-lbl { font-size: 10px; color: var(--text-muted); font-weight: 600; text-transform: uppercase; margin-top: 2px; }
        .badge-lokasi-ok { background: var(--success-bg); color: var(--success); }
        .badge-lokasi-out { background: var(--danger-bg); color: var(--danger); }
        .badge-lokasi-none { background: var(--surface); color: var(--text-muted); border: 1px solid var(--border); }
    </style>
</head>
<body>

<header class="header">
    <div class="header-title"><i class="fas fa-history"></i> Riwayat</div>
    <div class="profile">
        <div class="profile-info">
            <span class="name"><?= htmlspecialchars($nama_user) ?></span>
            <span class="nim"><?= htmlspecialchars($nim_user) ?></span>
        </div>
        <img src="https://ui-avatars.com/api/?name=<?= urlencode($nama_user) ?>&background=fff&color=2563EB" alt="Avatar">
        <a href="logout.php" class="logout-btn" title="Keluar"><i class="fas fa-sign-out-alt"></i></a>
    </div>
</header>

<main class="main-content w-400" style="padding-bottom: 90px;">
    <?php if (!empty($history_data)): ?>
    <div class="summary-row">
        <div class="s-card">
            <div class="s-num" style="color:var(--primary)"><?= $total_sesi ?></div>
            <div class="s-lbl">Total Sesi</div>
        </div>
        <div class="s-card">
            <div class="s-num" style="color:var(--success)"><?= $total_hadir ?></div>
            <div class="s-lbl">Hadir</div>
        </div>
        <div class="s-card">
            <div class="s-num" style="color:var(--danger)"><?= $total_alpha ?></div>
            <div class="s-lbl">Alpha</div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (empty($history_data)): ?>
        <div class="empty-state" style="padding:60px 16px;">
            <i class="fas fa-folder-open" style="font-size:40px; color:var(--text-muted); display:block; margin-bottom:12px;"></i>
            Belum ada riwayat absensi.
        </div>
    <?php else: ?>
        <?php foreach ($history_data as $bulan => $records): ?>
            <div class="section-title"><?= $bulan ?></div>
            <?php foreach ($records as $row):
                $status_class = 'badge-' . strtolower($row['keterangan']);
                $lokasi_class = $row['status_lokasi'] === 'Dalam Jangkauan'
                    ? 'badge-lokasi-ok'
                    : ($row['status_lokasi'] === 'Di Luar Jangkauan' ? 'badge-lokasi-out' : 'badge-lokasi-none');
                $jarak_text = $row['jarak_meter'] !== null ? ' (' . number_format((float)$row['jarak_meter'], 0, ',', '.') . ' m)' : '';
            ?>
            <div class="history-card">
                <div class="history-info">
                    <div class="activity-name"><?= htmlspecialchars($row['kegiatan']) ?></div>
                    <div class="activity-time">
                        <span><i class="far fa-calendar-alt"></i> <?= $row['tanggal'] ?></span>
                        <span><i class="far fa-clock"></i> <?= $row['waktu'] ?></span>
                        <span><i class="fas fa-location-dot"></i> <?= htmlspecialchars($row['status_lokasi'] . $jarak_text) ?></span>
                    </div>
                </div>
                <div>
                    <div class="badge <?= $status_class ?>"><?= htmlspecialchars($row['keterangan']) ?></div>
                    <div class="badge <?= $lokasi_class ?>" style="margin-top:6px;"><?= htmlspecialchars($row['status_lokasi']) ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endforeach; ?>
    <?php endif; ?>
</main>

<div class="bottom-nav">
    <a href="scan.php" class="nav-item">
        <i class="fas fa-expand"></i>
        <span>Scan</span>
    </a>
    <a href="riwayat.php" class="nav-item active">
        <i class="fas fa-history"></i>
        <span>Riwayat</span>
    </a>
</div>

</body>
</html>
