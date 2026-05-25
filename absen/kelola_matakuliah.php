<?php
session_start();
require 'koneksi.php';

if (!isset($_SESSION['username']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: index.php");
    exit();
}

$nama_admin = ucfirst($_SESSION['username']);
$error = '';
$success = '';

// Handle tambah mata kuliah
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $nama_matkul = trim($_POST['nama_matkul'] ?? '');
    $kode_matkul = trim($_POST['kode_matkul'] ?? '');

    if (empty($nama_matkul)) {
        $error = "Nama mata kuliah tidak boleh kosong";
    } else {
        $stmt = $conn->prepare("INSERT INTO mata_kuliah (nama_matkul, kode_matkul) VALUES (?, ?)");
        $stmt->bind_param("ss", $nama_matkul, $kode_matkul);
        if ($stmt->execute()) {
            $success = "Mata kuliah berhasil ditambahkan";
        } else {
            if (stripos($stmt->error, 'Duplicate') !== false) {
                $error = "Mata kuliah sudah ada";
            } else {
                $error = "Error: " . $stmt->error;
            }
        }
    }
}

// Handle delete mata kuliah
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    header('Content-Type: application/json');
    $id_matkul = (int)($_POST['id_matkul'] ?? 0);
    if ($id_matkul <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'ID mata kuliah tidak valid']);
        exit();
    }

    $stmt = $conn->prepare("DELETE FROM mata_kuliah WHERE id_matkul = ?");
    $stmt->bind_param("i", $id_matkul);
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Mata kuliah berhasil dihapus']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Gagal menghapus mata kuliah']);
    }
    exit();
}

// Fetch semua mata kuliah
$data_matkul = [];
$stmt_fetch = $conn->query("SELECT id_matkul, nama_matkul, kode_matkul, dibuat_pada FROM mata_kuliah ORDER BY nama_matkul ASC");
if ($stmt_fetch) {
    while($row = $stmt_fetch->fetch_assoc()) {
        $data_matkul[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Mata Kuliah – Absensi Digital</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .table-matkul { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .table-matkul thead { background: var(--primary); color: white; }
        .table-matkul th, .table-matkul td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        .table-matkul tbody tr:hover { background: #f5f5f5; }
        .btn-delete-matkul { background: var(--danger); color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 12px; }
        .btn-delete-matkul:hover { opacity: 0.9; }
        .form-row { display: grid; grid-template-columns: 2fr 1fr auto; gap: 12px; align-items: end; }
        .empty-state { text-align: center; padding: 30px; color: #999; }
        @media (max-width: 600px) {
            .form-row { grid-template-columns: 1fr; }
            .table-matkul { font-size: 12px; }
        }
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
            <a href="edit.php"><i class="fas fa-edit"></i> Edit Absensi</a>
            <a href="qr.php"><i class="fas fa-qrcode"></i> Lihat QR Aktif</a>
            <a href="kelola_matakuliah.php" class="active-menu"><i class="fas fa-book"></i> Kelola Mata Kuliah</a>
            <a href="logout.php" style="color:var(--danger)"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>
</header>

<main class="main-content w-500">
    <div class="page-header">
        <h2>Kelola Mata Kuliah</h2>
        <p>Tambah, ubah, atau hapus data mata kuliah</p>
    </div>

    <div class="form-card">
        <?php if ($error): ?>
            <div class="error-msg"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="success-msg"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form action="" method="POST">
            <input type="hidden" name="action" value="add">
            <div class="form-row">
                <div class="form-group" style="margin: 0;">
                    <label style="font-size: 12px;">Nama Mata Kuliah</label>
                    <input type="text" name="nama_matkul" class="form-control" required placeholder="Contoh: Pemrograman Web">
                </div>
                <div class="form-group" style="margin: 0;">
                    <label style="font-size: 12px;">Kode</label>
                    <input type="text" name="kode_matkul" class="form-control" placeholder="Contoh: IF401">
                </div>
                <button type="submit" class="btn-primary" style="padding: 8px 16px;"><i class="fas fa-plus"></i> Tambah</button>
            </div>
        </form>
    </div>

    <div class="form-card">
        <h3 style="margin-top: 0;">Data Mata Kuliah (<?= count($data_matkul) ?>)</h3>
        
        <?php if (empty($data_matkul)): ?>
            <div class="empty-state">
                <i class="fas fa-inbox" style="font-size: 40px; opacity: 0.5;"></i>
                <p>Belum ada mata kuliah. Tambahkan yang pertama!</p>
            </div>
        <?php else: ?>
            <table class="table-matkul">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Nama Mata Kuliah</th>
                        <th>Kode</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $no = 1; foreach ($data_matkul as $row): ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td><?= htmlspecialchars($row['nama_matkul']) ?></td>
                        <td><?= htmlspecialchars($row['kode_matkul'] ?? '–') ?></td>
                        <td>
                            <button type="button" class="btn-delete-matkul" onclick="deleteMatkul(<?= (int)$row['id_matkul'] ?>, this)">
                                <i class="fas fa-trash"></i> Hapus
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</main>

<script>
function toggleMenu() { document.getElementById("dropdownMenu").classList.toggle("show"); }
window.onclick = function(e) {
    if (!e.target.closest('.menu-container')) {
        document.querySelectorAll('.dropdown-menu').forEach(m => m.classList.remove('show'));
    }
}

function deleteMatkul(id, btn) {
    if (!confirm('Yakin ingin menghapus mata kuliah ini?')) return;
    
    fetch('kelola_matakuliah.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=delete&id_matkul=' + encodeURIComponent(id)
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'success') {
            btn.closest('tr').remove();
            alert('Mata kuliah berhasil dihapus');
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
