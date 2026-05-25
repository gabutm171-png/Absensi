<?php
session_start();
require 'koneksi.php';

if (!isset($_SESSION['username']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: index.php");
    exit();
}

$nama_admin = ucfirst($_SESSION['username']);
$id_sesi_aktif = trim($_GET['sesi'] ?? "");
$status_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['id_sesi']) && $_POST['action'] === 'stop') {
    $stop_sesi = trim($_POST['id_sesi']);
    $stmt_stop = $conn->prepare("UPDATE sesi_absensi SET waktu_selesai = ? WHERE id_sesi = ?");
    $current_time = date('H:i');
    $stmt_stop->bind_param("ss", $current_time, $stop_sesi);
    if ($stmt_stop->execute()) {
        header("Location: qr.php?sesi=" . urlencode($stop_sesi) . "&stopped=1");
        exit();
    }
}

if (isset($_GET['stopped'])) {
    $status_message = 'Pemindaian sesi telah dihentikan.';
}

$sesi_data = null;
$session_closed = false;
$session_pending = false;
$session_label = 'SESI AKTIF';

if ($id_sesi_aktif === '') {
    $stmt_latest = $conn->query("SELECT id_sesi FROM sesi_absensi ORDER BY dibuat_pada DESC LIMIT 1");
    if ($stmt_latest && $stmt_latest->num_rows > 0) {
        $id_sesi_aktif = $stmt_latest->fetch_assoc()['id_sesi'];
    }
}

if ($id_sesi_aktif !== '') {
    $stmt_sesi = $conn->prepare("SELECT id_sesi, nama_kegiatan, mata_kuliah, lokasi_label, lokasi_lat, lokasi_lng, radius_meter, tanggal, waktu_mulai, waktu_selesai FROM sesi_absensi WHERE id_sesi = ?");
    $stmt_sesi->bind_param("s", $id_sesi_aktif);
    $stmt_sesi->execute();
    $result_sesi = $stmt_sesi->get_result();
    if ($result_sesi->num_rows > 0) {
        $sesi_data = $result_sesi->fetch_assoc();
        try {
            $now = new DateTimeImmutable('now');
            $session_start = new DateTimeImmutable($sesi_data['tanggal'] . ' ' . $sesi_data['waktu_mulai']);
            $session_end = new DateTimeImmutable($sesi_data['tanggal'] . ' ' . $sesi_data['waktu_selesai']);

            if ($now < $session_start) {
                $session_pending = true;
                $session_label = 'SESI BELUM MULAI';
            } elseif ($now > $session_end) {
                $session_closed = true;
                $session_label = 'SESI DITUTUP';
            }
        } catch (Exception $e) {
            $session_closed = true;
            $session_label = 'JADWAL TIDAK VALID';
        }
    } else {
        $id_sesi_aktif = "";
        $status_message = "Sesi tidak ditemukan.";
    }
} else {
    $status_message = "Belum ada sesi absensi yang dibuat.";
}

$session_active = $sesi_data && !$session_pending && !$session_closed;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Live – Absensi Digital</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>
        .qr-section {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 24px;
            background: var(--primary-light);
            border-radius: var(--radius-md);
            border: 1px solid var(--primary-muted);
            margin-bottom: 20px;
            width: 100%;
        }
        .live-dot {
            display: inline-flex; align-items: center; gap: 6px;
            font-size: 12px; font-weight: 600; color: var(--success);
            background: var(--success-bg); padding: 4px 10px;
            border-radius: 99px; margin-bottom: 12px;
        }
        .live-dot.closed {
            color: var(--danger);
            background: var(--danger-bg);
        }
        .live-dot.pending {
            color: var(--primary);
            background: var(--primary-light);
        }
        .live-dot::before {
            content: ''; width: 7px; height: 7px; border-radius: 50%;
            background: var(--success); animation: pulse 1.4s infinite;
        }
        .live-dot.closed::before {
            background: var(--danger);
            animation: none;
        }
        .live-dot.pending::before {
            background: var(--primary);
            animation: none;
        }
        .qr-action {
            margin-top: 16px;
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            justify-content: center;
            width: 100%;
        }
        .qr-action button {
            min-width: 160px;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; } 50% { opacity: 0.3; }
        }
        .stat-row { display: flex; gap: 10px; width: 100%; margin-bottom: 16px; }
        .stat-card { flex: 1; background: var(--surface-2); border: 1px solid var(--border);
            border-radius: var(--radius-md); padding: 12px; text-align: center; }
        .stat-num { font-size: 24px; font-weight: 700; color: var(--primary); }
        .stat-label { font-size: 11px; color: var(--text-muted); font-weight: 500; margin-top: 2px; }
        .badge-lokasi-ok { background: var(--success-bg); color: var(--success); }
        .badge-lokasi-out { background: var(--danger-bg); color: var(--danger); }
        .badge-lokasi-none { background: var(--surface); color: var(--text-muted); border: 1px solid var(--border); }
        .location-summary { font-size: 12px; color: var(--text-muted); text-align: center; margin-top: 8px; }
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
            <a href="buat_sesi.php"><i class="fas fa-plus-circle"></i> Buat Sesi</a>
            <a href="edit.php?sesi=<?= urlencode($id_sesi_aktif) ?>"><i class="fas fa-edit"></i> Edit Absensi</a>
            <a href="logout.php" style="color:var(--danger)"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>
</header>

<main class="main-content w-450">
    <div class="page-header">
        <h2>QR Code & Live Tracking</h2>
        <p>Tampilkan QR Code ini kepada mahasiswa untuk presensi</p>
    </div>

    <?php if ($status_message): ?>
        <div class="<?= isset($_GET['stopped']) ? 'success-msg' : 'error-msg' ?>">
            <i class="fas fa-info-circle"></i> <?= htmlspecialchars($status_message) ?>
        </div>
    <?php endif; ?>

    <?php if ($sesi_data): ?>
    <div class="tracker-card">
        <div class="live-dot <?= $session_closed ? 'closed' : ($session_pending ? 'pending' : '') ?>">
            <?= htmlspecialchars($session_label) ?>
        </div>
        <div class="qr-section">
            <div class="qr-display-area" id="qrcode-display"></div>
            <p class="instruction-text" style="margin-bottom:0;">
                Sesi: <strong><?= htmlspecialchars($id_sesi_aktif) ?></strong>
            </p>
            <p class="instruction-text" style="margin-bottom:0;">
                <?= htmlspecialchars($sesi_data['mata_kuliah'] ?? '-') ?> - <?= htmlspecialchars($sesi_data['nama_kegiatan'] ?? '-') ?>
            </p>
            <p class="location-summary">
                <?= htmlspecialchars($sesi_data['lokasi_label'] ?: 'Lokasi sesi') ?>,
                radius <?= (int)$sesi_data['radius_meter'] ?> m
            </p>
            <?php if ($session_active): ?>
            <form method="POST" class="qr-action">
                <input type="hidden" name="action" value="stop">
                <input type="hidden" name="id_sesi" value="<?= htmlspecialchars($id_sesi_aktif, ENT_QUOTES) ?>">
                <button type="submit" class="btn-submit"><i class="fas fa-stop-circle"></i> Hentikan Scan</button>
            </form>
            <?php endif; ?>
        </div>

        <div class="stat-row">
            <div class="stat-card">
                <div class="stat-num" id="count-hadir">0</div>
                <div class="stat-label">Hadir</div>
            </div>
            <div class="stat-card">
                <div class="stat-num" id="count-total">0</div>
                <div class="stat-label">Total Scan</div>
            </div>
            <div class="stat-card">
                <div class="stat-num" id="count-lokasi">0</div>
                <div class="stat-label">Dalam Radius</div>
            </div>
        </div>

        <div class="divider"></div>

        <div class="table-container" style="width:100%">
            <table id="absensiTable">
                <thead>
                    <tr>
                        <th style="width:8%">No</th>
                        <th class="col-nama" style="width:34%; text-align:left; padding-left:14px">Nama</th>
                        <th style="width:16%">Waktu</th>
                        <th style="width:20%">Keterangan</th>
                        <th style="width:22%">Lokasi</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                    <tr><td colspan="5" class="empty-state">Menunggu mahasiswa scan...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
    <?php else: ?>
        <div class="empty-state" style="padding:60px 16px;">Belum ada sesi yang bisa ditampilkan.</div>
    <?php endif; ?>
</main>

<script>
function toggleMenu() { document.getElementById("dropdownMenu").classList.toggle("show"); }
window.onclick = function(e) {
    if (!e.target.closest('.menu-container')) {
        document.querySelectorAll('.dropdown-menu').forEach(m => m.classList.remove('show'));
    }
}

const idSesiAktif = <?= json_encode($id_sesi_aktif) ?>;
let lastDataCount = 0;
const badgeMap = { Hadir:'badge-hadir', Sakit:'badge-sakit', Alpha:'badge-alpha', Izin:'badge-izin', Terlambat:'badge-terlambat' };
const lokasiBadgeMap = {
    'Dalam Jangkauan': 'badge-lokasi-ok',
    'Di Luar Jangkauan': 'badge-lokasi-out'
};

window.onload = function() {
    if (!idSesiAktif || !document.getElementById("qrcode-display")) return;

    new QRCode(document.getElementById("qrcode-display"), {
        text: idSesiAktif, width: 200, height: 200,
        colorDark: "#000", colorLight: "#fff",
        correctLevel: QRCode.CorrectLevel.H
    });
    fetchDataAbsensi();
    setInterval(fetchDataAbsensi, 3000);
};

function fetchDataAbsensi() {
    fetch('get_absensi.php?sesi=' + encodeURIComponent(idSesiAktif))
        .then(r => r.json())
        .then(data => renderTable(data))
        .catch(err => console.error("Gagal:", err));
}

function renderTable(data) {
    const body = document.getElementById("tableBody");
    if (!Array.isArray(data)) {
        console.error("Format data absensi tidak valid:", data);
        return;
    }

    document.getElementById('count-hadir').textContent = data.filter(d => d.keterangan === 'Hadir').length;
    document.getElementById('count-total').textContent = data.length;
    document.getElementById('count-lokasi').textContent = data.filter(d => d.status_lokasi === 'Dalam Jangkauan').length;
    if (!data.length) {
        body.innerHTML = '<tr><td colspan="5" class="empty-state">Menunggu mahasiswa scan...</td></tr>';
        return;
    }
    body.innerHTML = '';
    data.forEach((scan, i) => {
        const tr = document.createElement('tr');
        if (i >= lastDataCount && lastDataCount !== 0) tr.className = 'new-row';
        const badge = badgeMap[scan.keterangan] || 'badge-alpha';

        const noCell = document.createElement('td');
        noCell.textContent = i + 1;

        const namaCell = document.createElement('td');
        namaCell.className = 'col-nama';
        namaCell.textContent = scan.nama || '-';

        const waktuCell = document.createElement('td');
        waktuCell.textContent = scan.waktu || '-';

        const ketCell = document.createElement('td');
        const ketBadge = document.createElement('span');
        ketBadge.className = 'badge ' + badge;
        ketBadge.textContent = scan.keterangan || '-';
        ketCell.appendChild(ketBadge);

        const lokasiCell = document.createElement('td');
        const lokasiBadge = document.createElement('span');
        const lokasiClass = lokasiBadgeMap[scan.status_lokasi] || 'badge-lokasi-none';
        lokasiBadge.className = 'badge ' + lokasiClass;
        const jarak = scan.jarak_meter === null || scan.jarak_meter === undefined ? '' : ` (${Math.round(Number(scan.jarak_meter))} m)`;
        lokasiBadge.textContent = (scan.status_lokasi || 'Belum Dicek') + jarak;
        lokasiCell.appendChild(lokasiBadge);

        tr.append(noCell, namaCell, waktuCell, ketCell, lokasiCell);
        body.appendChild(tr);
    });
    lastDataCount = data.length;
}
</script>
</body>
</html>
