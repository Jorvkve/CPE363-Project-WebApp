<?php
// === 1. นำเข้าไฟล์ตั้งค่าและตรวจสอบการล็อกอิน ===
require_once 'config.php';
requireLogin();

// === 2. ดึงประวัติการจองทั้งหมดของผู้ใช้จากฐานข้อมูล ===
// ดึงประวัติการจองทั้งหมดของ user จาก DB
$stmt = $conn->prepare("
    SELECT b.*, m.title, m.genre, s.show_date, s.show_time, s.hall, s.price
    FROM bookings b
    JOIN showtimes s ON b.showtime_id = s.id
    JOIN movies m ON s.movie_id = m.id
    WHERE b.user_id = ?
    ORDER BY b.booked_at DESC
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$bookings = $stmt->get_result();

// นับจำนวน
$total_bookings = $bookings->num_rows;
$bookings->data_seek(0);

// === 3. จัดการการยกเลิกการจองตั๋ว (เมื่อผู้ใช้กดยกเลิก) ===
// ยกเลิก booking
if (isset($_GET['cancel']) && is_numeric($_GET['cancel'])) {
    $cancel_id = intval($_GET['cancel']);

    $check = $conn->prepare("SELECT seats, showtime_id, payment_status, status FROM bookings WHERE id=? AND user_id=? AND status!='cancelled'");
    $check->bind_param("ii", $cancel_id, $_SESSION['user_id']);
    $check->execute();
    $data = $check->get_result()->fetch_assoc();

    if ($data && $data['status'] !== 'cancelled') {
        $num = count(array_filter(explode(',', $data['seats'])));

        // ✅ แก้แล้ว (ไม่มี execute ลอย ๆ)
        $upd = $conn->prepare("UPDATE bookings SET status='cancelled', payment_status='cancelled' WHERE id=?");
        $upd->bind_param("i", $cancel_id);
        $upd->execute();

        $upd2 = $conn->prepare("UPDATE showtimes SET available_seats = available_seats + ? WHERE id=?");
        $upd2->bind_param("ii", $num, $data['showtime_id']);
        $upd2->execute();
    }

    header("Location: mybooking.php");
    exit();
}

// === 4. ดึงข้อมูลประวัติการจองใหม่อีกครั้งหลังจากมีการอัปเดต ===
// ดึงใหม่หลังยกเลิก
$stmt->execute();
$bookings = $stmt->get_result();

// ไอคอนหนังแบบสุ่มเพื่อความสวยงาม
$emojis = [1 => '🚀', 2 => '🦇', 3 => '🌀', 4 => '🤖', 5 => '🏨'];
?>
<!-- === 5. เริ่มต้นโครงสร้างหน้าเว็บ (HTML) === -->
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ตั๋วของฉัน - CineMax</title>
    <!-- === 6. นำเข้าไฟล์สไตล์ (CSS) === -->
    <link rel="stylesheet" href="css/style.css">
</head>

<body>

    <!-- === 7. แถบเมนูด้านบน (Navigation Bar) === -->
    <nav>
        <a href="index.php" class="nav-logo">CINE<span>MAX</span></a>
        <ul class="nav-links">
            <li><a href="index.php">หน้าแรก</a></li>
            <li><a href="mybooking.php" class="active">ตั๋วของฉัน</a></li>
            <li>
                <a href="#" style="display:flex;align-items:center;gap:0.5rem;">
                    <div class="user-avatar"><?= strtoupper(substr($_SESSION['username'], 0, 1)) ?></div>
                    <?= htmlspecialchars($_SESSION['username']) ?>
                </a>
            </li>
            <li><a href="logout.php" class="btn-nav">ออกจากระบบ</a></li>
        </ul>
    </nav>

    <!-- === 8. ส่วนหัวหน้าเพจ === -->
    <div class="page-header">
        <div class="page-header-inner">
            <h1>🎟️ ตั๋วของฉัน</h1>
            <p>ประวัติการจองตั๋วทั้งหมดของ <?= htmlspecialchars($_SESSION['fullname'] ?? $_SESSION['username']) ?></p>
        </div>
    </div>

    <!-- === 9. ส่วนเนื้อหาหลัก === -->
    <div class="section">

        <!-- === 10. ข้อความแจ้งเตือนเมื่อจองตั๋วสำเร็จ === -->
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success" style="max-width:600px;margin:0 auto 1.5rem;">
                ✅ จองตั๋วสำเร็จแล้ว! ขอให้สนุกกับการดูหนังครับ 🎬
            </div>
        <?php endif; ?>

        <!-- === 11. แสดงหน้าจอเมื่อไม่มีประวัติการจอง === -->
        <?php if ($bookings->num_rows === 0): ?>
            <div class="empty-state">
                <div class="empty-icon">🎫</div>
                <h3>ยังไม่มีประวัติการจอง</h3>
                <p style="margin-bottom:1.5rem;">เริ่มจองตั๋วหนังได้เลย!</p>
                <a href="index.php" class="btn btn-primary">🎬 ดูหนังทั้งหมด</a>
            </div>
        <?php else: ?>

            <!-- === 12. สถิติสรุปจำนวนการจอง === -->
            <!-- Stats -->
            <div style="display:flex;gap:1rem;margin-bottom:2rem;flex-wrap:wrap;">
                <div
                    style="background:var(--bg-card);border:1px solid var(--border);border-radius:12px;padding:1rem 1.5rem;min-width:140px;">
                    <div style="color:var(--text-dim);font-size:0.8rem;margin-bottom:0.3rem;">การจองทั้งหมด</div>
                    <div style="font-family:'Bebas Neue',cursive;font-size:2rem;color:var(--accent);">
                        <?= $bookings->num_rows ?></div>
                </div>
            </div>

            <!-- === 13. แสดงรายการประวัติการจองทั้งหมด (Ticket Cards) === -->
            <!-- Booking List -->
            <?php
            $total_spent = 0;
            $all_bookings = [];
            while ($b = $bookings->fetch_assoc()) {
                $all_bookings[] = $b;
                if ($b['status'] === 'confirmed') $total_spent += $b['total_price'];
            }

            foreach ($all_bookings as $b):
                $seats_arr = explode(',', $b['seats']);
                $num_seats = count($seats_arr);
                $movie_emoji = '🎬';
            ?>
                <div class="ticket-card" style="<?= $b['status'] === 'cancelled' ? 'opacity:0.6;' : '' ?>">
                    <div class="ticket-icon"><?= $movie_emoji ?></div>

                    <div>
                        <div class="ticket-movie"><?= htmlspecialchars($b['title']) ?></div>

                        <div class="ticket-info">
                            📅 <?= date('d/m/Y', strtotime($b['show_date'])) ?>
                            &nbsp;·&nbsp;
                            🕐 <?= substr($b['show_time'], 0, 5) ?> น.
                            &nbsp;·&nbsp;
                            🏛 <?= $b['hall'] ?>

                            <br>

                            💺 ที่นั่ง: <strong style="color:var(--text)">
                                <?= htmlspecialchars($b['seats']) ?>
                            </strong>
                            &nbsp;(<?= $num_seats ?> ที่)

                            <?php if ($b['vip_count'] > 0): ?>
                                &nbsp;<span style="color:var(--gold);font-size:0.8rem;">
                                    ⭐VIP×<?= $b['vip_count'] ?>
                                </span>
                            <?php endif; ?>

                            <?php if ($b['normal_count'] > 0): ?>
                                &nbsp;<span style="color:var(--text-dim);font-size:0.8rem;">
                                    💺×<?= $b['normal_count'] ?>
                                </span>
                            <?php endif; ?>

                            <br>

                            🕒 จองเมื่อ <?= date('d/m/Y H:i', strtotime($b['booked_at'])) ?>

                            <br>

                            <!-- สถานะ payment -->
                            <?php
                            $payment_map = [
                                'pending' => '⏳ รอชำระเงิน',
                                'paid' => '✅ ชำระแล้ว',
                                'cancelled' => '❌ ยกเลิก'
                            ];
                            ?>

                            <?php if ($b['status'] === 'cancelled'): ?>

                                <span class="status-badge status-cancelled">❌ ยกเลิก</span>

                            <?php else: ?>

                                <!-- booking status -->
                                <span class="status-badge status-<?= $b['status'] ?>">
                                    <?= $b['status'] === 'confirmed' ? '✅ ยืนยันแล้ว' : '⏳ รอดำเนินการ' ?>
                                </span>

                                <br>

                                <!-- payment status -->
                                <span class="status-badge status-<?= $b['payment_status'] ?>">
                                    💳 <?= $payment_map[$b['payment_status']] ?? 'ไม่ทราบสถานะ' ?>
                                </span>

                            <?php endif; ?>

                            <!-- ปุ่มจ่ายเงิน -->
                            <?php if ($b['payment_status'] === 'pending' && $b['status'] !== 'cancelled'): ?>
                                <div style="margin-top:0.5rem;">
                                    <a href="payment.php?id=<?= $b['id'] ?>" class="btn btn-primary btn-sm">
                                        💳 ไปชำระเงิน
                                    </a>
                                </div>
                            <?php endif; ?>

                            <?php if ($b['payment_status'] === 'paid' && $b['status'] !== 'cancelled'): ?>
                                <div style="margin-top:0.5rem;">
                                    <a href="ticket.php?id=<?= $b['id'] ?>" class="btn btn-outline btn-sm">
                                        🎫 ดูตั๋ว
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div>
                        <div class="ticket-price">฿<?= number_format($b['total_price'], 0) ?></div>
                        <div class="ticket-seats"><?= $num_seats ?> ที่นั่ง</div>
                        <?php if ($b['status'] !== 'cancelled' && $b['payment_status'] !== 'paid'): ?>
                            <div style="margin-top:0.8rem;">
                                <a href="?cancel=<?= $b['id'] ?>" onclick="return confirm('ต้องการยกเลิกการจองนี้?')"
                                    class="btn btn-outline btn-sm" style="font-size:0.75rem;color:var(--text-dim);">
                                    ยกเลิก
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <div
                style="margin-top:2rem;padding:1.5rem;background:var(--bg-card);border:1px solid var(--border);border-radius:12px;display:flex;justify-content:space-between;align-items:center;">
                <div style="color:var(--text-dim);">ยอดรวมที่ใช้จ่าย</div>
                <div style="font-family:'Bebas Neue',cursive;font-size:2rem;color:var(--gold);">
                    ฿<?= number_format($total_spent, 0) ?></div>
            </div>

        <?php endif; ?>

        <!-- === 14. ปุ่มกลับหน้าแรก === -->
        <div style="margin-top:2rem;">
            <a href="index.php" class="btn btn-outline">← กลับหน้าแรก</a>
        </div>
    </div>

    <!-- === 15. ส่วนท้ายของหน้าเว็บ (Footer) === -->
    <footer>
        <strong>CINEMAX</strong> &copy; <?= date('Y') ?>
    </footer>

</body>

</html>