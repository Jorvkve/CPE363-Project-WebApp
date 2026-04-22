<?php
require_once 'config.php';
requireLogin();

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    header("Location: index.php");
    exit();
}

// ดึงข้อมูลการจอง + หนัง + รอบ
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

if (!$b) {
    header("Location: mybooking.php");
    exit();
}

// ถ้าจ่ายแล้ว redirect ไป mybooking
if ($b['payment_status'] === 'paid') {
    header("Location: mybooking.php?paid=1");
    exit();
}

// กดยืนยันการชำระเงิน
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $ref = 'PAY' . time() . rand(100, 999);

    $u = $conn->prepare("
        UPDATE bookings 
        SET payment_status='paid', status='confirmed', payment_ref=?, paid_at=NOW() 
        WHERE id=? AND user_id=?
    ");
    $u->bind_param("sii", $ref, $id, $_SESSION['user_id']);
    $u->execute();

    // ✅ เรียกส่งเมล (ต้องอยู่ก่อน header)
    require 'send_mail.php';

    $stmt = $conn->prepare("
        SELECT b.*, m.title, s.show_date, s.show_time, s.hall, u.email
        FROM bookings b
        JOIN showtimes s ON b.showtime_id = s.id
        JOIN movies m ON s.movie_id = m.id
        JOIN users u ON b.user_id = u.id
        WHERE b.id = ?
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_assoc();

    if ($data) {
        sendTicketEmail($data['email'], $data);
    }

    // ✅ ค่อย redirect หลังสุด
    header("Location: mybooking.php?paid=1");
    exit();
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ชำระเงิน - CineMax</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .payment-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 2rem;
            max-width: 480px;
            margin: 0 auto;
            text-align: center;
        }

        .qr-box {
            background: #fff;
            border-radius: 12px;
            padding: 1rem;
            display: inline-block;
            margin: 1.5rem 0;
        }

        .amount {
            font-family: 'Bebas Neue', cursive;
            font-size: 3rem;
            color: var(--gold);
            letter-spacing: 2px;
        }

        .booking-detail {
            background: rgba(255, 255, 255, 0.04);
            border-radius: 10px;
            padding: 1rem;
            margin: 1rem 0;
            text-align: left;
            font-size: 0.9rem;
        }

        .booking-detail div {
            display: flex;
            justify-content: space-between;
            padding: 0.3rem 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        .booking-detail div:last-child {
            border-bottom: none;
        }

        .booking-detail .lbl {
            color: var(--text-dim);
        }
    </style>
</head>

<body>

    <nav>
        <a href="index.php" class="nav-logo">CINE<span>MAX</span></a>
        <ul class="nav-links">
            <li><a href="index.php">หน้าแรก</a></li>
            <li><a href="mybooking.php">ตั๋วของฉัน</a></li>
            <li><a href="logout.php" class="btn-nav">ออกจากระบบ</a></li>
        </ul>
    </nav>

    <div class="page-header">
        <div class="page-header-inner">
            <h1>💳 ชำระเงิน</h1>
            <p>สแกน QR หรือกดยืนยันเพื่อดำเนินการต่อ</p>
        </div>
    </div>

    <div class="section" style="max-width:560px;">
        <div class="payment-card">

            <div style="font-size:0.85rem;color:var(--text-dim);margin-bottom:0.5rem;">ยอดที่ต้องชำระ</div>
            <div class="amount">฿<?= number_format($b['total_price'], 0) ?></div>

            <!-- QR Code -->
            <div class="qr-box">
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=CINEMAX-PAY-<?= $b['id'] ?>-<?= $b['total_price'] ?>"
                    alt="QR Code" width="180" height="180">
            </div>

            <!-- รายละเอียดการจอง -->
            <div class="booking-detail">
                <div><span class="lbl">หนัง</span><span
                        style="font-weight:600;"><?= htmlspecialchars($b['title']) ?></span></div>
                <div><span class="lbl">วันที่</span><span><?= date('d/m/Y', strtotime($b['show_date'])) ?></span></div>
                <div><span class="lbl">รอบ</span><span><?= substr($b['show_time'], 0, 5) ?> น.</span></div>
                <div><span class="lbl">โรง</span><span><?= htmlspecialchars($b['hall']) ?></span></div>
                <div><span class="lbl">ที่นั่ง</span><span
                        style="font-weight:600;"><?= htmlspecialchars($b['seats']) ?></span></div>
                <div>
                    <span class="lbl">ประเภท</span>
                    <span>
                        <?php if ($b['vip_count'] > 0): ?>
                            <span style="color:var(--gold);">⭐ VIP ×<?= $b['vip_count'] ?></span>
                        <?php endif; ?>
                        <?php if ($b['normal_count'] > 0): ?>
                            💺 Normal ×<?= $b['normal_count'] ?>
                        <?php endif; ?>
                    </span>
                </div>
            </div>

            <div
                style="background:rgba(229,9,20,0.1);border:1px solid rgba(229,9,20,0.3);border-radius:8px;padding:0.8rem;margin-bottom:1.5rem;font-size:0.85rem;color:var(--text-dim);">
                ⏱ กรุณาชำระภายใน 15 นาที มิฉะนั้นที่นั่งจะถูกยกเลิกอัตโนมัติ
            </div>

            <form method="POST">
                <button type="submit" class="btn btn-primary btn-full" style="font-size:1.1rem;padding:1rem;">
                    ✅ ฉันชำระเงินแล้ว
                </button>
            </form>

            <div style="margin-top:1rem;">
                <a href="mybooking.php" class="btn btn-outline btn-full">ยกเลิก / ชำระทีหลัง</a>
            </div>
        </div>
    </div>

    <footer><strong>CINEMAX</strong> &copy; <?= date('Y') ?></footer>

</body>

</html>