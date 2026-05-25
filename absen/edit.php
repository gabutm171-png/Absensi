<?php
session_start();
require 'koneksi.php';

if (!isset($_SESSION['username']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: index.php");
    exit();
}
$nama_admin = ucfirst($_SESSION['username']);
$pesan_sukses = "";

$id_sesi_aktif = "";
$stmt_sesi = $conn->query("SELECT id_sesi FROM sesi_absensi ORDER BY dibuat_pada DESC LIMIT 1");
if ($stmt_sesi->num_rows > 0) { $id_sesi_aktif = $stmt_sesi->fetch_assoc()['id_sesi']; }
if (isset($_GET['sesi']) && trim($_GET['sesi']) !== '') { $id_sesi_aktif = trim($_GET['sesi']); }
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_sesi_aktif']) && trim($_POST['id_sesi_aktif']) !== '') {
    $id_sesi_aktif = trim($_POST['id_sesi_aktif']);
}

// Fetch semua sesi untuk dropdown
$list_sesi = [];
$stmt_all_sesi = $conn->query("SELECT id_sesi, mata_kuliah, nama_kegiatan, tanggal FROM sesi_absensi ORDER BY dibuat_pada DESC LIMIT 30");
if ($stmt_all_sesi) {
    while($row = $stmt_all_sesi->fetch_assoc()) {
        $list_sesi[] = $row;
    }
}

// Handle AJAX delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    header('Content-Type: application/json');
    $id_absensi = (int)($_POST['id_absensi'] ?? 0);
    if ($id_absensi <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'ID absensi tidak valid']);
        exit();
    }

    $stmt_del = $conn->prepare("DELETE FROM absensi WHERE id = ?");
    $stmt_del->bind_param("i", $id_absensi);
    if ($stmt_del->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Data berhasil dihapus']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Gagal menghapus data']);
    }
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['keterangan']) && is_array($_POST['keterangan'])) {
    $allowed_keterangan = ['Hadir', 'Sakit', 'Alpha', 'Izin', 'Terlambat'];

    foreach ($_POST['keterangan'] as $username_mhs => $keterangan_baru) {
        $username_key = $username_mhs;
        if (!in_array($keterangan_baru, $allowed_keterangan, true)) {
            continue;
        }

        $username_mhs = trim($username_mhs);
        if ($username_mhs === '' || $id_sesi_aktif === '') {
            continue;
        }

        $id_absensi = $_POST['id_absensi'][$username_key] ?? '';
        if (!empty($id_absensi)) {
            $id_absensi = (int)$id_absensi;
            $stmt_update = $conn->prepare("UPDATE absensi SET keterangan = ? WHERE id = ?");
            $stmt_update->bind_param("si", $keterangan_baru, $id_absensi);
            $stmt_update->execute();
        } else {
            $stmt_insert = $conn->prepare("INSERT INTO absensi (id_sesi, nama_mahasiswa, keterangan) VALUES (?, ?, ?)");
            $stmt_insert->bind_param("sss", $id_sesi_aktif, $username_mhs, $keterangan_baru);
            $stmt_insert->execute();
        }
    }
    $pesan_sukses = "Data absensi berhasil diperbarui!";
}

$data_absensi = [];
if (!empty($id_sesi_aktif)) {
    $query = "
        SELECT u.username, u.nim, a.id AS id_absensi, DATE_FORMAT(a.waktu_scan, '%H:%i') AS waktu_scan, a.keterangan,
               a.status_lokasi, a.jarak_meter
        FROM users u
        LEFT JOIN absensi a ON u.username = a.nama_mahasiswa AND a.id_sesi = ?
        WHERE u.role = 'user' ORDER BY u.nim ASC, u.username ASC
    ";
    $stmt_data = $conn->prepare($query);
    $stmt_data->bind_param("s", $id_sesi_aktif);
    $stmt_data->execute();
    $result_data = $stmt_data->get_result();
    while($row = $result_data->fetch_assoc()) { $data_absensi[] = $row; }
}

$jumlah_hadir = count(array_filter($data_absensi, fn($r) => $r['keterangan'] === 'Hadir'));
$jumlah_alpha = count(array_filter($data_absensi, fn($r) => !$r['keterangan'] || $r['keterangan'] === 'Alpha'));
$jumlah_lokasi_dalam = count(array_filter($data_absensi, fn($r) => $r['status_lokasi'] === 'Dalam Jangkauan'));
$jumlah_lokasi_luar = count(array_filter($data_absensi, fn($r) => $r['status_lokasi'] === 'Di Luar Jangkauan'));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Absensi – Absensi Digital</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .stat-row { display: flex; gap: 10px; width: 100%; margin-bottom: 20px; }
        .stat-card { flex: 1; background: var(--surface); border: 1px solid var(--border);
            border-radius: var(--radius-md); padding: 14px; text-align: center; }
        .stat-num { font-size: 22px; font-weight: 700; }
        .stat-label { font-size: 11px; color: var(--text-muted); font-weight: 500; margin-top: 2px; }
        .s-hadir { color: var(--success); }
        .s-alpha { color: var(--danger); }
        .s-total { color: var(--primary); }
        .filter-section {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: var(--radius-md); padding: 16px; margin-bottom: 16px;
        }
        .filter-label {
            font-size: 12px; font-weight: 600; color: var(--text-secondary);
            text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; display: block;
        }
        .filter-select {
            width: 100%; padding: 10px 12px; border: 1px solid var(--border);
            border-radius: var(--radius-sm); font-family: inherit; font-size: 13px;
            background: var(--surface-2); color: var(--text-primary);
        }
        .filter-select:focus { border-color: var(--primary); outline: none; }
        .btn-delete {
            background: var(--danger); color: white; border: none;
            padding: 4px 8px; border-radius: 4px; cursor: pointer;
            font-size: 12px; transition: all 0.2s;
        }
        .btn-delete:hover { background: #c41e3a; opacity: 0.9; }
        .badge-lokasi-ok { background: var(--success-bg); color: var(--success); }
        .badge-lokasi-out { background: var(--danger-bg); color: var(--danger); }
        .badge-lokasi-none { background: var(--surface); color: var(--text-muted); border: 1px solid var(--border); }
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
            <a href="#" class="active-menu"><i class="fas fa-edit"></i> Edit Data</a>
            <a href="qr.php?sesi=<?= urlencode($id_sesi_aktif) ?>"><i class="fas fa-qrcode"></i> Lihat QR</a>
            <a href="logout.php" style="color:var(--danger)"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>
</header>

<main class="main-content w-500">
    <div class="page-header">
        <h2>Edit Data Absensi</h2>
        <p>Ubah status kehadiran mahasiswa secara manual</p>
        <?php if (!empty($id_sesi_aktif)): ?>
            <span class="sesi-badge"><i class="fas fa-layer-group"></i> <?= htmlspecialchars($id_sesi_aktif) ?></span>
        <?php endif; ?>
    </div>

    <?php if (!empty($pesan_sukses)): ?>
        <div class="success-msg"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($pesan_sukses) ?></div>
    <?php endif; ?>
    <?php if (!empty($list_sesi)): ?>
    <div class="filter-section">
        <label class="filter-label"><i class="fas fa-book"></i> Pilih Sesi untuk Diedit</label>
        <form method="GET" id="filterForm">
            <select name="sesi" class="filter-select" onchange="document.getElementById('filterForm').submit()">
                <option value="">-- Pilih Sesi Matakuliah --</option>
                <?php foreach ($list_sesi as $sesi): ?>
                    <option value="<?= htmlspecialchars($sesi['id_sesi'], ENT_QUOTES) ?>" <?= $id_sesi_aktif === $sesi['id_sesi'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($sesi['mata_kuliah'] ?? 'N/A') ?> - <?= htmlspecialchars($sesi['nama_kegiatan'] ?? '') ?> (<?= htmlspecialchars($sesi['tanggal'] ?? '') ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
    <?php endif; ?>

    <?php if (!empty($data_absensi)): ?>
    <div class="stat-row">
        <div class="stat-card">
            <div class="stat-num s-hadir"><?= $jumlah_hadir ?></div>
            <div class="stat-label">Hadir</div>
        </div>
        <div class="stat-card">
            <div class="stat-num s-alpha"><?= $jumlah_alpha ?></div>
            <div class="stat-label">Alpha</div>
        </div>
        <div class="stat-card">
            <div class="stat-num s-total"><?= count($data_absensi) ?></div>
            <div class="stat-label">Total</div>
        </div>
        <div class="stat-card">
            <div class="stat-num s-hadir"><?= $jumlah_lokasi_dalam ?></div>
            <div class="stat-label">Dalam Radius</div>
        </div>
        <div class="stat-card">
            <div class="stat-num s-alpha"><?= $jumlah_lokasi_luar ?></div>
            <div class="stat-label">Luar Radius</div>
        </div>
    </div>
    <?php endif; ?>

    <form method="POST" action="" style="width:100%">
        <input type="hidden" name="id_sesi_aktif" value="<?= htmlspecialchars($id_sesi_aktif, ENT_QUOTES) ?>">
        <div class="table-card" style="padding:0; overflow:hidden;">
            <div class="table-container" style="border:none; border-radius:0;">
                <table>
                    <thead>
                        <tr>
                            <th style="width:5%">No</th>
                            <th class="col-nama" style="width:25%; text-align:left; padding-left:14px">Nama</th>
                            <th style="width:16%">NIM</th>
                            <th style="width:12%">Waktu</th>
                            <th style="width:22%">Keterangan</th>
                            <th style="width:20%">Lokasi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($data_absensi)): ?>
                            <tr><td colspan="6" class="empty-state">Belum ada mahasiswa terdaftar.</td></tr>
                        <?php else: ?>
                            <?php $no = 1; foreach ($data_absensi as $row):
                                $status = $row['keterangan'] ?: 'Alpha';
                                $username_attr = htmlspecialchars($row['username'], ENT_QUOTES);
                                $status_class = htmlspecialchars(strtolower($status), ENT_QUOTES);
                                $status_lokasi = $row['status_lokasi'] ?: 'Belum Dicek';
                                $lokasi_class = $status_lokasi === 'Dalam Jangkauan'
                                    ? 'badge-lokasi-ok'
                                    : ($status_lokasi === 'Di Luar Jangkauan' ? 'badge-lokasi-out' : 'badge-lokasi-none');
                                $jarak_text = $row['jarak_meter'] !== null ? ' (' . number_format((float)$row['jarak_meter'], 0, ',', '.') . ' m)' : '';
                                $waktu  = $row['waktu_scan'] ?: '–';
                                $nim    = $row['nim'] ?: '–';
                            ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td class="col-nama"><?= htmlspecialchars($row['username']) ?></td>
                                <td><?= htmlspecialchars($nim) ?></td>
                                <td><?= htmlspecialchars($waktu) ?></td>
                                <td style="display:flex; gap:6px; align-items:center;">
                                    <input type="hidden" name="id_absensi[<?= $username_attr ?>]" value="<?= htmlspecialchars($row['id_absensi'] ?? '', ENT_QUOTES) ?>">
                                    <select name="keterangan[<?= $username_attr ?>]" class="status-select <?= $status_class ?>"
                                            onchange="updateColor(this)" style="flex:1;">
                                        <option value="Hadir"     <?= $status=='Hadir'     ? 'selected':'' ?>>Hadir</option>
                                        <option value="Sakit"     <?= $status=='Sakit'     ? 'selected':'' ?>>Sakit</option>
                                        <option value="Alpha"     <?= $status=='Alpha'     ? 'selected':'' ?>>Alpha</option>
                                        <option value="Izin"      <?= $status=='Izin'      ? 'selected':'' ?>>Izin</option>
                                        <option value="Terlambat" <?= $status=='Terlambat' ? 'selected':'' ?>>Terlambat</option>
                                    </select>
                                    <?php if ($row['id_absensi']): ?>
                                    <button type="button" class="btn-delete" onclick="deleteAbsensi(<?= (int)$row['id_absensi'] ?>, this)" title="Hapus data"><i class="fas fa-trash"></i></button>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge <?= $lokasi_class ?>"><?= htmlspecialchars($status_lokasi . $jarak_text) ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if (!empty($data_absensi)): ?>
            <div class="btn-container">
                <button type="submit" class="btn-update"><i class="fas fa-save"></i> Simpan Perubahan</button>
            </div>
        <?php endif; ?>
    </form>
</main>

<script>
function toggleMenu() { document.getElementById("dropdownMenu").classList.toggle("show"); }
window.onclick = function(e) {
    if (!e.target.closest('.menu-container')) {
        document.querySelectorAll('.dropdown-menu').forEach(m => m.classList.remove('show'));
    }
}
function updateColor(el) {
    el.className = 'status-select ' + el.value.toLowerCase();
}
function deleteAbsensi(id, btn) {
    if (!confirm('Yakin ingin menghapus data absensi ini?')) return;
    
    fetch('edit.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=delete&id_absensi=' + encodeURIComponent(id)
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'success') {
            btn.closest('tr').remove();
            alert('Data berhasil dihapus');
        } else {
            alert('Gagal menghapus: ' + data.message);
        }
    })
    .catch(err => {
        console.error('Error:', err);
        alert('Terjadi kesalahan');
    });
}
</script>
</body>
</html>
