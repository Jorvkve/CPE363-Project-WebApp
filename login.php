<?php
// === 1. นำเข้าไฟล์ตั้งค่าและตรวจสอบสถานะการล็อกอิน ===
require_once 'config.php';

// ถ้าผู้ใช้ล็อกอินอยู่แล้ว ให้เปลี่ยนหน้าไปที่หน้าหลัก (index.php) ทันที
if (isLoggedIn()) {
    header("Location: index.php");
    exit();
}

// === 2. จัดการเมื่อผู้ใช้กดปุ่มเข้าสู่ระบบ (เมื่อมีการส่งฟอร์มแบบ POST) ===
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    // เช็คว่ากรอกข้อมูลครบหรือไม่
    if (!$username || !$password) {
        $error = 'กรุณากรอก Username และรหัสผ่าน';
    } else {
        // === 3. ค้นหาข้อมูลผู้ใช้งานจากฐานข้อมูล (รองรับทั้ง username และ email) ===
        $stmt = $conn->prepare("SELECT id, username, password, fullname, is_admin FROM users WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $username, $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        // === 4. ตรวจสอบรหัสผ่าน และสร้าง Session หากข้อมูลถูกต้อง ===
        // เช็คว่ามีผู้ใช้นี้อยู่จริง และรหัสผ่านที่กรอกตรงกับรหัสผ่านที่เข้ารหัสไว้ (hash) หรือไม่
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id']  = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['fullname'] = $user['fullname'];
            $_SESSION['is_admin'] = $user['is_admin'];

            // ให้เปลี่ยนหน้ากลับไปหน้าที่ผู้ใช้ตั้งใจจะเข้าก่อนหน้านี้ (ถ้ามี) หรือไปหน้าหลัก
            $redirect = $_GET['redirect'] ?? 'index.php';
            header("Location: " . $redirect);
            exit();
        } else {
            $error = 'Username/Email หรือรหัสผ่านไม่ถูกต้อง';
        }
    }
}
?>
<!-- === 5. เริ่มต้นโครงสร้างหน้าเว็บ (HTML) === -->
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ - CineMax</title>
    <!-- === 6. นำเข้าไฟล์สไตล์ (CSS) === -->
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<!-- === 7. แถบเมนูด้านบน (Navigation Bar) === -->
<nav>
    <a href="index.php" class="nav-logo">CINE<span>MAX</span></a>
    <ul class="nav-links">
        <li><a href="index.php">หน้าแรก</a></li>
        <li><a href="login.php" class="active">เข้าสู่ระบบ</a></li>
        <li><a href="register.php" class="btn-nav">สมัครสมาชิก</a></li>
    </ul>
</nav>

<!-- === 8. พื้นที่สำหรับฟอร์มเข้าสู่ระบบ === -->
<div style="min-height:calc(100vh - 64px); display:flex; align-items:center; padding:3rem 1rem; background:radial-gradient(ellipse at top, rgba(229,9,20,0.08), transparent);">
    <div class="form-card" style="width:100%;">
        <div style="text-align:center;font-size:3rem;margin-bottom:1rem;">🎬</div>
        <h1 class="form-title">เข้าสู่ระบบ</h1>
        <p class="form-subtitle">ยินดีต้อนรับกลับสู่ CineMax</p>

        <?php if ($error): ?>
            <div class="alert alert-error">❌ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if (isset($_GET['redirect'])): ?>
            <div class="alert alert-error">⚠️ กรุณาเข้าสู่ระบบก่อนจองตั๋ว</div>
        <?php endif; ?>

        <!-- แบบฟอร์มกรอกข้อมูล -->
        <form method="POST">
            <div class="form-group">
                <label>Username หรือ Email</label>
                <input type="text" name="username" placeholder="username หรือ email@example.com"
                       value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required autofocus>
            </div>

            <div class="form-group">
                <label>รหัสผ่าน</label>
                <input type="password" name="password" placeholder="••••••••" required>
            </div>

            <button type="submit" class="btn btn-primary btn-full" style="margin-top:0.5rem;">
                🔓 เข้าสู่ระบบ
            </button>
        </form>

        <p style="text-align:center;margin-top:1.5rem;font-size:0.9rem;color:var(--text-dim);">
            ยังไม่มีบัญชี? <a href="register.php" style="color:var(--accent);font-weight:600;">สมัครสมาชิกฟรี</a>
        </p>
    </div>
</div>

<!-- === 9. ส่วนท้ายของหน้าเว็บ (Footer) === -->
<footer>
    <strong>CINEMAX</strong> &copy; <?= date('Y') ?>
</footer>

</body>
</html>
