<?php
// === 1. นำเข้าไฟล์ตั้งค่า ===
require_once 'config.php';

// === 2. ล้างข้อมูล Session ทั้งหมดเพื่อทำการออกจากระบบ ===
session_destroy();

// === 3. เปลี่ยนหน้ากลับไปยังหน้าแรก (index.php) ===
header("Location: index.php");
exit();
?>
