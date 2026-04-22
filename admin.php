<?php
require_once 'config.php';
requireAdmin();

/**
 * @param array{name?: string, tmp_name?: string, size?: int} $file
 */
function uploadMoviePoster(array $file): ?string
{
    if (empty($file['name']) || empty($file['tmp_name'])) {
        return null;
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp'];

    if (!in_array($ext, $allowed, true)) {
        die("เธฃเธญเธเธฃเธฑเธเน€เธเธเธฒเธฐ JPG, JPEG, PNG เนเธฅเธฐ WEBP");
    }

    if ($file['size'] > 5 * 1024 * 1024) {
        die("เธเธเธฒเธ”เนเธเธฅเนเน€เธเธดเธ 5MB");
    }

    if (!is_dir('uploads')) {
        mkdir('uploads', 0755, true);
    }

    $new_name = time() . "_" . rand(1000, 9999) . "." . $ext;
    $path = "uploads/" . $new_name;

    if (!move_uploaded_file($file['tmp_name'], $path)) {
        die("เธญเธฑเธเนเธซเธฅเธ”เนเธเธฅเนเนเธกเนเธชเธณเน€เธฃเนเธ เธเธฃเธธเธ“เธฒเธ•เธฃเธงเธเธชเธญเธเธชเธดเธ—เธเธดเนเนเธเธฅเน€เธ”เธญเธฃเน uploads/");
    }

    return $path;
}

// ===== Actions =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // เน€เธเธดเนเธกเธซเธเธฑเธ
    if ($_POST['action'] === 'add_movie') {
        $title = trim($_POST['title']);
        $desc = trim($_POST['description']);
        $genre = trim($_POST['genre']);
        $dur = intval($_POST['duration']);
        $rating = trim($_POST['rating']);

        $poster_path = uploadMoviePoster($_FILES['poster'] ?? []);

        // ๐”ฅ เน€เธเธดเนเธก poster เธฅเธ DB
        $stmt = $conn->prepare("INSERT INTO movies (title,description,genre,duration,rating,poster) VALUES (?,?,?,?,?,?)");
        $stmt->bind_param("sssiss", $title, $desc, $genre, $dur, $rating, $poster_path);
        $stmt->execute();

        header("Location: admin.php?tab=movies&msg=added");
        exit();
    }

    // เธฅเธเธซเธเธฑเธ
    if ($_POST['action'] === 'delete_movie') {
        $id = intval($_POST['id']);
        $d = $conn->prepare("DELETE FROM movies WHERE id=?");
        $d->bind_param("i", $id);
        $d->execute();
        header("Location: admin.php?tab=movies&msg=deleted");
        exit();
    }

    // toggle showing
    if ($_POST['action'] === 'toggle_movie') {
        $id = intval($_POST['id']);
        $u = $conn->prepare("UPDATE movies SET is_showing = 1 - is_showing WHERE id=?");
        $u->bind_param("i", $id);
        $u->execute();
        header("Location: admin.php?tab=movies");
        exit();
    }

    // เธขเธเน€เธฅเธดเธ booking
    if ($_POST['action'] === 'cancel_booking') {
        $id = intval($_POST['id']);
        $bk = $conn->prepare("SELECT seats, showtime_id FROM bookings WHERE id=?");
        $bk->bind_param("i", $id);
        $bk->execute();
        $brow = $bk->get_result()->fetch_assoc();
        if ($brow) {
            $n = count(array_filter(explode(',', $brow['seats'])));
            $u1 = $conn->prepare("UPDATE bookings SET status='cancelled' WHERE id=?");
            $u1->bind_param("i", $id);
            $u1->execute();
            $u2 = $conn->prepare("UPDATE showtimes SET available_seats=available_seats+? WHERE id=?");
            $u2->bind_param("ii", $n, $brow['showtime_id']);
            $u2->execute();
        }
        header("Location: admin.php?tab=bookings");
        exit();
    }

    // toggle admin
    if (isset($_POST['action']) && $_POST['action'] === 'toggle_admin') {

        if (!isAdmin()) {
            die("Access denied");
        }

        $id = intval($_POST['id']);

        if ($id != $_SESSION['user_id']) {
            $u = $conn->prepare("UPDATE users SET is_admin = 1 - is_admin WHERE id=?");
            $u->bind_param("i", $id);
            $u->execute();
        }
        header("Location: admin.php?tab=users");
        exit();
    }

    // โ• เน€เธเธดเนเธกเธฃเธญเธเธซเธเธฑเธ
    if ($_POST['action'] === 'add_showtime') {
        $movie_id = intval($_POST['movie_id']);
        $date = $_POST['show_date'];
        $time = $_POST['show_time'];
        $hall = trim($_POST['hall']);
        $type = $_POST['hall_type'];
        $price = floatval($_POST['price']);
        $seats = intval($_POST['seats']);

        $stmt = $conn->prepare("
        INSERT INTO showtimes (movie_id, show_date, show_time, hall, hall_type, price, total_seats, available_seats)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
        $stmt->bind_param("issssdii", $movie_id, $date, $time, $hall, $type, $price, $seats, $seats);
        $stmt->execute();

        header("Location: admin.php?tab=showtimes");
        exit();
    }

    // เธฅเธเธฃเธญเธเธซเธเธฑเธ
    if ($_POST['action'] === 'delete_showtime') {
        $id = intval($_POST['id']);

        // ๐”ฅ เน€เธเนเธเธเนเธญเธเธงเนเธฒเธกเธต booking เนเธซเธก
        $chk = $conn->prepare("SELECT COUNT(*) as c FROM bookings WHERE showtime_id=?");
        $chk->bind_param("i", $id);
        $chk->execute();
        $count = $chk->get_result()->fetch_assoc()['c'];

        if ($count > 0) {
            die("โ เธฅเธเธฃเธญเธเธเธตเนเนเธกเนเนเธ”เน เน€เธเธฃเธฒเธฐเธกเธตเธเธฒเธฃเธเธญเธเนเธฅเนเธง");
        }

        $d = $conn->prepare("DELETE FROM showtimes WHERE id=?");
        $d->bind_param("i", $id);
        $d->execute();

        header("Location: admin.php?tab=showtimes");
        exit();
    }

    if ($_POST['action'] === 'edit_movie') {
        $id = intval($_POST['id']);
        $title = $_POST['title'];
        $genre = $_POST['genre'];
        $duration = intval($_POST['duration']);
        $rating = $_POST['rating'];
        $description = $_POST['description'];
        $current_poster = trim($_POST['current_poster'] ?? '');
        $poster_path = $current_poster;

        $new_poster = uploadMoviePoster($_FILES['poster'] ?? []);
        if ($new_poster !== null) {
            $poster_path = $new_poster;
        }

        $stmt = $conn->prepare("
        UPDATE movies 
        SET title=?, genre=?, duration=?, rating=?, description=?, poster=? 
        WHERE id=?
    ");
        $stmt->bind_param("ssisssi", $title, $genre, $duration, $rating, $description, $poster_path, $id);
        $stmt->execute();

        header("Location: admin.php?tab=movies&msg=updated");
        exit();
    }
}

// ===== Stats =====
$s_movies = $conn->query("SELECT COUNT(*) c FROM movies WHERE is_showing=1")->fetch_assoc()['c'];
$s_users = $conn->query("SELECT COUNT(*) c FROM users")->fetch_assoc()['c'];
$s_bookings = $conn->query("SELECT COUNT(*) c FROM bookings WHERE status='confirmed'")->fetch_assoc()['c'];
$s_revenue = $conn->query("SELECT SUM(total_price) c FROM bookings WHERE status='confirmed'")->fetch_assoc()['c'] ?? 0;
$s_vip = $conn->query("SELECT SUM(vip_count) c FROM bookings WHERE status='confirmed'")->fetch_assoc()['c'] ?? 0;
$s_normal = $conn->query("SELECT SUM(normal_count) c FROM bookings WHERE status='confirmed'")->fetch_assoc()['c'] ?? 0;

// เธฃเธฒเธขเนเธ”เนเธฃเธฒเธขเธงเธฑเธ (7 เธงเธฑเธ)
$daily = $conn->query("
    SELECT DATE(booked_at) as day, SUM(total_price) as rev
    FROM bookings WHERE status='confirmed' AND booked_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY DATE(booked_at) ORDER BY day
");
$daily_labels = [];
$daily_data = [];
while ($d = $daily->fetch_assoc()) {
    $daily_labels[] = date('d/m', strtotime($d['day']));
    $daily_data[] = (float) $d['rev'];
}

// เธซเธเธฑเธเธขเธญเธ”เธเธดเธขเธก
$top_movies = $conn->query("
    SELECT m.title, COUNT(b.id) as cnt, SUM(b.total_price) as rev
    FROM bookings b JOIN showtimes s ON b.showtime_id=s.id JOIN movies m ON s.movie_id=m.id
    WHERE b.status='confirmed'
    GROUP BY m.id ORDER BY cnt DESC LIMIT 5
");

$tab = $_GET['tab'] ?? 'dashboard';
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - CineMax</title>
    <link rel="stylesheet" href="css/style.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
    <style>
    .admin-layout {
        display: grid;
        grid-template-columns: 220px 1fr;
        min-height: calc(100vh - 64px);
    }

    .admin-sidebar {
        background: var(--bg-card);
        border-right: 1px solid var(--border);
        padding: 1.5rem 1rem;
    }

    .admin-sidebar h3 {
        font-size: 0.7rem;
        letter-spacing: 2px;
        color: var(--text-dim);
        margin: 1.2rem 0 0.5rem;
        text-transform: uppercase;
    }

    .sidebar-link {
        display: flex;
        align-items: center;
        gap: 0.7rem;
        padding: 0.6rem 0.8rem;
        border-radius: 8px;
        color: var(--text-dim);
        text-decoration: none;
        font-size: 0.9rem;
        font-weight: 500;
        transition: all 0.2s;
        margin-bottom: 2px;
    }

    .sidebar-link:hover,
    .sidebar-link.active {
        background: rgba(229, 9, 20, 0.12);
        color: var(--text);
    }

    .sidebar-link.active {
        color: var(--accent);
    }

    .admin-main {
        padding: 2rem;
        overflow: auto;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        gap: 1rem;
        margin-bottom: 2rem;
    }

    .stat-card {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 1.2rem 1.5rem;
    }

    .stat-card .num {
        font-family: 'Bebas Neue', cursive;
        font-size: 2.2rem;
        line-height: 1;
    }

    .stat-card .lbl {
        color: var(--text-dim);
        font-size: 0.8rem;
        margin-top: 0.3rem;
    }

    .admin-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.88rem;
    }

    .admin-table th {
        text-align: left;
        padding: 0.7rem 1rem;
        color: var(--text-dim);
        font-size: 0.75rem;
        letter-spacing: 1px;
        text-transform: uppercase;
        border-bottom: 1px solid var(--border);
    }

    .admin-table td {
        padding: 0.8rem 1rem;
        border-bottom: 1px solid rgba(255, 255, 255, 0.04);
        vertical-align: middle;
    }

    .admin-table tr:hover td {
        background: rgba(255, 255, 255, 0.02);
    }

    .admin-section {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        overflow: hidden;
        margin-bottom: 1.5rem;
    }

    .admin-section-header {
        padding: 1rem 1.5rem;
        border-bottom: 1px solid var(--border);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .admin-section-header h2 {
        font-family: 'Bebas Neue', cursive;
        font-size: 1.3rem;
        letter-spacing: 2px;
    }

    .showtime-form {
        padding: 1.25rem;
        display: grid;
        gap: 1rem;
    }

    .showtime-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 0.9rem;
    }

    .showtime-field {
        display: flex;
        flex-direction: column;
        gap: 0.45rem;
    }

    .showtime-field label,
    .showtime-block-title {
        font-size: 0.82rem;
        font-weight: 600;
        color: var(--text-dim);
    }

    .showtime-form input,
    .showtime-form select {
        width: 100%;
        min-height: 46px;
        padding: 0.8rem 0.95rem;
        border-radius: 10px;
        border: 1px solid rgba(255, 255, 255, 0.09);
        background: #161722;
        color: var(--text);
        font-family: 'Outfit', sans-serif;
        font-size: 0.95rem;
        outline: none;
        transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
    }

    .showtime-form input::placeholder {
        color: #8d91a6;
    }

    .showtime-form input:focus,
    .showtime-form select:focus {
        border-color: rgba(229, 9, 20, 0.35);
        background: #1a1c28;
        box-shadow: 0 0 0 3px rgba(229, 9, 20, 0.10);
    }

    .showtime-form select {
        appearance: none;
        -webkit-appearance: none;
        -moz-appearance: none;
        background-image:
            linear-gradient(45deg, transparent 50%, #9ba1bb 50%),
            linear-gradient(135deg, #9ba1bb 50%, transparent 50%);
        background-position:
            calc(100% - 18px) calc(50% - 3px),
            calc(100% - 12px) calc(50% - 3px);
        background-size: 6px 6px, 6px 6px;
        background-repeat: no-repeat;
        padding-right: 2.5rem;
    }

    .showtime-select-group {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 0.75rem;
    }

    .showtime-select-group.type-group {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .showtime-form .select-btn {
        min-height: 44px;
        background: #1a1b28;
        color: #9aa0b5;
        border-color: rgba(255, 255, 255, 0.08);
    }

    .showtime-form .select-btn.active {
        background: linear-gradient(135deg, #d90b16 0%, #ff3341 100%);
        color: #fff;
        border-color: rgba(255, 71, 87, 0.75);
        box-shadow: 0 10px 24px rgba(229, 9, 20, 0.22);
    }

    .showtime-actions {
        margin-top: 0.25rem;
    }

    .admin-table-wrap {
        overflow-x: auto;
    }

    @media (max-width: 900px) {

        .showtime-grid,
        .showtime-select-group,
        .showtime-select-group.type-group {
            grid-template-columns: 1fr;
        }

        .admin-main {
            padding: 1rem;
        }
    }

    .btn-danger {
        background: rgba(229, 9, 20, 0.15);
        color: #ff6b6b;
        border: 1px solid rgba(229, 9, 20, 0.3);
        border-radius: 6px;
        padding: 4px 10px;
        font-size: 0.78rem;
        cursor: pointer;
        font-family: 'Outfit', sans-serif;
    }

    .btn-danger:hover {
        background: var(--accent);
        color: #fff;
    }

    .btn-sm-outline {
        background: transparent;
        color: var(--text-dim);
        border: 1px solid var(--border);
        border-radius: 6px;
        padding: 4px 10px;
        font-size: 0.78rem;
        cursor: pointer;
        font-family: 'Outfit', sans-serif;
    }

    .btn-sm-outline:hover {
        border-color: rgba(255, 255, 255, 0.3);
        color: var(--text);
    }

    .modal {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.7);
        z-index: 999;
        align-items: center;
        justify-content: center;
    }

    .modal.open {
        display: flex;
    }

    .modal-box {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: 2rem;
        width: 100%;
        max-width: 500px;
    }

    #editMovieModal .modal-box {
        max-width: 360px;
        padding: 1.25rem;
        max-height: min(88vh, 760px);
        overflow-y: auto;
    }

    #editMovieModal .modal-box h3 {
        font-size: 1.15rem;
        margin-bottom: 1rem;
        letter-spacing: 1px;
    }

    #editMovieModal .form-group {
        margin-bottom: 0.8rem;
    }

    #editMovieModal .form-group label {
        font-size: 0.78rem;
        margin-bottom: 0.3rem;
    }

    #editMovieModal input,
    #editMovieModal textarea,
    #editMovieModal select {
        font-size: 0.9rem;
        padding: 0.65rem 0.8rem;
    }

    #editMovieModal textarea {
        min-height: 76px;
    }

    .modal-box h3 {
        font-family: 'Bebas Neue', cursive;
        font-size: 1.5rem;
        letter-spacing: 2px;
        margin-bottom: 1.5rem;
    }

    .poster-preview {
        width: 100%;
        max-width: 180px;
        aspect-ratio: 2 / 3;
        border-radius: 12px;
        border: 1px solid var(--border);
        overflow: hidden;
        background: var(--bg-card2);
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--text-dim);
        font-size: 0.85rem;
        margin-bottom: 0.75rem;
    }

    #editMovieModal .poster-preview {
        max-width: 120px;
        border-radius: 10px;
        margin-bottom: 0.5rem;
    }

    .poster-preview img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }

    .poster-help {
        font-size: 0.8rem;
        color: var(--text-dim);
        margin-top: 0.35rem;
    }

    #editMovieModal .poster-help {
        font-size: 0.72rem;
        margin-top: 0.25rem;
    }

    .movie-cell {
        display: flex;
        align-items: center;
        gap: 0.85rem;
        min-width: 220px;
    }

    .movie-thumb {
        width: 54px;
        height: 78px;
        border-radius: 10px;
        object-fit: cover;
        border: 1px solid rgba(255, 255, 255, 0.08);
        background: var(--bg-card2);
        flex-shrink: 0;
    }

    .movie-thumb-fallback {
        width: 54px;
        height: 78px;
        border-radius: 10px;
        border: 1px solid rgba(255, 255, 255, 0.08);
        background: linear-gradient(180deg, rgba(229, 9, 20, 0.18), rgba(255, 255, 255, 0.03));
        color: var(--text-dim);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
        flex-shrink: 0;
    }

    .movie-cell-title {
        font-weight: 600;
        color: var(--text);
        line-height: 1.35;
    }

    .movie-cell-sub {
        margin-top: 0.18rem;
        color: var(--text-dim);
        font-size: 0.78rem;
    }

    .movie-status-form,
    .movie-action-group {
        display: flex;
        align-items: center;
        gap: 0.45rem;
        flex-wrap: wrap;
    }

    .status-toggle-btn {
        border-radius: 999px;
        padding: 0.38rem 0.8rem;
        font-size: 0.75rem;
        font-weight: 600;
        border: 1px solid transparent;
        cursor: pointer;
        font-family: 'Outfit', sans-serif;
        transition: all 0.2s;
    }

    .status-toggle-btn.is-live {
        background: rgba(34, 197, 94, 0.14);
        border-color: rgba(34, 197, 94, 0.28);
        color: #86efac;
    }

    .status-toggle-btn.is-live:hover {
        background: rgba(34, 197, 94, 0.22);
    }

    .status-toggle-btn.is-hidden {
        background: rgba(148, 163, 184, 0.12);
        border-color: rgba(148, 163, 184, 0.22);
        color: #cbd5e1;
    }

    .status-toggle-btn.is-hidden:hover {
        background: rgba(148, 163, 184, 0.18);
    }

    .action-btn {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        border-radius: 8px;
        padding: 0.42rem 0.78rem;
        font-size: 0.76rem;
        font-weight: 600;
        border: 1px solid rgba(255, 255, 255, 0.08);
        background: rgba(255, 255, 255, 0.02);
        color: var(--text-dim);
        cursor: pointer;
        font-family: 'Outfit', sans-serif;
        transition: all 0.2s;
    }

    .action-btn:hover {
        color: var(--text);
        border-color: rgba(255, 255, 255, 0.16);
        background: rgba(255, 255, 255, 0.05);
    }

    .action-btn.edit {
        color: #f8b4a8;
    }

    .action-btn.delete {
        color: #ff8b8b;
        background: rgba(229, 9, 20, 0.08);
        border-color: rgba(229, 9, 20, 0.16);
    }

    .action-btn.delete:hover {
        background: rgba(229, 9, 20, 0.15);
        border-color: rgba(229, 9, 20, 0.28);
    }

    .chart-wrap {
        height: 220px;
        position: relative;
    }

    .progress-bar {
        background: rgba(255, 255, 255, 0.08);
        border-radius: 4px;
        height: 8px;
        overflow: hidden;
        margin-top: 4px;
    }

    .progress-fill {
        height: 100%;
        background: var(--accent);
        border-radius: 4px;
    }

    .progress-fill.gold {
        background: var(--gold);
    }
    </style>
</head>

<body>

    <nav>
        <a href="index.php" class="nav-logo">CINE<span>MAX</span></a>
        <ul class="nav-links">
            <li><a href="index.php">หน้าแรก</a></li>
            <li><a href="profile.php" style="display:flex;align-items:center;gap:0.5rem;">
                    <div class="user-avatar"><?= strtoupper(substr($_SESSION['username'], 0, 1)) ?></div>
                </a></li>
            <li><a href="logout.php" class="btn-nav">ออกจากระบบ</a></li>
        </ul>
    </nav>

    <div class="admin-layout">
        <!-- Sidebar -->
        <div class="admin-sidebar">
            <div
                style="font-family:'Bebas Neue',cursive;font-size:1.1rem;letter-spacing:2px;color:var(--accent);margin-bottom:1rem;">
                ADMIN PANEL</div>
            <h3>เมนูหลัก</h3>
            <a href="admin.php?tab=dashboard"
                class="sidebar-link <?= $tab === 'dashboard' ? 'active' : '' ?>">Dashboard</a>

            <h3>Management</h3>
            <a href="admin.php?tab=movies" class="sidebar-link <?= $tab === 'movies' ? 'active' : '' ?>">รายการหนัง</a>
            <a href="admin.php?tab=bookings"
                class="sidebar-link <?= $tab === 'bookings' ? 'active' : '' ?>">รายการจอง</a>

            <a href="admin.php?tab=users" class="sidebar-link <?= $tab === 'users' ? 'active' : '' ?>">สมาชิก</a>
            <a href="admin.php?tab=showtimes"
                class="sidebar-link <?= $tab === 'showtimes' ? 'active' : '' ?>">รอบหนัง</a>

        </div>

        <!-- Main -->
        <div class="admin-main">

            <?php if ($tab === 'dashboard'): ?>
            <!-- ===== DASHBOARD ===== -->
            <h1 style="font-family:'Bebas Neue',cursive;font-size:2rem;letter-spacing:3px;margin-bottom:1.5rem;">
                Dashboard</h1>


            <div class="stats-grid">
                <div class="stat-card">
                    <div class="num" style="color:var(--accent);"><?= $s_movies ?></div>
                    <div class="lbl">Now Showing Movies</div>
                </div>
                <div class="stat-card">
                    <div class="num" style="color:#60a5fa;"><?= $s_users ?></div>
                    <div class="lbl">Total Users</div>
                </div>
                <div class="stat-card">
                    <div class="num" style="color:#4ade80;"><?= $s_bookings ?></div>
                    <div class="lbl">Confirmed Bookings</div>
                </div>
                <div class="stat-card">
                    <div class="num" style="color:var(--gold);">฿<?= number_format($s_revenue, 0) ?></div>
                    <div class="lbl">Total Revenue</div>
                </div>
                <div class="stat-card">
                    <div class="num" style="color:var(--gold);"><?= $s_vip ?></div>
                    <div class="lbl">VIP Seats Sold</div>
                </div>
                <div class="stat-card">
                    <div class="num"><?= $s_normal ?></div>
                    <div class="lbl">Normal Seats Sold</div>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;margin-bottom:1.5rem;">
                <!-- Revenue Chart -->
                <div class="admin-section">
                    <div class="admin-section-header">
                        <h2>Revenue (Last 7 Days)</h2>
                    </div>
                    <div style="padding:1.5rem;">
                        <?php if (empty($daily_data)): ?>
                        <div style="text-align:center;color:var(--text-dim);padding:2rem;">No data yet</div>
                        <?php else: ?>
                        <div class="chart-wrap">
                            <canvas id="revenueChart"></canvas>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- VIP vs Normal -->
                <div class="admin-section">
                    <div class="admin-section-header">
                        <h2>Seat Distribution</h2>
                    </div>
                    <div style="padding:1.5rem;">
                        <?php
                            $total_seats_sold = $s_vip + $s_normal;
                            $vip_pct = $total_seats_sold ? round($s_vip / $total_seats_sold * 100) : 0;
                            $normal_pct = 100 - $vip_pct;
                            ?>
                        <div style="margin-bottom:1.2rem;">
                            <div
                                style="display:flex;justify-content:space-between;font-size:0.85rem;margin-bottom:6px;">
                                <span style="color:var(--gold);">VIP (<?= $s_vip ?> seats)</span>
                                <span style="font-weight:700;"><?= $vip_pct ?>%</span>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill gold" style="width:<?= $vip_pct ?>%"></div>
                            </div>
                        </div>
                        <div>
                            <div
                                style="display:flex;justify-content:space-between;font-size:0.85rem;margin-bottom:6px;">
                                <span>Normal (<?= $s_normal ?> seats)</span>
                                <span style="font-weight:700;"><?= $normal_pct ?>%</span>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width:<?= $normal_pct ?>%"></div>
                            </div>
                        </div>
                        <div
                            style="margin-top:1.5rem;padding:1rem;background:rgba(255,255,255,0.04);border-radius:8px;font-size:0.85rem;">
                            <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
                                <span style="color:var(--text-dim);">Revenue from VIP</span>
                                <span
                                    style="color:var(--gold);font-weight:700;">฿<?= number_format($s_vip * PRICE_VIP, 0) ?></span>
                            </div>
                            <div style="display:flex;justify-content:space-between;">
                                <span style="color:var(--text-dim);">Revenue from Normal</span>
                                <span style="font-weight:700;">฿<?= number_format($s_normal * PRICE_NORMAL, 0) ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Top Movies -->
            <div class="admin-section">
                <div class="admin-section-header">
                    <h2>Top Movies</h2>
                </div>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Movie Title</th>
                            <th>Bookings</th>
                            <th>Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $rank = 1;
                            while ($m = $top_movies->fetch_assoc()): ?>
                        <tr>
                            <td><span
                                    style="font-family:'Bebas Neue',cursive;font-size:1.3rem;color:var(--accent);"><?= $rank++ ?></span>
                            </td>
                            <td style="font-weight:600;"><?= htmlspecialchars($m['title']) ?></td>
                            <td><?= $m['cnt'] ?> times</td>
                            <td style="color:var(--gold);font-weight:700;">฿<?= number_format($m['rev'], 0) ?></td>
                        </tr>
                        <?php endwhile;
                            if ($rank === 1): ?>
                        <tr>
                            <td colspan="4" style="text-align:center;color:var(--text-dim);padding:2rem;">No data yet
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php elseif ($tab === 'movies'): ?>
            <!-- ===== MOVIES ===== -->
            <?php if (isset($_GET['msg'])): ?>
            <div class="alert alert-success" style="margin-bottom:1rem;">Success:
                <?= $_GET['msg'] === 'added' ? 'Movie added successfully' : 'Movie updated or deleted successfully' ?>
            </div>
            <?php endif; ?>

            <div class="admin-section">
                <div class="admin-section-header">
                    <h2>Manage Movies</h2>
                    <button onclick="document.getElementById('addMovieModal').classList.add('open')"
                        class="btn btn-primary btn-sm">+ Add Movie</button>
                </div>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Title</th>
                            <th>Genre</th>
                            <th>Duration</th>
                            <th>Rating</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $mv = $conn->query("SELECT * FROM movies ORDER BY id DESC");
                            while ($m = $mv->fetch_assoc()): ?>
                        <tr>
                            <td style="color:var(--text-dim);">#<?= $m['id'] ?></td>
                            <td>
                                <div class="movie-cell">
                                    <?php if (!empty($m['poster']) && file_exists($m['poster'])): ?>
                                    <img src="<?= htmlspecialchars($m['poster']) ?>"
                                        alt="<?= htmlspecialchars($m['title']) ?>" class="movie-thumb">
                                    <?php else: ?>
                                    <div class="movie-thumb-fallback">IMG</div>
                                    <?php endif; ?>
                                    <div>
                                        <div class="movie-cell-title"><?= htmlspecialchars($m['title']) ?></div>
                                        <div class="movie-cell-sub">ID #<?= $m['id'] ?></div>
                                    </div>
                                </div>
                            </td>
                            <td style="color:var(--text-dim);font-size:0.82rem;"><?= htmlspecialchars($m['genre']) ?>
                            </td>
                            <td><?= $m['duration'] ?> min</td>
                            <td><span class="tag"><?= htmlspecialchars($m['rating']) ?></span></td>
                            <td>
                                <form method="POST" class="movie-status-form">
                                    <input type="hidden" name="action" value="toggle_movie">
                                    <input type="hidden" name="id" value="<?= $m['id'] ?>">
                                    <button class="status-toggle-btn <?= $m['is_showing'] ? 'is-live' : 'is-hidden' ?>">
                                        <?= $m['is_showing'] ? 'Now Showing' : 'Hidden' ?>
                                    </button>
                                </form>
                            </td>
                            <td>
                                <div class="movie-action-group">
                                    <button onclick="openEditModal(
                                            <?= $m['id'] ?>,
                                            '<?= htmlspecialchars($m['title'], ENT_QUOTES) ?>',
                                            '<?= htmlspecialchars($m['genre'], ENT_QUOTES) ?>',
                                            <?= $m['duration'] ?>,
                                            '<?= $m['rating'] ?>',
                                            '<?= htmlspecialchars($m['description'], ENT_QUOTES) ?>',
                                            '<?= htmlspecialchars($m['poster'] ?? '', ENT_QUOTES) ?>'
                                        )" class="action-btn edit" type="button">Edit</button>
                                    <form method="POST" style="display:inline;"
                                        onsubmit="return confirm('Delete this movie?')">
                                        <input type="hidden" name="action" value="delete_movie">
                                        <input type="hidden" name="id" value="<?= $m['id'] ?>">
                                        <button class="action-btn delete">Delete</button>
                                    </form>
                                </div>
                            </td>

                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <?php elseif ($tab === 'bookings'): ?>
            <!-- ===== BOOKINGS ===== -->
            <div class="admin-section">
                <div class="admin-section-header">
                    <h2>รายการจองทั้งหมด</h2>
                </div>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Movie</th>
                            <th>Showtime</th>
                            <th>Seats</th>
                            <th>VIP/Normal</th>
                            <th>Price</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                            $bks = $conn->query("
                SELECT b.*, u.username, m.title, s.show_date, s.show_time, s.hall
                FROM bookings b
                JOIN users u ON b.user_id=u.id
                JOIN showtimes s ON b.showtime_id=s.id
                JOIN movies m ON s.movie_id=m.id
                ORDER BY b.booked_at DESC LIMIT 50
            ");
                            while ($b = $bks->fetch_assoc()):
                            ?>
                        <tr>
                            <td style="color:var(--text-dim);">#<?= $b['id'] ?></td>
                            <td><?= htmlspecialchars($b['username']) ?></td>
                            <td style="font-size:0.82rem;font-weight:600;"><?= htmlspecialchars($b['title']) ?></td>
                            <td style="font-size:0.8rem;"><?= date('d/m', strtotime($b['show_date'])) ?>
                                <?= substr($b['show_time'], 0, 5) ?><br><span
                                    style="color:var(--text-dim);"><?= $b['hall'] ?></span>
                            </td>
                            <td style="font-size:0.8rem;"><?= htmlspecialchars($b['seats']) ?></td>
                            <td style="font-size:0.8rem;">
                                <?php if ($b['vip_count'] > 0): ?><span style="color:var(--gold);">VIP
                                    <?= $b['vip_count'] ?></span><?php endif; ?>
                                <?php if ($b['normal_count'] > 0): ?> Normal <?= $b['normal_count'] ?><?php endif; ?>
                            </td>
                            <td style="color:var(--gold);font-weight:700;">฿<?= number_format($b['total_price'], 0) ?>
                            </td>
                            <td><span
                                    class="status-badge status-<?= $b['status'] ?>"><?= $b['status'] === 'confirmed' ? 'Confirmed' : 'Cancelled' ?></span>
                            </td>
                            <td>
                                <?php if ($b['status'] === 'confirmed'): ?>
                                <form method="POST" style="display:inline;"
                                    onsubmit="return confirm('Cancel this booking?')">
                                    <input type="hidden" name="action" value="cancel_booking">
                                    <input type="hidden" name="id" value="<?= $b['id'] ?>">
                                    <button class="btn-danger">Cancel</button>
                                </form>
                                <?php else: ?>-<?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <?php elseif ($tab === 'users'): ?>
            <!-- ===== USERS ===== -->
            <div class="admin-section">
                <div class="admin-section-header">
                    <h2>จัดการสมาชิก</h2>
                </div>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Joined</th>
                            <th>Role</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $us = $conn->query("SELECT u.*, (SELECT COUNT(*) FROM bookings WHERE user_id=u.id AND status='confirmed') as bk FROM users u ORDER BY id");
                            while ($u = $us->fetch_assoc()): ?>
                        <tr>
                            <td style="color:var(--text-dim);">#<?= $u['id'] ?></td>
                            <td style="font-weight:600;">
                                <?= htmlspecialchars($u['username']) ?>
                                <?php if ($u['id'] == $_SESSION['user_id']): ?><span
                                    style="color:var(--accent);font-size:0.75rem;"> (You)</span><?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($u['fullname']) ?></td>
                            <td style="font-size:0.82rem;color:var(--text-dim);"><?= htmlspecialchars($u['email']) ?>
                            </td>
                            <td style="font-size:0.82rem;"><?= $u['phone'] ?: '-' ?></td>
                            <td style="font-size:0.8rem;color:var(--text-dim);">
                                <?= date('d/m/Y', strtotime($u['created_at'])) ?>
                            </td>
                            <td>
                                <?php if ($u['is_admin']): ?>
                                <span class="status-badge"
                                    style="background:rgba(245,197,24,0.15);color:var(--gold);">Admin</span>

                                <?php else: ?>
                                <span class="status-badge status-confirmed">User</span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="toggle_admin">
                                    <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                    <button class="btn-sm-outline">
                                        <?= $u['is_admin'] ? 'Remove Admin' : 'Make Admin' ?>
                                    </button>
                                </form>
                                <?php else: ?>-<?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <?php elseif ($tab === 'showtimes'): ?>

            <div class="admin-section">
                <div class="admin-section-header">
                    <h2>จัดการรอบหนัง</h2>
                </div>

                <!-- โ• เน€เธเธดเนเธกเธฃเธญเธ -->
                <form method="POST" class="showtime-form">
                    <input type="hidden" name="action" value="add_showtime">

                    <?php
                        $mv = $conn->query("SELECT id,title FROM movies");
                        ?>

                    <div class="showtime-grid">
                        <div class="showtime-field">
                            <label for="movie_id">Select Movie</label>
                            <select name="movie_id" id="movie_id" required>
                                <option value="">Select Movie</option>
                                <?php while ($m = $mv->fetch_assoc()): ?>
                                <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['title']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="showtime-field">
                            <label for="show_date">Show Date</label>
                            <input type="date" name="show_date" id="show_date" required>
                        </div>

                        <div class="showtime-field">
                            <label for="show_time">Show Time</label>
                            <input type="time" name="show_time" id="show_time" required>
                        </div>
                    </div>

                    <div class="showtime-field">
                        <div class="showtime-block-title">Select Hall</div>
                        <div class="showtime-select-group">
                            <input type="hidden" name="hall" id="hallInput" value="Hall A" required>
                            <button type="button" class="select-btn active" onclick="selectHall(this,'Hall A')">Hall
                                A</button>
                            <button type="button" class="select-btn" onclick="selectHall(this,'Hall B')">Hall B</button>
                            <button type="button" class="select-btn" onclick="selectHall(this,'Hall C')">Hall C</button>
                        </div>
                    </div>

                    <div class="showtime-field">
                        <div class="showtime-block-title">Seat Type</div>
                        <div class="showtime-select-group type-group">
                            <input type="hidden" name="hall_type" id="typeInput" value="normal">
                            <button type="button" class="select-btn active"
                                onclick="selectType(this,'normal')">Normal</button>
                            <button type="button" class="select-btn" onclick="selectType(this,'vip')">VIP</button>
                        </div>
                    </div>

                    <div class="showtime-grid">
                        <div class="showtime-field">
                            <label for="price">Price</label>
                            <input type="number" name="price" id="price" placeholder="Example 180" required>
                        </div>

                        <div class="showtime-field">
                            <label for="seats">Total Seats</label>
                            <input type="number" name="seats" id="seats" value="60" min="1">
                        </div>
                    </div>

                    <div class="showtime-actions">
                        <button class="btn btn-primary" style="width:100%;">Add Showtime</button>
                    </div>
                </form>

                <!-- ๐“ เธ•เธฒเธฃเธฒเธ -->
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Movie</th>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Hall</th>
                                <th>Price</th>
                                <th>Seats</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                                $sts = $conn->query("
            SELECT s.*, m.title 
            FROM showtimes s 
            JOIN movies m ON s.movie_id = m.id
            ORDER BY show_date, show_time
        ");
                                while ($s = $sts->fetch_assoc()):
                                ?>
                            <tr>
                                <td>#<?= $s['id'] ?></td>
                                <td><?= htmlspecialchars($s['title']) ?></td>
                                <td><?= $s['show_date'] ?></td>
                                <td><?= substr($s['show_time'], 0, 5) ?></td>
                                <td><?= $s['hall'] ?></td>
                                <td>฿<?= number_format($s['price'], 0) ?></td>
                                <td><?= $s['available_seats'] ?>/<?= $s['total_seats'] ?></td>
                                <td>
                                    <form method="POST" onsubmit="return confirm('Delete this showtime?')">
                                        <input type="hidden" name="action" value="delete_showtime">
                                        <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                        <button class="btn-danger">Delete</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

        </div><!-- admin-main -->
    </div><!-- admin-layout -->

    <!-- Modal: เน€เธเธดเนเธกเธซเธเธฑเธ -->
    <div class="modal" id="addMovieModal" onclick="if(event.target===this)this.classList.remove('open')">
        <div class="modal-box">
            <h3>Add New Movie</h3>

            <!-- ๐”ฅ เน€เธเธดเนเธก enctype -->
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add_movie">

                <div class="form-group">
                    <label>Movie Title</label>
                    <input type="text" name="title" required placeholder="Enter movie title">
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description"
                        style="width:100%;background:var(--bg-dark);border:1px solid var(--border);border-radius:8px;padding:0.75rem;color:var(--text);font-family:'Outfit',sans-serif;resize:vertical;"
                        rows="3" placeholder="Short description"></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Genre</label>
                        <input type="text" name="genre" placeholder="Action / Drama">
                    </div>
                    <div class="form-group">
                        <label>Duration (minutes)</label>
                        <input type="number" name="duration" value="120" min="1">
                    </div>
                </div>

                <div class="form-group">
                    <label>Rating</label>
                    <select name="rating">
                        <option>G</option>
                        <option>PG</option>
                        <option selected>PG-13</option>
                        <option>R</option>
                    </select>
                </div>

                <!-- ๐”ฅ เน€เธเธดเนเธกเธชเนเธงเธเธเธตเน -->
                <div class="form-group">
                    <label>Movie Poster</label>
                    <input type="file" name="poster" accept="image/*">
                </div>

                <div style="display:flex;gap:1rem;margin-top:1rem;">
                    <button type="submit" class="btn btn-primary">Add Movie</button>
                    <button type="button" class="btn btn-outline"
                        onclick="document.getElementById('addMovieModal').classList.remove('open')">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: เนเธเนเนเธเธซเธเธฑเธ -->
    <div class="modal" id="editMovieModal" onclick="if(event.target===this)this.classList.remove('open')">
        <div class="modal-box">
            <h3>Edit Movie</h3>

            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="edit_movie">
                <input type="hidden" name="id" id="edit_id">
                <input type="hidden" name="current_poster" id="edit_current_poster">

                <div class="form-group">
                    <label>Movie Title</label>
                    <input type="text" name="title" id="edit_title" required>
                </div>

                <div class="form-group">
                    <label>Genre</label>
                    <input type="text" name="genre" id="edit_genre">
                </div>

                <div class="form-group">
                    <label>Duration (minutes)</label>
                    <input type="number" name="duration" id="edit_duration">
                </div>

                <div class="form-group">
                    <label>Rating</label>
                    <input type="text" name="rating" id="edit_rating">
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" id="edit_description"></textarea>
                </div>

                <div class="form-group">
                    <label>Current Poster</label>
                    <div class="poster-preview" id="editPosterPreview">No poster yet</div>
                </div>

                <div class="form-group">
                    <label>Change Poster</label>
                    <input type="file" name="poster" id="edit_poster" accept="image/*">
                    <div class="poster-help">If you do not upload a new image, the current poster will be kept.</div>
                </div>

                <button class="btn btn-primary btn-full">Save Changes</button>
            </form>
        </div>
    </div>

    <script>
    <?php if (!empty($daily_data)): ?>
    const ctx = document.getElementById('revenueChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($daily_labels) ?>,
            datasets: [{
                label: 'Revenue (THB)',
                data: <?= json_encode($daily_data) ?>,
                backgroundColor: 'rgba(229,9,20,0.6)',
                borderColor: '#e50914',
                borderWidth: 1,
                borderRadius: 6,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    ticks: {
                        color: '#888',
                        callback: v => '฿' + v.toLocaleString()
                    },
                    grid: {
                        color: 'rgba(255,255,255,0.05)'
                    }
                },
                x: {
                    ticks: {
                        color: '#888'
                    },
                    grid: {
                        display: false
                    }
                }
            }
        }
    });
    <?php endif; ?>

    // ๐”ฅ เน€เธเธดเนเธก 2 เธ•เธฑเธงเธเธตเนเน€เธเนเธฒเนเธ
    function selectHall(el, value) {
        document.querySelectorAll('[onclick^="selectHall"]').forEach(btn => {
            btn.classList.remove('active');
        });

        el.classList.add('active');
        document.getElementById('hallInput').value = value;
    }

    function selectType(el, value) {
        document.querySelectorAll('[onclick^="selectType"]').forEach(btn => {
            btn.classList.remove('active');
        });

        el.classList.add('active');
        document.getElementById('typeInput').value = value;

        const price = document.querySelector('[name="price"]');
        const seats = document.querySelector('[name="seats"]');

        if (value === 'vip') {
            price.value = 250;
            seats.value = 20;
        } else {
            price.value = 150;
            seats.value = 60;
        }
    }

    function openEditModal(id, title, genre, duration, rating, description, poster) {
        document.getElementById('edit_id').value = id;
        document.getElementById('edit_title').value = title;
        document.getElementById('edit_genre').value = genre;
        document.getElementById('edit_duration').value = duration;
        document.getElementById('edit_rating').value = rating;
        document.getElementById('edit_description').value = description;
        document.getElementById('edit_current_poster').value = poster || '';
        document.getElementById('edit_poster').value = '';

        const preview = document.getElementById('editPosterPreview');
        if (poster) {
            preview.innerHTML = `<img src="${poster}" alt="Movie poster">`;
        } else {
            preview.textContent = 'เธขเธฑเธเนเธกเนเธกเธต poster';
        }

        document.getElementById('editMovieModal').classList.add('open');
    }
    preview.textContent = 'No poster yet';
    document.getElementById('edit_poster')?.addEventListener('change', function(event) {
        const file = event.target.files && event.target.files[0];
        const preview = document.getElementById('editPosterPreview');

        if (!file) {
            const currentPoster = document.getElementById('edit_current_poster').value;
            if (currentPoster) {
                preview.innerHTML = `<img src="${currentPoster}" alt="Movie poster">`;
            } else {
                preview.textContent = 'No poster yet';
            }
            return;
        }

        const reader = new FileReader();
        reader.onload = function(e) {
            preview.innerHTML = `<img src="${e.target.result}" alt="New movie poster">`;
        };
        reader.readAsDataURL(file);
    });
    </script>

</body>

</html>