<?php
session_start();
require 'koneksi.php';

$error = '';
$show_login = false;
$active_role = 'user';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password_input = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'user';
    $allowed_roles = ['user', 'admin'];

    if (!in_array($role, $allowed_roles, true)) {
        $role = 'user';
    }

    $password = md5($password_input);

    $stmt = $conn->prepare("SELECT * FROM users WHERE username=? AND password=? AND role=?");
    $stmt->bind_param("sss", $username, $password, $role);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user_data = $result->fetch_assoc();
        session_regenerate_id(true);
        $_SESSION['username'] = $user_data['username'];
        $_SESSION['role'] = $user_data['role'];
        $_SESSION['nim'] = $user_data['nim'];

        if ($role == 'user') {
            header("Location: scan.php");
        } else {
            header("Location: buat_sesi.php");
        }
        exit();
    } else {
        $error = "Username atau kata sandi salah!";
        $show_login = true;
        $active_role = $role;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Absensi Digital</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { justify-content: center; padding: 20px; }
        .login-wrap { width: 100%; max-width: 400px; }
        .brand-icon {
            width: 56px; height: 56px; border-radius: 16px;
            background: var(--primary); display: flex; align-items: center;
            justify-content: center; margin: 0 auto 16px; color: #fff; font-size: 22px;
        }
        .divider-text {
            display: flex; align-items: center; gap: 12px;
            color: var(--text-muted); font-size: 12px; margin: 20px 0;
        }
        .divider-text::before, .divider-text::after {
            content: ''; flex: 1; height: 1px; background: var(--border);
        }
        .role-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 8px; }
        .role-btn {
            display: flex; flex-direction: column; align-items: center; gap: 6px;
            padding: 18px 12px; border-radius: var(--radius-md);
            border: 1.5px solid var(--border); background: var(--surface-2);
            cursor: pointer; font-family: inherit; font-size: 13px; font-weight: 600;
            color: var(--text-secondary); transition: all 0.2s;
        }
        .role-btn i { font-size: 22px; color: var(--primary); }
        .role-btn:hover { border-color: var(--primary); background: var(--primary-light); color: var(--primary); }
        .back-link {
            display: inline-flex; align-items: center; gap: 6px;
            color: var(--text-muted); font-size: 13px; cursor: pointer;
            margin-top: 20px; text-decoration: none; transition: color 0.2s;
        }
        .back-link:hover { color: var(--primary); }
        .mode-chip {
            display: inline-flex; align-items: center; gap: 6px;
            background: var(--primary-light); color: var(--primary);
            font-size: 12px; font-weight: 600; padding: 4px 12px;
            border-radius: 99px; margin-bottom: 20px;
        }
        .form-group label { display: block; font-size: 12px; font-weight: 600;
            color: var(--text-secondary); text-transform: uppercase;
            letter-spacing: 0.5px; margin-bottom: 6px; }
        .form-control-icon { position: relative; }
        .form-control-icon i {
            position: absolute; left: 12px; top: 50%; transform: translateY(-50%);
            color: var(--text-muted); font-size: 14px; pointer-events: none;
        }
        .form-control-icon .form-control { padding-left: 36px; }
    </style>
</head>
<body>
<div class="login-wrap">

    <!-- PILIH MODE -->
    <div class="container <?= $show_login ? 'hidden' : '' ?>" id="page-selection">
        <div class="brand-icon"><i class="fas fa-qrcode"></i></div>
        <h1 style="margin-bottom:4px;">Absensi Digital</h1>
        <p class="desc" style="margin-bottom:24px;">Sistem presensi berbasis QR Code</p>
        <div class="role-grid">
            <button class="role-btn" onclick="showLogin('user')">
                <i class="fas fa-user-graduate"></i>
                Login User
            </button>
            <button class="role-btn" onclick="showLogin('admin')">
                <i class="fas fa-user-shield"></i>
                Login Admin
            </button>
        </div>
    </div>

    <!-- FORM LOGIN -->
    <div class="container <?= $show_login ? '' : 'hidden' ?>" id="page-login">
        <div class="brand-icon"><i class="fas fa-sign-in-alt"></i></div>
        <h1 style="margin-bottom:4px;">Masuk Akun</h1>
        <div id="mode-chip" class="mode-chip">
            <i class="fas fa-<?= $active_role === 'admin' ? 'user-shield' : 'user-graduate' ?>"></i>
            <span id="mode-label"><?= ucfirst($active_role) ?></span>
        </div>

        <form action="" method="POST">
            <input type="hidden" name="role" id="role_input" value="<?= htmlspecialchars($active_role, ENT_QUOTES) ?>">

            <?php if ($error): ?>
                <div class="error-msg"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <div class="form-group">
                <label>Username</label>
                <div class="form-control-icon">
                    <i class="fas fa-user"></i>
                    <input type="text" name="username" class="form-control" placeholder="Masukkan username" required>
                </div>
            </div>
            <div class="form-group">
                <label>Kata Sandi</label>
                <div class="form-control-icon">
                    <i class="fas fa-lock"></i>
                    <input type="password" name="password" class="form-control" placeholder="Masukkan kata sandi" required>
                </div>
            </div>
            <button type="submit" class="btn-submit"><i class="fas fa-sign-in-alt"></i> Masuk</button>
        </form>

        <a class="back-link" onclick="showSelection()">
            <i class="fas fa-arrow-left"></i> Kembali pilih mode
        </a>
    </div>

</div>
<script>
    function showLogin(mode) {
        document.getElementById('role_input').value = mode;
        document.getElementById('mode-label').textContent = mode === 'admin' ? 'Admin' : 'User';
        document.querySelector('#mode-chip i').className = 'fas fa-' + (mode === 'admin' ? 'user-shield' : 'user-graduate');
        document.getElementById('page-selection').classList.add('hidden');
        document.getElementById('page-login').classList.remove('hidden');
    }
    function showSelection() {
        document.getElementById('page-login').classList.add('hidden');
        document.getElementById('page-selection').classList.remove('hidden');
    }
</script>
</body>
</html>
