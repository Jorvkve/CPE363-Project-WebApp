<?php
require_once 'config.php';

if (isLoggedIn()) {
    header("Location: index.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $fullname = trim($_POST['fullname'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    // Validate
    if (!$username || !$email || !$fullname || !$password) {
        $error = 'กรุณากรอกข้อมูลให้ครบถ้วน';
    } elseif ($password !== $confirm) {
        $error = 'รหัสผ่านไม่ตรงกัน';
    } elseif (strlen($password) < 6) {
        $error = 'รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร';
    } else {
        // เช็ค username/email ซ้ำ
        $check = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $check->bind_param("ss", $username, $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $error = 'Username หรือ Email นี้มีผู้ใช้งานแล้ว';
        } else {
            // บันทึกลง DB
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (username, email, password, fullname, phone) VALUES (?,?,?,?,?)");
            $stmt->bind_param("sssss", $username, $email, $hashed, $fullname, $phone);

            if ($stmt->execute()) {
                $success = 'สมัครสมาชิกสำเร็จ! กรุณาเข้าสู่ระบบ';
            } else {
                $error = 'เกิดข้อผิดพลาด กรุณาลองใหม่';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สมัครสมาชิก - CineMax</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<nav>
    <a href="index.php" class="nav-logo">CINE<span>MAX</span></a>
    <ul class="nav-links">
        <li><a href="index.php">หน้าแรก</a></li>
        <li><a href="login.php">เข้าสู่ระบบ</a></li>
        <li><a href="register.php" class="btn-nav">สมัครสมาชิก</a></li>
    </ul>
</nav>

<div style="min-height:calc(100vh - 64px); display:flex; align-items:center; padding:3rem 1rem; background:radial-gradient(ellipse at top, rgba(229,9,20,0.08), transparent);">
    <div class="form-card" style="width:100%;">
        <div style="text-align:center;font-size:3rem;margin-bottom:1rem;">🎬</div>
        <h1 class="form-title">สมัครสมาชิก</h1>
        <p class="form-subtitle">สร้างบัญชีเพื่อจองตั๋วหนัง</p>

        <?php if ($error): ?>
            <div class="alert alert-error">❌ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">✅ <?= htmlspecialchars($success) ?>
                <br><a href="login.php" style="color:inherit;font-weight:700;">→ ไปหน้าเข้าสู่ระบบ</a>
            </div>
        <?php else: ?>

        <form method="POST" autocomplete="off">
            <div class="form-row">
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" placeholder="your_username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label>ชื่อ-นามสกุล</label>
                    <input type="text" name="fullname" placeholder="สมชาย ใจดี" value="<?= htmlspecialchars($_POST['fullname'] ?? '') ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" placeholder="you@email.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
            </div>

            <div class="form-group">
                <label>เบอร์โทรศัพท์</label>
                <input type="tel" name="phone" placeholder="08X-XXX-XXXX" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>รหัสผ่าน</label>
                    <input type="password" name="password" placeholder="อย่างน้อย 6 ตัว" required>
                </div>
                <div class="form-group">
                    <label>ยืนยันรหัสผ่าน</label>
                    <input type="password" name="confirm_password" placeholder="กรอกอีกครั้ง" required>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-full" style="margin-top:0.5rem;">
                🎟️ สมัครสมาชิก
            </button>
        </form>

        <?php endif; ?>

        <p style="text-align:center;margin-top:1.5rem;font-size:0.9rem;color:var(--text-dim);">
            มีบัญชีแล้ว? <a href="login.php" style="color:var(--accent);font-weight:600;">เข้าสู่ระบบ</a>
        </p>
    </div>
</div>

<footer>
    <strong>CINEMAX</strong> &copy; <?= date('Y') ?>
</footer>

</body>
</html>
