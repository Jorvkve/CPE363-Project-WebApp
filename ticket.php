<?php
// === 1. นำเข้าไฟล์ตั้งค่าและตรวจสอบการล็อกอิน ===
require_once 'config.php';
requireLogin();

// === 2. รับค่า ID ของตั๋วจาก URL ===
$id = intval($_GET['id'] ?? 0);

// === 3. ค้นหาข้อมูลตั๋ว หนัง และรอบฉายจากฐานข้อมูล ===
$stmt = $conn->prepare("
SELECT b.*, m.title, s.show_date, s.show_time, s.hall
FROM bookings b
JOIN showtimes s ON b.showtime_id = s.id
JOIN movies m ON s.movie_id = m.id
WHERE b.id = ? AND b.user_id = ?
");
$stmt->bind_param("ii", $id, $_SESSION['user_id']);
$stmt->execute();
$b = $stmt->get_result()->fetch_assoc();

// === 4. ตรวจสอบว่าพบข้อมูลตั๋วหรือไม่ ===
if (!$b) {
    die("ไม่พบตั๋ว");
}
?>

<!-- === 5. เริ่มต้นโครงสร้างหน้าเว็บ (HTML) === -->
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>Ticket</title>
    
    <!-- === 6. จัดการความสวยงามของหน้าเว็บและตั๋ว (CSS) === -->
    <style>
    body {
        background: #0b0f1a;
        font-family: Arial;
        color: #fff;
        display: flex;
        justify-content: center;
        align-items: center;
        height: 100vh;
    }

    .ticket {
        width: 380px;
        background: #111827;
        border-radius: 15px;
        overflow: hidden;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.5);
    }

    .header {
        background: linear-gradient(90deg, #e50914, #7f1d1d);
        padding: 20px;
        text-align: center;
    }

    .header h1 {
        margin: 0;
    }

    .content {
        padding: 20px;
    }

    .row {
        display: flex;
        justify-content: space-between;
        margin-bottom: 10px;
        font-size: 14px;
    }

    .label {
        color: #9ca3af;
    }

    .value {
        font-weight: bold;
    }

    .qr {
        text-align: center;
        margin-top: 20px;
    }

    .qr img {
        background: #fff;
        padding: 10px;
        border-radius: 10px;
    }

    .footer {
        text-align: center;
        font-size: 12px;
        color: #9ca3af;
        padding: 15px;
    }
    </style>
</head>

<body>

    <!-- === 7. ปุ่มย้อนกลับไปหน้า My Booking === -->
    <div style="position:absolute;top:20px;left:20px;">
        <a href="mybooking.php"
            style="color:#fff;text-decoration:none;background:#000;padding:6px 12px;border-radius:6px;">
            ← กลับ
        </a>
    </div>

    <!-- === 8. ส่วนแสดงตั๋วหนังหลัก (กรอบตั๋ว) === -->
    <div class="ticket">

        <!-- === 9. ส่วนหัวของตั๋วหนัง === -->
        <div class="header">
            <h1>🎬 CineMax</h1>
            <p>Movie Ticket</p>
        </div>

        <!-- === 10. ส่วนข้อมูลรายละเอียดของตั๋ว === -->
        <div class="content">

            <div class="row">
                <span class="label">หนัง</span>
                <span class="value"><?= htmlspecialchars($b['title']) ?></span>
            </div>

            <div class="row">
                <span class="label">วันเวลา</span>
                <span class="value"><?= date('d/m/Y H:i', strtotime($b['show_date'] . ' ' . $b['show_time'])) ?></span>
            </div>

            <div class="row">
                <span class="label">ที่นั่ง</span>
                <span class="value"><?= htmlspecialchars($b['seats']) ?></span>
            </div>

            <div class="row">
                <span class="label">โรง</span>
                <span class="value"><?= htmlspecialchars($b['hall']) ?></span>
            </div>

            <div class="row">
                <span class="label">ราคา</span>
                <span class="value">฿<?= number_format($b['total_price']) ?></span>
            </div>

            <div class="row">
                <span class="label">Ticket ID</span>
                <span class="value">CMX-<?= $b['id'] ?></span>
            </div>

            <!-- === 11. ส่วนแสดง QR Code สำหรับสแกนหน้าโรง === -->
            <div class="qr">
                <p style="font-size:12px;color:#9ca3af;">แสดง QR นี้ที่หน้าโรง</p>
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=CINEMAX|<?= $b['id'] ?>" />
            </div>

        </div>

        <!-- === 12. ส่วนท้ายของตั๋วหนัง === -->
        <div class="footer">
            ขอบคุณที่ใช้บริการ CineMax 🙏
        </div>

    </div>

</body>

</html>