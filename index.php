<?php
// === 1. นำเข้าไฟล์ตั้งค่าหลัก ===
require_once 'config.php';

// === 2. รับค่าจากฟอร์มค้นหาและการกรองข้อมูล (GET parameters) ===
$search = trim($_GET['q'] ?? '');
$selected_genre = trim($_GET['genre'] ?? '');
$selected_date = trim($_GET['date'] ?? '');

// === 3. ดึงข้อมูลประเภทหนัง (Genre) และวันที่ที่มีรอบฉาย เพื่อใช้ใน Dropdown ตัวกรอง ===
// ดึงประเภทหนังแบบไม่ซ้ำกัน
$genre_result = $conn->query("SELECT DISTINCT genre FROM movies WHERE is_showing = 1 AND genre <> '' ORDER BY genre");
// ดึงวันที่ที่มีรอบฉายตั้งแต่ปัจจุบันเป็นต้นไป
$available_dates = $conn->query("
    SELECT DISTINCT s.show_date
    FROM showtimes s
    JOIN movies m ON m.id = s.movie_id
    WHERE m.is_showing = 1 AND s.show_date >= CURDATE()
    ORDER BY s.show_date ASC
");

// === 4. สร้างคำสั่ง SQL สำหรับดึงข้อมูลหนัง พร้อมกับหาข้อมูลรอบฉายถัดไป ===
$movie_sql = "
    SELECT
        m.*,
        MIN(TIMESTAMP(s.show_date, s.show_time)) AS next_showtime_at,
        COUNT(s.id) AS upcoming_count
    FROM movies m
    LEFT JOIN showtimes s
        ON s.movie_id = m.id
        AND s.show_date >= CURDATE()
";

$types = '';
$params = [];

// === 5. เพิ่มเงื่อนไขการกรองข้อมูลตามที่ผู้ใช้เลือก ===
// กรองตามวันที่
if ($selected_date !== '') {
    $movie_sql .= " AND s.show_date = ?";
    $types .= 's';
    $params[] = $selected_date;
}

$movie_sql .= " WHERE m.is_showing = 1";

// กรองตามคำค้นหา (ค้นจากชื่อหนัง, ประเภท หรือ รายละเอียด)
if ($search !== '') {
    $search_like = '%' . $search . '%';
    $movie_sql .= " AND (m.title LIKE ? OR m.genre LIKE ? OR m.description LIKE ?)";
    $types .= 'sss';
    $params[] = $search_like;
    $params[] = $search_like;
    $params[] = $search_like;
}

// กรองตามประเภทหนัง
if ($selected_genre !== '') {
    $movie_sql .= " AND m.genre = ?";
    $types .= 's';
    $params[] = $selected_genre;
}

$movie_sql .= "
    GROUP BY m.id
    HAVING upcoming_count > 0
    ORDER BY next_showtime_at ASC, m.id ASC
";

// === 6. นำคำสั่ง SQL ไปประมวลผลกับฐานข้อมูลและเก็บผลลัพธ์ ===
$movies_stmt = $conn->prepare($movie_sql);
if ($types !== '') {
    $movies_stmt->bind_param($types, ...$params);
}
$movies_stmt->execute();
$movies = $movies_stmt->get_result();
$movie_count = $movies->num_rows;

// ไอคอนสำรองกรณีที่หนังไม่มีรูปโปสเตอร์
$emojis = ['🚀', '🎇', '🌌', '🎭', '🏰'];
?>
<!-- === 7. เริ่มต้นโครงสร้างหน้าเว็บ (HTML) === -->
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CineMax - จองตั๋วหนังออนไลน์</title>
    <!-- === 8. นำเข้าไฟล์สไตล์ (CSS) === -->
    <link rel="stylesheet" href="css/style.css">
</head>

<body>

    <!-- === 9. แถบเมนูด้านบน (Navigation Bar) === -->
    <nav>
        <a href="index.php" class="nav-logo">CINE<span>MAX</span></a>
        <ul class="nav-links">
            <li><a href="index.php" class="active">หน้าแรก</a></li>
            <?php if (isLoggedIn()): ?>
            <li><a href="mybooking.php">ตั๋วของฉัน</a></li>
            <li>
                <a href="profile.php" style="display:flex;align-items:center;gap:0.5rem;">
                    <div class="user-avatar"><?= strtoupper(substr($_SESSION['username'], 0, 1)) ?></div>
                    <?= htmlspecialchars($_SESSION['username']) ?>
                </a>
            </li>
            <?php if (isAdmin()): ?>
            <li><a href="admin.php" style="color:var(--gold);">Admin</a></li>
            <?php endif; ?>
            <li><a href="logout.php" class="btn-nav">ออกจากระบบ</a></li>
            <?php else: ?>
            <li><a href="login.php">เข้าสู่ระบบ</a></li>
            <li><a href="register.php" class="btn-nav">สมัครสมาชิก</a></li>
            <?php endif; ?>
        </ul>
    </nav>

    <!-- === 10. ส่วนต้อนรับหรือแบนเนอร์ด้านบน (Hero Section) === -->
    <div class="hero">
        <h1 class="hero-title">จองตั๋วหนัง<span>ออนไลน์</span></h1>
        <p class="hero-sub">ค้นหารอบหนังที่ใช่ เลือกเวลาได้ง่าย และจองที่นั่งได้ในไม่กี่ขั้นตอน</p>
    </div>

    <div class="section">
        <h2 class="section-title">🎬 ค้นหารอบหนัง</h2>

        <!-- === 11. แบบฟอร์มสำหรับค้นหาและกรองหนัง === -->
        <form method="GET" class="filter-card">
            <div class="filter-grid">
                <div class="form-group" style="margin-bottom:0;">
                    <label for="q">ค้นหาหนัง</label>
                    <input type="text" id="q" name="q" value="<?= htmlspecialchars($search) ?>"
                        placeholder="เช่น Spider-Man, Action, ผจญภัย">
                </div>

                <div class="form-group" style="margin-bottom:0;">
                    <label for="genre">ประเภท</label>
                    <select id="genre" name="genre">
                        <option value="">ทุกประเภท</option>
                        <?php while ($genre = $genre_result->fetch_assoc()): ?>
                        <option value="<?= htmlspecialchars($genre['genre']) ?>"
                            <?= $selected_genre === $genre['genre'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($genre['genre']) ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom:0;">
                    <label for="date">วันที่มีรอบฉาย</label>
                    <select id="date" name="date">
                        <option value="">ทุกวัน</option>
                        <?php while ($date = $available_dates->fetch_assoc()): ?>
                        <option value="<?= $date['show_date'] ?>"
                            <?= $selected_date === $date['show_date'] ? 'selected' : '' ?>>
                            <?= date('d/m/Y', strtotime($date['show_date'])) ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>

            <div class="filter-actions">
                <button type="submit" class="btn btn-primary btn-sm">ค้นหา</button>
                <a href="index.php" class="btn btn-outline btn-sm">ล้างตัวกรอง</a>
                <div class="filter-result-text">พบหนัง <?= $movie_count ?> เรื่อง</div>
            </div>
        </form>

        <!-- === 12. ส่วนแสดงรายการหนัง (Movie Grid) === -->
        <?php if ($movie_count > 0): ?>
        <div class="movies-grid">
            <?php $i = 0; ?>
            <?php while ($movie = $movies->fetch_assoc()): ?>
            <a href="movie.php?id=<?= $movie['id'] ?>" class="movie-card">
                <div class="movie-poster">
                    <?php if (!empty($movie['poster']) && file_exists($movie['poster'])): ?>
                    <img src="<?= htmlspecialchars($movie['poster']) ?>" class="movie-poster-img"
                        alt="<?= htmlspecialchars($movie['title']) ?>">
                    <?php else: ?>
                    <span><?= $emojis[$i % count($emojis)] ?></span>
                    <?php endif; ?>
                    <div class="movie-badge"><?= htmlspecialchars($movie['rating']) ?></div>
                </div>
                <div class="movie-info">
                    <div class="movie-title"><?= htmlspecialchars($movie['title']) ?></div>
                    <div class="movie-genre"><?= htmlspecialchars($movie['genre']) ?></div>
                    <div class="movie-meta">
                        <span class="movie-rating">⭐ <?= number_format(7.5 + ($movie['id'] % 15) * 0.1, 1) ?></span>
                        <span class="movie-duration">🕒 <?= $movie['duration'] ?> นาที</span>
                    </div>

                    <?php if (!empty($movie['next_showtime_at'])): ?>
                    <div class="movie-showtime-meta">
                        <div class="movie-showtime-label">รอบถัดไป</div>
                        <div class="movie-showtime-value">
                            <?= date('d/m/Y', strtotime($movie['next_showtime_at'])) ?>
                            • <?= date('H:i', strtotime($movie['next_showtime_at'])) ?>
                        </div>
                        <div class="movie-showtime-count">มีรอบให้เลือก <?= (int) $movie['upcoming_count'] ?> รอบ</div>
                    </div>
                    <?php endif; ?>
                </div>
            </a>
            <?php $i++; ?>
            <?php endwhile; ?>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <div class="empty-icon">🔎</div>
            <h3>ไม่พบรอบหนังที่ตรงกับเงื่อนไข</h3>
            <p>ลองค้นหาด้วยชื่อหนัง ประเภท หรือเลือกวันที่ใหม่อีกครั้ง</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- === 13. ส่วนแสดงจุดเด่นของระบบ (Features Section) === -->
    <div
        style="background:var(--bg-card); border-top:1px solid var(--border); border-bottom:1px solid var(--border); padding:3rem 2rem; margin-top:2rem;">
        <div
            style="max-width:900px;margin:0 auto;display:grid;grid-template-columns:repeat(3,1fr);gap:2rem;text-align:center;">
            <div>
                <div style="font-size:2.5rem;margin-bottom:0.8rem;">🎟️</div>
                <div style="font-weight:700;margin-bottom:0.3rem;">ค้นหาได้ทันที</div>
                <div style="color:var(--text-dim);font-size:0.9rem;">เจอหนังที่อยากดูและเช็กรอบฉายได้จากหน้าเดียว</div>
            </div>
            <div>
                <div style="font-size:2.5rem;margin-bottom:0.8rem;">💺</div>
                <div style="font-weight:700;margin-bottom:0.3rem;">เลือกที่นั่งเอง</div>
                <div style="color:var(--text-dim);font-size:0.9rem;">เข้าหนังเรื่องที่ต้องการแล้วจองที่นั่งได้ทันที
                </div>
            </div>
            <div>
                <div style="font-size:2.5rem;margin-bottom:0.8rem;">📋</div>
                <div style="font-weight:700;margin-bottom:0.3rem;">ดูรอบถัดไปง่ายขึ้น</div>
                <div style="color:var(--text-dim);font-size:0.9rem;">ทุกการ์ดหนังจะแสดงเวลารอบถัดไปและจำนวนรอบที่มี
                </div>
            </div>
        </div>
    </div>

    <!-- === 14. ส่วนท้ายของหน้าเว็บ (Footer) === -->
    <footer>
        <strong>CINEMAX</strong> &copy; <?= date('Y') ?> - ระบบจองตั๋วหนังออนไลน์
    </footer>

</body>

</html>