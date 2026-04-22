<?php
// === 1. นำเข้าไฟล์ตั้งค่าและบังคับว่าต้องล็อกอินก่อนเสมอ ===
require_once 'config.php';
requireLogin();

// === 2. ตรวจสอบว่ามีการส่ง ID ของรอบฉายมาหรือไม่ และดึงข้อมูลรอบฉาย-หนัง ===
$showtime_id = intval($_GET['showtime'] ?? 0);
if (!$showtime_id) {
    header("Location: index.php");
    exit();
}

$stmt = $conn->prepare("SELECT s.*, m.title, m.duration, m.genre FROM showtimes s JOIN movies m ON s.movie_id = m.id WHERE s.id = ?");
$stmt->bind_param("i", $showtime_id);
$stmt->execute();
$showtime = $stmt->get_result()->fetch_assoc();
if (!$showtime) {
    header("Location: index.php");
    exit();
}

// === 3. ดึงข้อมูลที่นั่งที่ถูกจองไปแล้วสำหรับรอบฉายนี้ (เพื่อไม่ให้กดเลือกซ้ำ) ===
// ดึงที่นั่งที่จองแล้ว (ยกเว้นสถานะที่ยกเลิกไปแล้ว)
$booked_stmt = $conn->prepare("SELECT seats FROM bookings WHERE showtime_id = ? AND status != 'cancelled'");
$booked_stmt->bind_param("i", $showtime_id);
$booked_stmt->execute();

$result = $booked_stmt->get_result(); // 🔥 เรียกครั้งเดียว

$taken_seats = [];
while ($row = $result->fetch_assoc()) {
    foreach (explode(',', $row['seats']) as $s) {
        $s = trim($s);
        if ($s)
            $taken_seats[] = $s;
    }
}

$result->free();       // 🔥 ปิด result
$booked_stmt->close(); // 🔥 ปิด statement

$error = '';

// === 4. จัดการเมื่อมีการกดปุ่ม "ยืนยันการจอง" (ส่งฟอร์มแบบ POST) ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selected = trim($_POST['selected_seats'] ?? '');
    $seats_arr = array_values(array_filter(array_map('trim', explode(',', $selected))));

    if (empty($seats_arr)) {
        $error = 'กรุณาเลือกที่นั่งอย่างน้อย 1 ที่';
    } elseif (count($seats_arr) > 10) {
        $error = 'จองได้สูงสุด 10 ที่นั่งต่อครั้ง';
    } else {
        // ===== 5. ระบบป้องกันการจองที่นั่งซ้ำซ้อนกัน (Concurrency Control) =====
        // เริ่ม Transaction หากมีคนจองพร้อมกัน จะได้ล็อกตารางไว้ตรวจสอบก่อน
        $conn->begin_transaction();

        $fresh = $conn->prepare("SELECT seats FROM bookings WHERE showtime_id = ? AND status != 'cancelled' FOR UPDATE");
        $fresh->bind_param("i", $showtime_id);
        $fresh->execute();
        $fresh_taken = [];
        $fresult = $fresh->get_result();
        while ($r = $fresult->fetch_assoc()) {
            foreach (explode(',', $r['seats']) as $s) {
                $s = trim($s);
                if ($s)
                    $fresh_taken[] = $s;
            }
        }

        $conflict = array_intersect($seats_arr, $fresh_taken);
        if (!empty($conflict)) {
            $conn->rollback();
            $error = '⚠️ ที่นั่ง ' . implode(', ', $conflict) . ' ถูกจองไปแล้ว กรุณาเลือกใหม่';
            $taken_seats = $fresh_taken;
        } else {
            // === 6. คำนวณราคาและบันทึกข้อมูลการจองลงในฐานข้อมูล ===
            // คำนวณราคาอัตโนมัติจากที่นั่งที่เลือกว่าเป็น VIP หรือ Normal
            $calc = calcTotalPrice($seats_arr);
            $total_price = $calc['total'];
            $vip_count = $calc['vip'];
            $normal_count = $calc['normal'];
            $seats_str = implode(',', $seats_arr);

            // สร้าง seat_types string (ใช้ฟังก์ชันปกติแทน arrow function)
            $types = array_map(function ($s) {
                return getSeatType($s);
            }, $seats_arr);
            $types_str = implode(',', $types);

            $user_id = $_SESSION['user_id'];
            $ins = $conn->prepare("INSERT INTO bookings 
            (user_id, showtime_id, seats, seat_types, total_price, vip_count, normal_count, status, payment_status)
            VALUES (?,?,?,?,?,?,?, 'pending', 'pending')");
            $ins->bind_param("iissdii", $user_id, $showtime_id, $seats_str, $types_str, $total_price, $vip_count, $normal_count);

            if ($ins->execute()) {
                $new_booking_id = $ins->insert_id; // ✅ ดึงทันทีหลัง insert ถูกต้อง
                $num_seats = count($seats_arr);
                $upd = $conn->prepare("UPDATE showtimes SET available_seats = available_seats - ? WHERE id = ?");
                $upd->bind_param("ii", $num_seats, $showtime_id);

                if ($upd->execute()) {
                    $conn->commit();
                    header("Location: payment.php?id=" . $new_booking_id);
                    exit();
                } else {
                    $conn->rollback();
                    $error = 'อัปเดตที่นั่งล้มเหลว';
                }
            } else {
                $conn->rollback();
                $error = 'เกิดข้อผิดพลาด กรุณาลองใหม่';
            }
        }
    }
}

// === 7. เตรียมข้อมูลสำหรับสร้างแผนผังที่นั่ง (Seat Map) ===
$total_seats = $showtime['total_seats']; // ดึงจำนวนที่นั่งทั้งหมดจาก DB

$cols = 10; // กำหนด 1 แถวมี 10 ที่
$rows_count = ceil($total_seats / $cols);

// สร้าง A, B, C, D...
$all_rows = [];
for ($i = 0; $i < $rows_count; $i++) {
    $all_rows[] = chr(65 + $i); // 65 = A
}

// กำหนดให้แถว A และ B เป็นโซน VIP (ครึ่งบน)
$rows_vip = ['A', 'B'];
$rows_normal = array_diff($all_rows, $rows_vip);
?>
<!-- === 8. เริ่มต้นโครงสร้างหน้าเว็บ (HTML) === -->
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เลือกที่นั่ง - CineMax</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .seat.vip-seat {
            border-color: rgba(245, 197, 24, 0.4);
        }

        .seat.vip-seat:hover:not(.taken) {
            background: rgba(245, 197, 24, 0.25);
            border-color: var(--gold);
            color: var(--gold);
        }

        .seat.vip-seat.selected {
            background: var(--gold);
            border-color: var(--gold);
            color: #000;
        }

        .zone-label {
            grid-column: 1/-1;
            text-align: center;
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 2px;
            padding: 4px 0;
            margin: 4px 0;
        }

        .zone-vip-label {
            color: var(--gold);
        }

        .zone-normal-label {
            color: var(--text-dim);
        }

        .legend-box.vip {
            background: var(--gold);
            border-color: var(--gold);
        }

        .price-breakdown {
            background: rgba(255, 255, 255, 0.04);
            border-radius: 8px;
            padding: 0.8rem;
            margin: 0.8rem 0;
            font-size: 0.85rem;
        }

        .price-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 4px;
        }

        .price-row .tag-vip {
            color: var(--gold);
            font-weight: 600;
        }

        .price-row .tag-normal {
            color: var(--text-dim);
        }
    </style>
</head>

<body>

    <!-- === 9. แถบเมนูด้านบน (Navigation Bar) === -->
    <nav>
        <a href="index.php" class="nav-logo">CINE<span>MAX</span></a>
        <ul class="nav-links">
            <li><a href="index.php">หน้าแรก</a></li>
            <li><a href="mybooking.php">ตั๋วของฉัน</a></li>
            <li><a href="profile.php" style="display:flex;align-items:center;gap:0.5rem;">
                    <div class="user-avatar"><?= strtoupper(substr($_SESSION['username'], 0, 1)) ?></div>
                </a></li>
            <li><a href="logout.php" class="btn-nav">ออกจากระบบ</a></li>
        </ul>
    </nav>

    <!-- === 10. ส่วนหัวหน้าเพจ แสดงชื่อหนัง โรง และเวลารอบฉาย === -->
    <div class="page-header">
        <div class="page-header-inner">
            <h1>💺 เลือกที่นั่ง</h1>
            <p><?= htmlspecialchars($showtime['title']) ?> · <?= $showtime['hall'] ?>
                <?php if ($showtime['hall_type'] === 'vip'): ?>
                    <span style="color:var(--gold);font-weight:700;"> ★ VIP Hall</span>
                <?php endif; ?>
                · <?= substr($showtime['show_time'], 0, 5) ?> น.
            </p>
        </div>
    </div>

    <div class="section" style="max-width:960px;">
        <?php if ($error): ?>
            <div class="alert alert-error">❌ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div style="display:grid;grid-template-columns:1fr 300px;gap:2rem;align-items:start;">

            <!-- === 11. ส่วนแสดงแผนผังที่นั่ง (Seat Map) === -->
            <div>
                <div class="screen">SCREEN</div>

                <div class="seat-legend" style="flex-wrap:wrap;gap:1rem;">
                    <span>
                        <div class="legend-box"></div> Normal ฿<?= $showtime['price'] ?>
                    </span>
                    <span>
                        <div class="legend-box vip"></div> VIP ฿<?= $showtime['price'] + 70 ?>
                    </span>
                    <span>
                        <div class="legend-box selected"></div> เลือกแล้ว
                    </span>
                    <span>
                        <div class="legend-box taken"></div> ถูกจอง
                    </span>
                </div>

                <!-- === 12. แบบฟอร์มเก็บข้อมูลที่นั่งที่ถูกเลือก เพื่อส่งไปประมวลผล === -->
                <form method="POST" id="bookingForm">
                    <input type="hidden" name="selected_seats" id="selectedInput">

                    <!-- VIP Zone label -->
                    <div
                        style="text-align:center;margin:0.5rem 0 0.2rem;font-size:0.75rem;font-weight:700;letter-spacing:2px;color:var(--gold);">
                        ★ VIP ZONE — ฿<?= $showtime['price'] + 70 ?>
                    </div>

                    <div class="seats-grid" id="seatGrid">
                        <?php foreach ($all_rows as $row_index => $row):
                            $is_vip_row = in_array($row, $rows_vip);
                            if ($row === 'C'): ?>
                                <!-- Normal zone divider -->
                                <div
                                    style="grid-column:1/-1;text-align:center;margin:0.8rem 0 0.2rem;font-size:0.75rem;font-weight:700;letter-spacing:2px;color:var(--text-dim);">
                                    NORMAL ZONE — ฿<?= $showtime['price'] ?>
                                </div>
                            <?php endif;
                            $max_seat = min($cols, $total_seats - (($row_index) * $cols));
                            for ($c = 1; $c <= $max_seat; $c++):
                                $seat_id = $row . $c;
                                $is_taken = in_array($seat_id, $taken_seats);
                                $vip_class = $is_vip_row ? 'vip-seat' : '';
                                ?>
                                <div class="seat <?= $vip_class ?> <?= $is_taken ? 'taken' : '' ?>" data-seat="<?= $seat_id ?>"
                                    data-price="<?= $showtime['price'] + ($is_vip_row ? 70 : 0) ?>"
                                    data-type="<?= getSeatType($seat_id) ?>" <?= $is_taken ? '' : 'onclick="toggleSeat(this)"' ?>
                                    title="<?= $seat_id ?> — <?= $is_vip_row ? 'VIP ฿' . PRICE_VIP : 'Normal ฿' . PRICE_NORMAL ?>">
                                    <?= $seat_id ?>
                                </div>
                            <?php endfor;
                        endforeach; ?>
                    </div>

                    <button type="submit" id="confirmBtn" class="btn btn-primary btn-full" style="margin-top:1rem;"
                        disabled>
                        🎟️ ยืนยันการจอง
                    </button>
                </form>
            </div>

            <!-- === 13. ส่วนแสดงสรุปการจองตั๋ว (Booking Summary) === -->
            <div class="summary-card">
                <div class="summary-title">📋 สรุปการจอง</div>
                <div class="summary-row">
                    <span class="label">หนัง</span>
                    <span class="value"
                        style="font-size:0.82rem;text-align:right;"><?= htmlspecialchars($showtime['title']) ?></span>
                </div>
                <div class="summary-row">
                    <span class="label">รอบ</span>
                    <span class="value"><?= substr($showtime['show_time'], 0, 5) ?> น.</span>
                </div>
                <div class="summary-row">
                    <span class="label">โรง</span>
                    <span class="value"><?= $showtime['hall'] ?></span>
                </div>
                <div class="summary-row">
                    <span class="label">ที่นั่ง</span>
                    <span class="value" id="seatDisplay" style="text-align:right;font-size:0.82rem;">-</span>
                </div>

                <!-- Price Breakdown -->
                <div class="price-breakdown" id="priceBreakdown" style="display:none;">
                    <div class="price-row">
                        <span class="tag-vip">⭐ VIP (<span id="vipCount">0</span> ที่ × ฿<?= PRICE_VIP ?>)</span>
                        <span id="vipTotal">฿0</span>
                    </div>
                    <div class="price-row">
                        <span class="tag-normal">💺 Normal (<span id="normalCount">0</span> ที่ ×
                            ฿<?= PRICE_NORMAL ?>)</span>
                        <span id="normalTotal">฿0</span>
                    </div>
                </div>

                <div class="summary-total">
                    <span>รวมทั้งหมด</span>
                    <span class="price" id="totalDisplay">฿0</span>
                </div>

                <div
                    style="margin-top:1rem;padding:0.8rem;background:rgba(255,255,255,0.04);border-radius:8px;font-size:0.82rem;color:var(--text-dim);line-height:1.8;">
                    👤 <?= htmlspecialchars($_SESSION['fullname'] ?? $_SESSION['username']) ?><br>
                    ★ VIP = แถว A–B (ใกล้จอ)<br>
                    💺 Normal = แถว C–<?= end($all_rows) ?>
                </div>
            </div>
        </div>
    </div>

    <!-- === 14. ส่วนท้ายของหน้าเว็บ (Footer) === -->
    <footer><strong>CINEMAX</strong> &copy; <?= date('Y') ?></footer>

    <!-- === 15. สคริปต์จัดการการคลิกเลือกที่นั่งและคำนวณราคาแบบ Real-time (JavaScript) === -->
    <script>
        const PRICE_VIP = <?= PRICE_VIP ?>;
        const PRICE_NORMAL = <?= PRICE_NORMAL ?>;
        let selectedSeats = new Map(); // seat_id -> {price, type}

        function toggleSeat(el) {
            const seat = el.dataset.seat;
            const price = parseInt(el.dataset.price);
            const type = el.dataset.type;

            if (selectedSeats.has(seat)) {
                selectedSeats.delete(seat);
                el.classList.remove('selected');
            } else {
                if (selectedSeats.size >= 10) {
                    alert('จองได้สูงสุด 10 ที่นั่งต่อครั้ง');
                    return;
                }
                selectedSeats.set(seat, {
                    price,
                    type
                });
                el.classList.add('selected');
            }
            updateSummary();
        }

        function updateSummary() {
            const seats = [...selectedSeats.keys()];
            let total = 0,
                vip = 0,
                normal = 0;

            selectedSeats.forEach(v => {
                total += v.price;
                v.type === 'vip' ? vip++ : normal++;
            });

            document.getElementById('selectedInput').value = seats.join(',');
            document.getElementById('seatDisplay').textContent = seats.length ? seats.join(', ') : '-';
            document.getElementById('totalDisplay').textContent = '฿' + total.toLocaleString();
            document.getElementById('confirmBtn').disabled = seats.length === 0;

            // Breakdown
            const bd = document.getElementById('priceBreakdown');
            bd.style.display = seats.length ? 'block' : 'none';
            document.getElementById('vipCount').textContent = vip;
            document.getElementById('normalCount').textContent = normal;
            document.getElementById('vipTotal').textContent = '฿' + (vip * PRICE_VIP).toLocaleString();
            document.getElementById('normalTotal').textContent = '฿' + (normal * PRICE_NORMAL).toLocaleString();
        }
    </script>
</body>

</html>