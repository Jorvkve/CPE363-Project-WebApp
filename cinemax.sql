-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 22, 2026 at 05:27 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `cinemax`
--

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `showtime_id` int(11) NOT NULL,
  `seats` varchar(255) NOT NULL COMMENT 'เช่น A1,A2,B3',
  `total_price` decimal(10,2) NOT NULL,
  `status` enum('pending','confirmed','cancelled') DEFAULT 'confirmed',
  `booked_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `vip_count` int(11) DEFAULT 0,
  `normal_count` int(11) DEFAULT 0,
  `seat_types` text DEFAULT NULL,
  `payment_status` enum('pending','paid','cancelled') DEFAULT 'pending',
  `payment_ref` varchar(100) DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`id`, `user_id`, `showtime_id`, `seats`, `total_price`, `status`, `booked_at`, `vip_count`, `normal_count`, `seat_types`, `payment_status`, `payment_ref`, `paid_at`) VALUES
(19, 1, 26, 'C6,C5', 360.00, 'confirmed', '2026-04-22 10:13:10', 0, 2, 'normal,normal', 'paid', 'PAY1776852792639', '2026-04-22 17:13:12'),
(20, 4, 30, 'C5,C6', 360.00, 'confirmed', '2026-04-22 10:39:20', 0, 2, 'normal,normal', 'paid', 'PAY1776854364155', '2026-04-22 17:39:24'),
(21, 1, 27, 'C6,B7', 430.00, 'confirmed', '2026-04-22 11:04:01', 1, 1, 'normal,vip', 'paid', 'PAY1776855849294', '2026-04-22 18:04:09'),
(22, 1, 28, 'E9,E7', 360.00, 'confirmed', '2026-04-22 11:19:33', 0, 2, 'normal,normal', 'paid', 'PAY1776856775164', '2026-04-22 18:19:35'),
(23, 1, 30, 'E6,E5', 360.00, 'confirmed', '2026-04-22 11:42:53', 0, 2, 'normal,normal', 'paid', 'PAY1776858175612', '2026-04-22 18:42:55'),
(24, 1, 26, 'C7,C8', 360.00, 'confirmed', '2026-04-22 14:42:11', 0, 2, 'normal,normal', 'paid', 'PAY1776868933713', '2026-04-22 21:42:13'),
(25, 1, 26, 'C3,C4', 360.00, 'cancelled', '2026-04-22 14:42:35', 0, 2, 'normal,normal', 'cancelled', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `movies`
--

CREATE TABLE `movies` (
  `id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `genre` varchar(100) DEFAULT NULL,
  `duration` int(11) DEFAULT NULL COMMENT 'นาที',
  `rating` varchar(10) DEFAULT NULL,
  `poster` varchar(255) DEFAULT NULL,
  `is_showing` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `movies`
--

INSERT INTO `movies` (`id`, `title`, `description`, `genre`, `duration`, `rating`, `poster`, `is_showing`, `created_at`) VALUES
(1, 'Dune: Part Three', 'การต่อสู้ครั้งสุดท้ายบนดาวเคราะห์ทะเลทราย Paul Atreides นำทัพพิฆาตจักรวรรดิ', 'Sci-Fi / Adventure', 165, 'PG-13', 'uploads/1776853872_8103.jpg', 1, '2026-04-09 07:50:21'),
(2, 'The Dark Knight Returns', 'แบทแมนกลับมาอีกครั้งเพื่อปกป้อง Gotham จากภัยคุกคามใหม่ที่ไม่เคยเจอมาก่อน', 'Action / Superhero', 152, 'PG-13', 'uploads/1776853616_1684.jpg', 1, '2026-04-09 07:50:21'),
(3, 'Spirited Away 2', 'การผจญภัยครั้งใหม่ของชิฮิโระในโลกวิญญาณ ผลงานล่าสุดจาก Studio Ghibli', 'Animation / Fantasy', 128, 'G', 'uploads/1776852718_8916.jpg', 1, '2026-04-09 07:50:21'),
(5, 'The Grand Budapest Hotel 2', 'การผจญภัยสุดแปลกประหลาดของ Zero กับความลับของโรงแรมในยุโรป', 'Comedy / Drama', 115, 'PG', 'uploads/1776852682_2268.jpg', 1, '2026-04-09 07:50:21'),
(6, 'Spider-Man 1 ไอ้แมงมุม', 'หนุ่มวัยรุ่นกำพร้า ปีเตอร์ พาร์เกอร์ (โทบีย์ แมไกวร์) อาศัยอยู่กับป้าเมย์ (โรสแมรี แฮร์ริส) และลุงเบน (คลิฟฟ์ รอเบิร์ตสัน) อันเป็นที่รัก ในเมืองควีนส์ นิวยอร์ก ปีเตอร์เป็นเพียงเด็กนักเรียนธรรมดา ๆ ซึ่งเรียนไปพร้อมกับทำหน้าที่เป็นช่างภาพอยู่ที่ Daily Bugle ภายใต้การติวเข้มของ เจ โจนาห์ เจมส์สัน (เจ เค ซิมมอนส์) เขาแอบหลงรักสาวสวย แมรีเจน วัตสัน (เคิร์สเตน ดันส์ท) อยู่อย่างเงียบ ๆ และมีเพื่อนคู่หูคือ แฮร์รี ออสบอร์น (เจมส์ ฟรังโก) ในการทัศนศึกษาครั้งหนึ่ง ขณะที่ทั้งปีเตอร์และเพื่อนร่วมชั้น กำลังสาธิตการทดลองทางวิทยาศาสตร์เกี่ยวกับแมงมุมอยู่นั้น ปีเตอร์ก็โดนเจ้าแมงมุมตัวหนึ่ง ที่ถูกดัดแปลงทางพันธุกรรมกัดเข้า จากนั้นไม่ช้าไม่นาน เขาก็พบว่าตัวเขามีพลังพิเศษขึ้นมา เขาได้รับพละกำลัง และความปราดเปรียวพร้อมด้วยความฉลาดเฉลียว ซึ่งค่อนข้างแน่ใจได้ว่าเป็น \"พลังแมงมุม\"', 'Action', 120, 'PG-13', 'uploads/1775731031_1009.jpg', 1, '2026-04-09 10:37:11'),
(7, 'JUJUTSU KAISEN : HIDDEN INVENTORY', 'เล่าเรื่องราวช่วงปี 2006 สมัยที่โกะโจ ซาโตรุ และเกะโท สุงุรุ ยังเป็นนักเรียนปี 2 โรงเรียนไสยเวท โดยได้รับภารกิจสำคัญในการคุ้มครองและส่งตัว \"ริโกะ อามานาอิ\" ภาชนะพลาสมาดวงดาว ไปหาอาจารย์ใหญ่เทนเกน ซึ่งภารกิจนี้จะเป็นจุดเปลี่ยนสำคัญที่ทำให้มิตรภาพของทั้งสองต้องแตกหักและเดินบนเส้นทางที่ต่างกัน', 'Animation / Action / Fantasy', 120, 'R', 'uploads/1776853578_3749.jpg', 1, '2026-04-22 10:26:18'),
(8, 'it', 'ในเมืองแดร์รี่ รัฐเมน เมื่อเด็กๆ เริ่มหายตัวไปอย่างลึกลับ กลุ่มเด็กนอกคอก 7 คนที่เรียกตัวเองว่า \"The Losers\' Club\" ต้องเผชิญหน้ากับความกลัวที่เลวร้ายที่สุด เมื่อพวกเขาถูกตามล่าโดยปีศาจในร่างตัวตลกที่ชื่อว่า เพนนีไวซ์ ซึ่งจะปรากฏตัวออกมาทุกๆ 27 ปี', 'Horror', 135, 'R', 'uploads/1776871293_6972.jpg', 1, '2026-04-22 15:21:33');

-- --------------------------------------------------------

--
-- Table structure for table `showtimes`
--

CREATE TABLE `showtimes` (
  `id` int(11) NOT NULL,
  `movie_id` int(11) NOT NULL,
  `show_date` date NOT NULL,
  `show_time` time NOT NULL,
  `hall` varchar(50) NOT NULL,
  `total_seats` int(11) DEFAULT 60,
  `available_seats` int(11) DEFAULT 60,
  `price` decimal(10,2) DEFAULT 180.00,
  `hall_type` enum('normal','vip') DEFAULT 'normal'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `showtimes`
--

INSERT INTO `showtimes` (`id`, `movie_id`, `show_date`, `show_time`, `hall`, `total_seats`, `available_seats`, `price`, `hall_type`) VALUES
(26, 2, '2026-04-22', '18:00:00', 'Hall A', 30, 26, 150.00, 'normal'),
(27, 7, '2026-04-23', '13:00:00', 'Hall A', 50, 48, 150.00, 'normal'),
(28, 5, '2026-04-23', '13:30:00', 'Hall B', 60, 58, 60.00, 'normal'),
(29, 1, '2026-04-23', '15:00:00', 'Hall C', 100, 100, 180.00, 'normal'),
(30, 3, '2026-04-24', '09:30:00', 'Hall C', 50, 46, 50.00, 'normal'),
(31, 6, '2026-04-30', '12:45:00', 'Hall C', 60, 60, 90.00, 'normal'),
(32, 7, '2026-04-23', '20:00:00', 'Hall C', 80, 80, 65.00, 'normal'),
(33, 7, '2026-04-23', '23:00:00', 'Hall B', 50, 50, 90.00, 'normal'),
(34, 1, '2026-04-23', '08:00:00', 'Hall A', 60, 60, 180.00, 'normal'),
(35, 2, '2026-04-22', '22:00:00', 'Hall A', 60, 60, 180.00, 'normal'),
(36, 2, '2026-04-23', '10:30:00', 'Hall A', 60, 60, 85.00, 'normal');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `fullname` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `role` enum('user','admin') DEFAULT 'user',
  `is_admin` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `fullname`, `phone`, `created_at`, `role`, `is_admin`) VALUES
(1, 'Jorvkve', 'Turth00@gmail.com', '$2y$10$8BgFl.i9LPWuhyIrx97s1.QnLBXqgqP1Um0rgUN8p4Rhn8f5MmhJG', 'เตวิช จันทร์คง', '0925968363', '2026-04-09 07:52:06', 'admin', 1),
(2, 'admin', 'admin@gmail.com', '$2y$10$h9dW7YYHFJTXzVFVhMmfb.WtdfUMJuXdiUamPkTSWqNW/6CRnmJ16', 'admin cinemax', '0925968363', '2026-04-22 08:10:42', 'admin', 1),
(4, 'orawan', 'orawan2003y@gmail.com', '$2y$10$J9rMR6z9p7H753RwSCNyKOaHsAMFrPUpXVoCrfr55aE6grWK2q5Km', 'อรวรรณ พุ่มเจริญ', '0631329067', '2026-04-22 10:38:24', 'user', 1);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `showtime_id` (`showtime_id`);

--
-- Indexes for table `movies`
--
ALTER TABLE `movies`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `showtimes`
--
ALTER TABLE `showtimes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `movie_id` (`movie_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `movies`
--
ALTER TABLE `movies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `showtimes`
--
ALTER TABLE `showtimes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `bookings_ibfk_2` FOREIGN KEY (`showtime_id`) REFERENCES `showtimes` (`id`),
  ADD CONSTRAINT `bookings_ibfk_3` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `bookings_ibfk_4` FOREIGN KEY (`showtime_id`) REFERENCES `showtimes` (`id`);

--
-- Constraints for table `showtimes`
--
ALTER TABLE `showtimes`
  ADD CONSTRAINT `showtimes_ibfk_1` FOREIGN KEY (`movie_id`) REFERENCES `movies` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `showtimes_ibfk_2` FOREIGN KEY (`movie_id`) REFERENCES `movies` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
