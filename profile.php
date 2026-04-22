<?php
require_once 'config.php';
requireLogin();

// ดึงข้อมูล user จาก DB
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// สถิติการจอง
$stats_stmt = $conn->prepare("
    SELECT COUNT(*) as total_bookings,
           SUM(CASE WHEN status='confirmed' THEN total_price ELSE 0 END) as total_spent,
           SUM(CASE WHEN status='confirmed' THEN vip_count ELSE 0 END) as total_vip,
           SUM(CASE WHEN status='confirmed' THEN normal_count ELSE 0 END) as total_normal
    FROM bookings WHERE user_id = ?
");
$stats_stmt->bind_param("i", $_SESSION['user_id']);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();

$error = $success = '';

// อัปเดตข้อมูลส่วนตัว
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'update_profile') {
        $fullname = trim($_POST['fullname'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $phone    = trim($_POST['phone'] ?? '');

        if (!$fullname || !$email) {
            $error = 'กรุณากรอกชื่อและ Email';
        } else {
            // เช็ค email ซ้ำ (ยกเว้น user ตัวเอง)
            $chk = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $chk->bind_param("si", $email, $_SESSION['user_id']);
            $chk->execute();
            if ($chk->get_result()->num_rows > 0) {
                $error = 'Email นี้มีผู้ใช้งานแล้ว';
            } else {
                $upd = $conn->prepare("UPDATE users SET fullname=?, email=?, phone=? WHERE id=?");
                $upd->bind_param("sssi", $fullname, $email, $phone, $_SESSION['user_id']);
                if ($upd->execute()) {
                    $_SESSION['fullname'] = $fullname;
                    $user['fullname'] = $fullname;
                    $user['email']    = $email;
                    $user['phone']    = $phone;
                    $success = 'อัปเดตข้อมูลสำเร็จ';
                } else {
                    $error = 'เกิดข้อผิดพลาด';
                }
            }
        }
    }

    if ($_POST['action'] === 'change_password') {
        $old_pw  = $_POST['old_password']  ?? '';
        $new_pw  = $_POST['new_password']  ?? '';
        $conf_pw = $_POST['confirm_password'] ?? '';

        if (!$old_pw || !$new_pw || !$conf_pw) {
            $error = 'กรุณากรอกข้อมูลรหัสผ่านให้ครบ';
        } elseif (!password_verify($old_pw, $user['password'])) {
            $error = 'รหัสผ่านเดิมไม่ถูกต้อง';
        } elseif ($new_pw !== $conf_pw) {
            $error = 'รหัสผ่านใหม่ไม่ตรงกัน';
        } elseif (strlen($new_pw) < 6) {
            $error = 'รหัสผ่านใหม่ต้องมีอย่างน้อย 6 ตัว';
        } else {
            $hashed = password_hash($new_pw, PASSWORD_DEFAULT);
            $upd = $conn->prepare("UPDATE users SET password=? WHERE id=?");
            $upd->bind_param("si", $hashed, $_SESSION['user_id']);
            if ($upd->execute()) {
                $success = 'เปลี่ยนรหัสผ่านสำเร็จ';
                $user['password'] = $hashed;
            } else {
                $error = 'เกิดข้อผิดพลาด';
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
    <title>โปรไฟล์ - CineMax</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .tab-bar { display:flex; gap:0.5rem; margin-bottom:2rem; border-bottom:1px solid var(--border); }
        .tab-btn { padding:0.7rem 1.4rem; background:none; border:none; color:var(--text-dim); font-family:'Outfit',sans-serif; font-size:0.95rem; font-weight:600; cursor:pointer; border-bottom:2px solid transparent; margin-bottom:-1px; transition:all 0.2s; }
        .tab-btn.active { color:var(--accent); border-color:var(--accent); }
        .tab-content { display:none; } .tab-content.active { display:block; }
        .stat-box { background:var(--bg-card); border:1px solid var(--border); border-radius:12px; padding:1.2rem 1.5rem; text-align:center; }
        .stat-num { font-family:'Bebas Neue',cursive; font-size:2.2rem; line-height:1; }
        .stat-label { color:var(--text-dim); font-size:0.8rem; margin-top:0.3rem; }
        .avatar-big { width:80px; height:80px; background:var(--accent); border-radius:50%; display:flex; align-items:center; justify-content:center; font-family:'Bebas Neue',cursive; font-size:2.5rem; color:#fff; margin:0 auto 1rem; }
    </style>
</head>
<body>

<nav>
    <a href="index.php" class="nav-logo">CINE<span>MAX</span></a>
    <ul class="nav-links">
        <li><a href="index.php">หน้าแรก</a></li>
        <li><a href="mybooking.php">ตั๋วของฉัน</a></li>
        <li><a href="profile.php" class="active" style="display:flex;align-items:center;gap:0.5rem;">
            <div class="user-avatar"><?= strtoupper(substr($user['username'],0,1)) ?></div>
            <?= htmlspecialchars($user['username']) ?>
        </a></li>
        <?php if(isAdmin()): ?>
        <li><a href="admin.php" style="color:var(--gold);">⚙️ Admin</a></li>
        <?php endif; ?>
        <li><a href="logout.php" class="btn-nav">ออกจากระบบ</a></li>
    </ul>
</nav>

<div class="page-header">
    <div class="page-header-inner">
        <h1>👤 โปรไฟล์ของฉัน</h1>
        <p>จัดการข้อมูลส่วนตัวและรหัสผ่าน</p>
    </div>
</div>

<div class="section" style="max-width:800px;">

    <?php if ($error): ?><div class="alert alert-error">❌ <?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div><?php endif; ?>

    <!-- Avatar + Stats -->
    <div style="display:grid;grid-template-columns:200px 1fr;gap:2rem;align-items:start;margin-bottom:2rem;">
        <div style="text-align:center;">
            <div class="avatar-big"><?= strtoupper(substr($user['username'],0,1)) ?></div>
            <div style="font-weight:700;font-size:1.1rem;"><?= htmlspecialchars($user['fullname']) ?></div>
            <div style="color:var(--text-dim);font-size:0.85rem;">@<?= htmlspecialchars($user['username']) ?></div>
            <?php if ($user['is_admin']): ?>
            <div style="margin-top:0.5rem;"><span class="status-badge" style="background:rgba(245,197,24,0.15);color:var(--gold);">⭐ Admin</span></div>
            <?php endif; ?>
            <div style="color:var(--text-dim);font-size:0.75rem;margin-top:0.5rem;">สมาชิกตั้งแต่ <?= date('M Y', strtotime($user['created_at'])) ?></div>
        </div>

        <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:1rem;">
            <div class="stat-box">
                <div class="stat-num" style="color:var(--accent);"><?= $stats['total_bookings'] ?? 0 ?></div>
                <div class="stat-label">การจองทั้งหมด</div>
            </div>
            <div class="stat-box">
                <div class="stat-num" style="color:var(--gold);">฿<?= number_format($stats['total_spent'] ?? 0, 0) ?></div>
                <div class="stat-label">ยอดรวมที่ใช้</div>
            </div>
            <div class="stat-box">
                <div class="stat-num" style="color:var(--gold);"><?= $stats['total_vip'] ?? 0 ?></div>
                <div class="stat-label">ที่นั่ง VIP</div>
            </div>
            <div class="stat-box">
                <div class="stat-num"><?= $stats['total_normal'] ?? 0 ?></div>
                <div class="stat-label">ที่นั่ง Normal</div>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="tab-bar">
        <button class="tab-btn active" onclick="showTab('profile')">📋 ข้อมูลส่วนตัว</button>
        <button class="tab-btn" onclick="showTab('password')">🔒 เปลี่ยนรหัสผ่าน</button>
    </div>

    <!-- Tab: Profile -->
    <div class="tab-content active" id="tab-profile">
        <div class="form-card" style="max-width:100%;">
            <form method="POST">
                <input type="hidden" name="action" value="update_profile">
                <div class="form-row">
                    <div class="form-group">
                        <label>ชื่อ-นามสกุล</label>
                        <input type="text" name="fullname" value="<?= htmlspecialchars($user['fullname']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" value="<?= htmlspecialchars($user['username']) ?>" disabled style="opacity:0.5;cursor:not-allowed;">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>เบอร์โทรศัพท์</label>
                        <input type="tel" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" placeholder="08X-XXX-XXXX">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">💾 บันทึกข้อมูล</button>
            </form>
        </div>
    </div>

    <!-- Tab: Password -->
    <div class="tab-content" id="tab-password">
        <div class="form-card" style="max-width:100%;">
            <form method="POST">
                <input type="hidden" name="action" value="change_password">
                <div class="form-group">
                    <label>รหัสผ่านเดิม</label>
                    <input type="password" name="old_password" placeholder="••••••••" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>รหัสผ่านใหม่</label>
                        <input type="password" name="new_password" placeholder="อย่างน้อย 6 ตัว" required>
                    </div>
                    <div class="form-group">
                        <label>ยืนยันรหัสผ่านใหม่</label>
                        <input type="password" name="confirm_password" placeholder="กรอกอีกครั้ง" required>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">🔒 เปลี่ยนรหัสผ่าน</button>
            </form>
        </div>
    </div>

    <div style="margin-top:2rem;">
        <a href="mybooking.php" class="btn btn-outline">🎟️ ดูประวัติการจอง</a>
    </div>
</div>

<footer><strong>CINEMAX</strong> &copy; <?= date('Y') ?></footer>

<script>
function showTab(name) {
    document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('tab-' + name).classList.add('active');
    event.target.classList.add('active');
}
<?php if ($error && isset($_POST['action']) && $_POST['action']==='change_password'): ?>
showTab('password'); document.querySelector('[onclick="showTab(\'password\')"]').classList.add('active');
document.querySelector('[onclick="showTab(\'profile\')"]').classList.remove('active');
<?php endif; ?>
</script>
</body>
</html>
