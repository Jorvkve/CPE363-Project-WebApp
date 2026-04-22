<?php
// === 1. การตั้งค่าตัวแปรสำหรับเชื่อมต่อฐานข้อมูล (Database Configuration) ===
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'cinemax');

// === 2. การตั้งค่าราคาและประเภทที่นั่ง (Pricing & Seat Configuration) ===
define('PRICE_NORMAL', 180);
define('PRICE_VIP',    250);
define('VIP_ROWS', ['A', 'B']); // แถว A,B = VIP ใกล้จอ

// === 3. สร้างการเชื่อมต่อกับฐานข้อมูล (Database Connection) ===
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$conn->set_charset("utf8mb4");

// ตรวจสอบว่าการเชื่อมต่อมีปัญหาหรือไม่
if ($conn->connect_error) {
    die("<div style='color:red;text-align:center;padding:50px;font-family:sans-serif'>
        ❌ เชื่อมต่อฐานข้อมูลไม่ได้: " . $conn->connect_error . "
        <br><small>กรุณาเปิด XAMPP และรัน database.sql ก่อน</small></div>");
}

// === 4. เริ่มต้นการใช้งาน Session เพื่อเก็บข้อมูลการล็อกอินของผู้ใช้ ===
session_start();

// === 5. ฟังก์ชันตรวจสอบสิทธิ์การใช้งาน (Authentication Functions) ===

// ฟังก์ชันเช็คว่าผู้ใช้ล็อกอินอยู่หรือไม่
function isLoggedIn()
{
    return isset($_SESSION['user_id']);
}

// ฟังก์ชันเช็คว่าผู้ใช้เป็นแอดมินหรือไม่
function isAdmin()
{
    return !empty($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;
}

// ฟังก์ชันบังคับให้ต้องล็อกอินก่อนเข้าถึงหน้านั้นๆ (ถ้ายังไม่ล็อกอินจะเด้งไปหน้า login)
function requireLogin()
{
    if (!isLoggedIn()) {
        header("Location: login.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
        exit();
    }
}

// ฟังก์ชันบังคับว่าต้องเป็นแอดมินเท่านั้นถึงเข้าหน้านั้นๆได้
function requireAdmin()
{
    if (!isLoggedIn() || !isAdmin()) {
        header("Location: index.php");
        exit();
    }
}
// === 6. ฟังก์ชันจัดการที่นั่งและคำนวณราคา (Seat & Pricing Functions) ===

// ฟังก์ชันหาคำนวณราคาจากรหัสที่นั่ง (เช่น A1 คือ VIP, C5 คือ Normal)
function getSeatPrice(string $seat_id): int
{
    $row = strtoupper(substr(trim($seat_id), 0, 1)); // ดึงตัวอักษรแถว (ตัวแรก)
    return in_array($row, VIP_ROWS) ? PRICE_VIP : PRICE_NORMAL;
}
// ฟังก์ชันตรวจสอบประเภทที่นั่งว่าเป็น VIP หรือ Normal
function getSeatType(string $seat_id): string
{
    $row = strtoupper(substr(trim($seat_id), 0, 1));
    return in_array($row, VIP_ROWS) ? 'vip' : 'normal';
}
/**
 * ฟังก์ชันคำนวณราคารวมและจำนวนที่นั่งแต่ละประเภทจากอาร์เรย์ที่นั่ง
 * @param array<int, string> $seats_arr (เช่น ['A1', 'C5', 'C6'])
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
