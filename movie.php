<?php
require_once 'config.php';

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    header("Location: index.php");
    exit();
}

$stmt = $conn->prepare("SELECT * FROM movies WHERE id = ? AND is_showing = 1");
$stmt->bind_param("i", $id);
$stmt->execute();
$movie = $stmt->get_result()->fetch_assoc();

if (!$movie) {
    header("Location: index.php");
    exit();
}

$showtimes = $conn->prepare("
    SELECT *
    FROM showtimes
    WHERE movie_id = ? AND show_date >= CURDATE()
    ORDER BY show_date, show_time
");
$showtimes->bind_param("i", $id);
$showtimes->execute();
$showtimes_result = $showtimes->get_result();

$showtime_groups = [];
while ($st = $showtimes_result->fetch_assoc()) {
    $showtime_groups[$st['show_date']][] = $st;
}

$available_dates = array_keys($showtime_groups);
$selected_date = $_GET['date'] ?? ($available_dates[0] ?? null);
if ($selected_date !== null && !isset($showtime_groups[$selected_date]) && !empty($available_dates)) {
    $selected_date = $available_dates[0];
}

$selected_showtimes = $selected_date !== null ? ($showtime_groups[$selected_date] ?? []) : [];

$emojis = [1 => '🚀', 2 => '🎇', 3 => '🌌', 4 => '🎭', 5 => '🏰'];
$emoji = $emojis[$id] ?? '🎬';
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($movie['title']) ?> - CineMax</title>
    <link rel="stylesheet" href="css/style.css">
</head>

<body>

    <nav>
        <a href="index.php" class="nav-logo">CINE<span>MAX</span></a>
        <ul class="nav-links">
            <li><a href="index.php">Home</a></li>
            <?php if (isLoggedIn()): ?>
                <li><a href="mybooking.php">My Bookings</a></li>
                <li><a href="logout.php" class="btn-nav">Logout</a></li>
            <?php else: ?>
                <li><a href="login.php">Login</a></li>
                <li><a href="register.php" class="btn-nav">Register</a></li>
            <?php endif; ?>
        </ul>
    </nav>

    <div class="movie-detail-hero">
        <div class="movie-detail-grid">
            <div class="movie-detail-poster">
                <?php if (!empty($movie['poster']) && file_exists($movie['poster'])): ?>
                    <img src="<?= htmlspecialchars($movie['poster']) ?>" alt="<?= htmlspecialchars($movie['title']) ?>" style="width:100%;height:100%;object-fit:cover;">
                <?php else: ?>
                    <?= $emoji ?>
                <?php endif; ?>
            </div>
            <div>
                <div style="color:var(--accent);font-size:0.85rem;font-weight:600;letter-spacing:2px;margin-bottom:0.5rem;">NOW SHOWING</div>
                <h1 class="movie-detail-title"><?= htmlspecialchars($movie['title']) ?></h1>

                <div class="movie-tags">
                    <?php foreach (explode('/', $movie['genre']) as $g): ?>
                        <span class="tag"><?= trim($g) ?></span>
                    <?php endforeach; ?>
                    <span class="tag"><?= $movie['duration'] ?> min</span>
                    <span class="tag"><?= htmlspecialchars($movie['rating']) ?></span>
                </div>

                <p class="movie-desc"><?= htmlspecialchars($movie['description']) ?></p>

                <div style="display:flex;gap:1.5rem;margin-bottom:1.5rem;font-size:0.9rem;flex-wrap:wrap;">
                    <div>
                        <div style="color:var(--text-dim);font-size:0.8rem;">Genre</div>
                        <div style="font-weight:600;"><?= htmlspecialchars($movie['genre']) ?></div>
                    </div>
                    <div>
                        <div style="color:var(--text-dim);font-size:0.8rem;">Duration</div>
                        <div style="font-weight:600;"><?= $movie['duration'] ?> min</div>
                    </div>
                    <div>
                        <div style="color:var(--text-dim);font-size:0.8rem;">Rating</div>
                        <div style="font-weight:600;"><?= htmlspecialchars($movie['rating']) ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="section">
        <h2 class="section-title">Choose Showtime</h2>

        <?php if (!empty($available_dates)): ?>
            <div class="showtime-date-strip">
                <?php foreach ($available_dates as $date): ?>
                    <?php $is_active = $date === $selected_date; ?>
                    <a href="movie.php?id=<?= $id ?>&date=<?= urlencode($date) ?>" class="showtime-date-chip <?= $is_active ? 'active' : '' ?>">
                        <span class="showtime-date-weekday"><?= date('D', strtotime($date)) ?></span>
                        <span class="showtime-date-day"><?= date('d', strtotime($date)) ?></span>
                        <span class="showtime-date-month"><?= date('M Y', strtotime($date)) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>

            <?php if (!empty($selected_showtimes)): ?>
                <div class="showtime-selected-date"><?= date('D, d M Y', strtotime($selected_date)) ?></div>

                <div class="showtime-list">
                    <?php foreach ($selected_showtimes as $st): ?>
                        <a href="booking.php?showtime=<?= $st['id'] ?>" class="showtime-btn">
                            <div class="showtime-time"><?= substr($st['show_time'], 0, 5) ?></div>
                            <div class="showtime-hall"><?= $st['hall'] ?></div>
                            <div class="showtime-price">฿<?= number_format($st['price'], 0) ?></div>
                            <div style="font-size:0.7rem;color:var(--text-dim);margin-top:2px;">
                                <?= $st['available_seats'] ?> seats left
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!isLoggedIn()): ?>
                <div style="margin-top:1.5rem;padding:1rem 1.5rem;background:rgba(229,9,20,0.1);border:1px solid rgba(229,9,20,0.3);border-radius:10px;font-size:0.9rem;">
                    Please <a href="login.php" style="color:var(--accent);font-weight:700;">log in</a> before booking your ticket.
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon">📅</div>
                <h3>No showtimes available yet</h3>
                <p>Please check back again later.</p>
            </div>
        <?php endif; ?>

        <div style="margin-top:2rem;">
            <a href="index.php" class="btn btn-outline">← Back to home</a>
        </div>
    </div>

    <footer>
        <strong>CINEMAX</strong> &copy; <?= date('Y') ?>
    </footer>

</body>

</html>
