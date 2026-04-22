# 🎬 CineMax Movie Booking System

<p align="center">
  <b>ระบบจองตั๋วหนังออนไลน์</b><br>
  Developed with PHP & MySQL
</p>

<p align="center">
  <img src="https://img.shields.io/badge/PHP-Backend-blue">
  <img src="https://img.shields.io/badge/MySQL-Database-orange">
  <img src="https://img.shields.io/badge/Status-Completed-success">
</p>

---

## ✨ Overview

CineMax เป็นระบบจองตั๋วหนังออนไลน์ที่ช่วยให้ผู้ใช้งานสามารถ  
เลือกหนัง เลือกรอบ เลือกที่นั่ง และชำระเงินผ่านเว็บไซต์ได้อย่างสะดวก  

ระบบถูกออกแบบให้มีทั้ง:
- 👤 ฝั่งผู้ใช้งาน (User)
- 🛠️ ฝั่งผู้ดูแลระบบ (Admin)

พร้อมฟีเจอร์เสริม เช่น Email Ticket, QR Code และ Dashboard

---

## 🚀 Features

### 👤 User Features
- 🔍 ค้นหาและดูรายการหนัง
- 🎟️ เลือกรอบฉาย
- 💺 เลือกที่นั่ง (VIP / Normal)
- 💳 ชำระเงิน
- 📩 รับตั๋วผ่าน Email
- 🎫 แสดง QR Code สำหรับตั๋ว

---

### 🛠️ Admin Features
- 🎬 จัดการหนัง (เพิ่ม / แก้ไข / ลบ)
- 🕐 จัดการรอบหนัง
- 👥 จัดการสมาชิก
- 📊 Dashboard แสดงรายได้และสถิติ
- ❌ ป้องกันการลบรอบที่มีการจอง

---

## 🧠 Technologies Used

| Technology | Description |
|----------|------------|
| PHP | Backend Logic |
| MySQL | Database |
| HTML/CSS | UI Design |
| JavaScript | Interaction |
| Chart.js | Dashboard Graph |
| PHPMailer | Email System |
| QR Code API | Generate Ticket QR |

---

## 🏗️ System Architecture

```text
Client (Browser)
      ↓
   PHP Server
      ↓
   MySQL Database
