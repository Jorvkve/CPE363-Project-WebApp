<?php
require 'src/PHPMailer.php';
require 'src/SMTP.php';
require 'src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * @param array{id: int|string, title: string, show_date: string, show_time: string, seats: string, hall: string} $data
 */
function sendTicketEmail(string $toEmail, array $data): bool
{
    $mail = new PHPMailer(true);

    try {
        // 🔥 SMTP Config
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'Turth00@gmail.com'; // ⭐ ใส่ Gmail
        $mail->Password = 'ketw hgct yuje mnlh'; // ⭐ App Password
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;

        // 🔥 ป้องกันภาษาเพี้ยน
        $mail->CharSet = 'UTF-8';

        // 🔥 Debug (ถ้ามีปัญหาให้เปลี่ยนเป็น 2)
        $mail->SMTPDebug = 0;

        // 🔥 ผู้ส่ง
        $mail->setFrom('Turth00@gmail.com', 'CineMax');

        // 🔥 ผู้รับ
        $mail->addAddress($toEmail);

        // 🔥 เนื้อหา
        $mail->isHTML(true);
        $mail->Subject = '🎟️ CineMax Ticket';

        $mail->Body = "
<div style='background:#0b0f1a;padding:20px;font-family:Arial,sans-serif;color:#fff'>
    
    <div style='max-width:500px;margin:0 auto;background:#111827;border-radius:12px;overflow:hidden'>
        
        <!-- HEADER -->
        <div style='background:linear-gradient(90deg,#e50914,#7f1d1d);padding:20px;text-align:center'>
            <h1 style='margin:0;color:#fff;'>🎬 CineMax</h1>
            <p style='margin:5px 0 0;color:#ddd;font-size:14px;'>Movie Ticket</p>
        </div>

        <!-- BODY -->
        <div style='padding:20px'>
            
            <h2 style='margin-top:0;color:#facc15;'>🎟️ ตั๋วของคุณ</h2>

            <table style='width:100%;font-size:14px;margin-top:10px'>
                <tr>
                    <td style='color:#9ca3af;'>หนัง</td>
                    <td style='text-align:right;font-weight:bold;'>{$data['title']}</td>
                </tr>
                <tr>
                    <td style='color:#9ca3af;'>วันเวลา</td>
                    <td style='text-align:right;'>{$data['show_date']} {$data['show_time']}</td>
                </tr>
                <tr>
                    <td style='color:#9ca3af;'>ที่นั่ง</td>
                    <td style='text-align:right;font-weight:bold;'>{$data['seats']}</td>
                </tr>
                <tr>
                    <td style='color:#9ca3af;'>โรง</td>
                    <td style='text-align:right;'>{$data['hall']}</td>
                </tr>
            </table>

            <!-- DIVIDER -->
            <hr style='border:none;border-top:1px dashed #374151;margin:20px 0'>

            <!-- QR -->
            <div style='text-align:center'>
                <p style='font-size:12px;color:#9ca3af;'>แสดง QR นี้ที่หน้าโรงภาพยนตร์</p>
                <img src='https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=CINEMAX-{$data['id']}' 
                     style='border-radius:8px;background:#fff;padding:5px'>
            </div>

        </div>

        <!-- FOOTER -->
        <div style='background:#0b0f1a;padding:15px;text-align:center;font-size:12px;color:#9ca3af'>
            ขอบคุณที่ใช้บริการ CineMax 🙏
        </div>

    </div>
</div>
";

        $mail->send();

        return true;
    } catch (Exception $e) {
        echo "❌ ส่งเมลไม่สำเร็จ: {$mail->ErrorInfo}";
        return false;
    }
}
