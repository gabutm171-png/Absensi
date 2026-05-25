<?php
session_start();
if (!isset($_SESSION['username']) || ($_SESSION['role'] ?? '') !== 'user') {
    header("Location: index.php");
    exit();
}

$nama_user = ucfirst($_SESSION['username']);
$nim_user = !empty($_SESSION['nim']) ? $_SESSION['nim'] : "NIM Belum Diatur";
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scan QR – Absensi Digital</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    <style>
        #reader { border-radius: var(--radius-md); overflow:hidden; border: 2px solid var(--border); }
        #reader video { border-radius: var(--radius-md); }
        .scan-hint {
            display: flex; align-items: center; gap: 8px;
            background: var(--primary-light); color: var(--primary);
            font-size: 13px; font-weight: 500;
            padding: 10px 14px; border-radius: var(--radius-sm);
            border: 1px solid var(--primary-muted);
            margin-bottom: 16px; width: 100%;
        }
    </style>
</head>
<body>

<header class="header">
    <div class="header-title"><i class="fas fa-qrcode"></i> Scan Absensi</div>
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
    <div class="scan-hint">
        <i class="fas fa-info-circle"></i>
        Arahkan kamera ke QR Code dan izinkan akses lokasi saat diminta.
    </div>

    <div id="reader" style="width:100%; margin-bottom:16px;"></div>
    <div class="btn-container">
        <button id="stopScanBtn" class="btn btn-outline" onclick="stopScan()">Berhenti Scan</button>
        <button id="startScanBtn" class="btn btn-primary hidden" onclick="startScan()">Mulai Scan</button>
    </div>
    <div id="result-message" class="result-box"></div>
</main>

<div class="bottom-nav">
    <a href="scan.php" class="nav-item active">
        <i class="fas fa-expand"></i>
        <span>Scan</span>
    </a>
    <a href="riwayat.php" class="nav-item">
        <i class="fas fa-history"></i>
        <span>Riwayat</span>
    </a>
</div>

<script>
let scanner;
let scanInProgress = false;
const scanOptions = { fps: 10, qrbox: { width: 240, height: 240 } };
const stopBtn = document.getElementById('stopScanBtn');
const startBtn = document.getElementById('startScanBtn');
const resultBox = document.getElementById('result-message');

function initScanner() {
    scanner = new Html5QrcodeScanner("reader", scanOptions, false);
    scanner.render(onScanSuccess, onScanError);
    stopBtn.disabled = false;
    startBtn.classList.add('hidden');
}

function renderScanner() {
    if (scanner && typeof scanner.clear === 'function') {
        scanner.clear().then(initScanner).catch(initScanner);
    } else {
        initScanner();
    }
}

function showResult(message, type) {
    resultBox.style.display = 'block';
    resultBox.className = `result-box ${type}`;
    resultBox.textContent = '';

    const icon = document.createElement('i');
    icon.className = type === 'success'
        ? 'fas fa-check-circle'
        : type === 'error'
            ? 'fas fa-times-circle'
            : 'fas fa-info-circle';

    resultBox.appendChild(icon);
    resultBox.appendChild(document.createTextNode(' ' + message));
}

function hideResult() {
    resultBox.style.display = 'none';
}

function onScanError(errorMessage) {
    // Ignore non-fatal scan errors.
}

function onScanSuccess(decodedText) {
    if (scanInProgress) return;
    scanInProgress = true;

    if (scanner && typeof scanner.pause === 'function') {
        scanner.pause();
    }

    showResult('Mengambil lokasi...', 'info');

    if (!navigator.geolocation) {
        showResult('Browser tidak mendukung deteksi lokasi.', 'error');
        scanInProgress = false;
        setTimeout(resumeScanner, 3000);
        return;
    }

    navigator.geolocation.getCurrentPosition(
        position => submitScan(decodedText, position),
        () => {
            showResult('Lokasi tidak bisa dibaca. Izinkan akses lokasi lalu scan ulang.', 'error');
            scanInProgress = false;
            setTimeout(resumeScanner, 3000);
        },
        { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
    );
}

function submitScan(decodedText, position) {
    const body = new URLSearchParams({
        id_sesi: decodedText,
        latitude: position.coords.latitude,
        longitude: position.coords.longitude,
        akurasi_meter: position.coords.accuracy || 0
    });

    fetch('proses_scan.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'success') {
            showResult(data.message, 'success');
        } else {
            showResult(data.message, 'error');
            setTimeout(() => {
                resumeScanner();
                hideResult();
            }, 3000);
        }
        scanInProgress = false;
    })
    .catch(() => {
        scanInProgress = false;
        resumeScanner();
    });
}

function resumeScanner() {
    if (scanner && typeof scanner.resume === 'function') {
        scanner.resume();
    }
}

function stopScan() {
    if (!scanner) return;
    if (typeof scanner.pause === 'function') {
        scanner.pause();
    }
    stopBtn.disabled = true;
    startBtn.classList.remove('hidden');
    showResult('Pemindaian dihentikan. Tekan Mulai Scan untuk lanjut.', 'info');
}

function startScan() {
    if (!scanner || typeof scanner.resume !== 'function') {
        renderScanner();
    } else {
        scanner.resume();
    }
    stopBtn.disabled = false;
    startBtn.classList.add('hidden');
    scanInProgress = false;
    hideResult();
}

renderScanner();
</script>
</body>
</html>
