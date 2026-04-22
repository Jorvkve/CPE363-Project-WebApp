<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'cinemax');

define('PRICE_NORMAL', 180);
define('PRICE_VIP',    250);
define('VIP_ROWS', ['A', 'B']); // แถว A,B = VIP ใกล้จอ

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    die("<div style='color:red;text-align:center;padding:50px;font-family:sans-serif'>
        ❌ เชื่อมต่อฐานข้อมูลไม่ได้: " . $conn->connect_error . "
        <br><small>กรุณาเปิด XAMPP และรัน database.sql ก่อน</small></div>");
}

session_start();

function isLoggedIn()
{
    return isset($_SESSION['user_id']);
}
function isAdmin()
{
    return !empty($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;
}

function requireLogin()
{
    if (!isLoggedIn()) {
        header("Location: login.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
        exit();
    }
}
function requireAdmin()
{
    if (!isLoggedIn() || !isAdmin()) {
        header("Location: index.php");
        exit();
    }
}
// คำนวณราคาจาก seat label (A1=VIP, C5=Normal)
function getSeatPrice(string $seat_id): int
{
    $row = strtoupper(substr(trim($seat_id), 0, 1));
    return in_array($row, VIP_ROWS) ? PRICE_VIP : PRICE_NORMAL;
}
function getSeatType(string $seat_id): string
{
    $row = strtoupper(substr(trim($seat_id), 0, 1));
    return in_array($row, VIP_ROWS) ? 'vip' : 'normal';
}
/**
 * @param array<int, string> $seats_arr
 * @return array{total: int, vip: int, normal: int}
 */
function calcTotalPrice(array $seats_arr): array
{
    $total = 0;
    $vip = 0;
    $normal = 0;
    foreach ($seats_arr as $s) {
        $s = trim($s);
        if (!$s) continue;
        $total += getSeatPrice($s);
        getSeatType($s) === 'vip' ? $vip++ : $normal++;
    }
    return ['total' => $total, 'vip' => $vip, 'normal' => $normal];
}
