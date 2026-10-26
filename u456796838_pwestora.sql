-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Oct 26, 2026 at 01:21 PM
-- Server version: 11.8.9-MariaDB-log
-- PHP Version: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `u456796838_pwestora`
--

-- --------------------------------------------------------

--
-- Table structure for table `applications`
--

CREATE TABLE `applications` (
  `id` int(11) NOT NULL,
  `property_id` int(11) NOT NULL,
  `lessor_id` int(11) NOT NULL,
  `lessee_id` int(11) DEFAULT NULL,
  `tenant_name` varchar(150) NOT NULL,
  `business_name` varchar(150) DEFAULT NULL,
  `term_months` int(11) NOT NULL,
  `rent` decimal(12,2) NOT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `submitted_at` datetime NOT NULL DEFAULT current_timestamp(),
  `checklist_negotiation` longtext DEFAULT NULL,
  `lessor_rebuttal` text DEFAULT NULL,
  `rebuttal_at` datetime DEFAULT NULL,
  `valid_id_path` varchar(255) DEFAULT NULL,
  `sec_dti_path` varchar(255) DEFAULT NULL,
  `business_permit_path` varchar(255) DEFAULT NULL,
  `bir_cert_path` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `applications`
--

INSERT INTO `applications` (`id`, `property_id`, `lessor_id`, `lessee_id`, `tenant_name`, `business_name`, `term_months`, `rent`, `status`, `submitted_at`, `checklist_negotiation`, `lessor_rebuttal`, `rebuttal_at`, `valid_id_path`, `sec_dti_path`, `business_permit_path`, `bir_cert_path`) VALUES
(1, 1, 2, 3, 'jm', 'vv', 8, 508.00, 'approved', '2026-08-21 09:32:50', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(2, 1, 2, 3, 'jm', 'h j hh', 80, 80.00, 'approved', '2026-08-21 10:02:07', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(3, 1, 2, 7, 'glenn', 'glenn', 12, 2000.00, 'approved', '2026-08-27 01:03:44', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(4, 1, 2, NULL, 'Chris Daniel Acot', 'Kapegaduhon', 24, 7000.00, 'rejected', '2026-09-06 14:15:34', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(5, 1, 2, NULL, 'Daniel Pastor', 'Angels Hotdog', 24, 7000.00, 'approved', '2026-09-06 15:01:39', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(6, 1, 2, 12, 'Jake Peralta', 'BabyCakes', 12, 2500.00, 'approved', '2026-09-07 10:01:14', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(7, 1, 2, 17, 'Charmain Acot', 'MangJuan Store', 12, 4500.00, 'rejected', '2026-09-14 11:25:32', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(8, 1, 2, 17, 'Charmain Acot', 'Perfume Treats', 12, 1500.00, 'approved', '2026-09-14 12:03:45', '{\"security_deposit\":{\"agreed\":false,\"rebuttal_note\":\"Is it ok that I will pay 1 month rent? becuase this is beyond my cost and my budget is limited. I hope you will understand, Thankyou.\"},\"rent_escalation\":{\"agreed\":true,\"rebuttal_note\":null},\"lease_term\":{\"agreed\":true,\"rebuttal_note\":null},\"use_of_premises\":{\"agreed\":true,\"rebuttal_note\":null},\"maintenance\":{\"agreed\":true,\"rebuttal_note\":null},\"subleasing\":{\"agreed\":true,\"rebuttal_note\":null},\"termination_notice\":{\"agreed\":true,\"rebuttal_note\":null},\"insurance_compliance\":{\"agreed\":true,\"rebuttal_note\":null},\"messages\":[{\"sender\":\"lessee\",\"sender_name\":\"Charmain Acot\",\"message\":\"Yes I assure you that!\",\"sent_at\":\"2026-09-14 12:18:27\"}]}', 'Yes but how I can assure that you are really will be consistent tenant', '2026-09-14 12:09:00', NULL, NULL, NULL, NULL),
(9, 5, 2, NULL, 'Jeremy Gonzales', 'Uncle Brew (General / Other)', 36, 3000.00, 'rejected', '2026-09-28 09:51:22', '{\"security_deposit\":{\"agreed\":true,\"rebuttal_note\":null},\"rent_escalation\":{\"agreed\":true,\"rebuttal_note\":null},\"lease_term\":{\"agreed\":true,\"rebuttal_note\":null},\"use_of_premises\":{\"agreed\":true,\"rebuttal_note\":null},\"maintenance\":{\"agreed\":true,\"rebuttal_note\":null},\"subleasing\":{\"agreed\":true,\"rebuttal_note\":null},\"termination_notice\":{\"agreed\":true,\"rebuttal_note\":null},\"insurance_compliance\":{\"agreed\":true,\"rebuttal_note\":null},\"_proposal_meta\":{\"category\":\"General / Other\",\"target_move_in\":\"2026-10-30\",\"fitout_grace_requested\":true}}', NULL, NULL, NULL, NULL, NULL, NULL),
(10, 4, 2, NULL, 'Jeremy Gonzales', 'Bilbalos (Food & Beverage)', 24, 5000.00, 'pending', '2026-09-28 10:00:07', '{\"security_deposit\":{\"agreed\":true,\"rebuttal_note\":null},\"rent_escalation\":{\"agreed\":true,\"rebuttal_note\":null},\"lease_term\":{\"agreed\":true,\"rebuttal_note\":null},\"use_of_premises\":{\"agreed\":true,\"rebuttal_note\":null},\"maintenance\":{\"agreed\":true,\"rebuttal_note\":null},\"subleasing\":{\"agreed\":true,\"rebuttal_note\":null},\"termination_notice\":{\"agreed\":true,\"rebuttal_note\":null},\"insurance_compliance\":{\"agreed\":true,\"rebuttal_note\":null},\"_proposal_meta\":{\"category\":\"Food & Beverage\",\"target_move_in\":\"2026-10-31\",\"fitout_grace_requested\":true}}', NULL, NULL, NULL, NULL, NULL, NULL),
(11, 5, 2, NULL, 'Jeremy Gonzales', 'Uncle Bro (Retail & Fashion)', 36, 3000.00, 'approved', '2026-09-28 10:46:07', '{\"security_deposit\":{\"agreed\":true,\"rebuttal_note\":null},\"rent_escalation\":{\"agreed\":true,\"rebuttal_note\":null},\"lease_term\":{\"agreed\":true,\"rebuttal_note\":null},\"use_of_premises\":{\"agreed\":true,\"rebuttal_note\":null},\"maintenance\":{\"agreed\":true,\"rebuttal_note\":null},\"subleasing\":{\"agreed\":true,\"rebuttal_note\":null},\"termination_notice\":{\"agreed\":true,\"rebuttal_note\":null},\"insurance_compliance\":{\"agreed\":true,\"rebuttal_note\":null},\"_proposal_meta\":{\"category\":\"Retail & Fashion\",\"target_move_in\":\"2026-10-23\",\"fitout_grace_requested\":true}}', NULL, NULL, 'uploads/applications/11/valid_id_5d6fea2e.jpeg', 'uploads/applications/11/sec_dti_f595cc1b.jpg', 'uploads/applications/11/business_permit_e790c688.jpg', 'uploads/applications/11/bir_cert_f728a3fe.jpg'),
(12, 4, 2, 30, 'Chris Acot', 'Uncle Brew (Food & Beverage)', 36, 5000.00, 'approved', '2026-10-12 15:59:14', '{\"security_deposit\":{\"agreed\":true,\"rebuttal_note\":null},\"rent_escalation\":{\"agreed\":true,\"rebuttal_note\":null},\"rent_grace_period\":{\"agreed\":true,\"rebuttal_note\":null},\"rent_restructuring_policy\":{\"agreed\":true,\"rebuttal_note\":null},\"violations_discipline_policy\":{\"agreed\":true,\"rebuttal_note\":null},\"lease_term\":{\"agreed\":true,\"rebuttal_note\":null},\"use_of_premises\":{\"agreed\":true,\"rebuttal_note\":null},\"maintenance\":{\"agreed\":true,\"rebuttal_note\":null},\"subleasing\":{\"agreed\":true,\"rebuttal_note\":null},\"termination_notice\":{\"agreed\":true,\"rebuttal_note\":null},\"insurance_compliance\":{\"agreed\":true,\"rebuttal_note\":null},\"_proposal_meta\":{\"category\":\"Food & Beverage\",\"target_move_in\":\"2026-10-29\",\"fitout_grace_requested\":true}}', NULL, NULL, 'uploads/applications/12/valid_id_26941a09.png', 'uploads/applications/12/sec_dti_ca079bb1.jpg', 'uploads/applications/12/business_permit_2ca71dc8.jpg', 'uploads/applications/12/bir_cert_b1e10337.jpg'),
(13, 5, 2, 30, 'Chris Acot', 'Gether\'s (Food & Beverage)', 6, 3000.00, 'approved', '2026-10-19 04:47:17', '{\"security_deposit\":{\"agreed\":true,\"rebuttal_note\":null},\"rent_escalation\":{\"agreed\":true,\"rebuttal_note\":null},\"rent_grace_period\":{\"agreed\":true,\"rebuttal_note\":null},\"rent_restructuring_policy\":{\"agreed\":true,\"rebuttal_note\":null},\"violations_discipline_policy\":{\"agreed\":true,\"rebuttal_note\":null},\"lease_term\":{\"agreed\":true,\"rebuttal_note\":null},\"use_of_premises\":{\"agreed\":true,\"rebuttal_note\":null},\"maintenance\":{\"agreed\":true,\"rebuttal_note\":null},\"subleasing\":{\"agreed\":true,\"rebuttal_note\":null},\"termination_notice\":{\"agreed\":true,\"rebuttal_note\":null},\"insurance_compliance\":{\"agreed\":true,\"rebuttal_note\":null},\"_proposal_meta\":{\"category\":\"Food & Beverage\",\"target_move_in\":\"2026-11-02\",\"fitout_grace_requested\":true}}', NULL, NULL, 'uploads/applications/13/valid_id_6a627626.png', 'uploads/applications/13/sec_dti_59c55658.jpg', 'uploads/applications/13/business_permit_dc239f57.jpg', 'uploads/applications/13/bir_cert_cfc6b282.jpg'),
(14, 5, 2, 31, 'Anton', 'Gether (Food & Beverage)', 12, 3000.00, 'pending', '2026-10-19 05:12:49', '{\"security_deposit\":{\"agreed\":true,\"rebuttal_note\":null},\"rent_escalation\":{\"agreed\":true,\"rebuttal_note\":null},\"rent_grace_period\":{\"agreed\":true,\"rebuttal_note\":null},\"rent_restructuring_policy\":{\"agreed\":true,\"rebuttal_note\":null},\"violations_discipline_policy\":{\"agreed\":true,\"rebuttal_note\":null},\"lease_term\":{\"agreed\":true,\"rebuttal_note\":null},\"use_of_premises\":{\"agreed\":true,\"rebuttal_note\":null},\"maintenance\":{\"agreed\":true,\"rebuttal_note\":null},\"subleasing\":{\"agreed\":true,\"rebuttal_note\":null},\"termination_notice\":{\"agreed\":false,\"rebuttal_note\":\"\"},\"insurance_compliance\":{\"agreed\":true,\"rebuttal_note\":null},\"_proposal_meta\":{\"category\":\"Food & Beverage\",\"target_move_in\":\"2026-11-02\",\"fitout_grace_requested\":false}}', NULL, NULL, 'uploads/applications/14/valid_id_eb9b81e2.jpg', 'uploads/applications/14/sec_dti_2d627cea.jpg', 'uploads/applications/14/business_permit_b95220ab.jpg', 'uploads/applications/14/bir_cert_0b19195a.jpg'),
(15, 5, 2, 32, 'Roger Intong', 'Uncle Brew (Food & Beverage)', 24, 3000.00, 'approved', '2026-10-19 08:00:44', '{\"security_deposit\":{\"agreed\":true,\"rebuttal_note\":null},\"rent_escalation\":{\"agreed\":true,\"rebuttal_note\":null},\"rent_grace_period\":{\"agreed\":true,\"rebuttal_note\":null},\"rent_restructuring_policy\":{\"agreed\":true,\"rebuttal_note\":null},\"violations_discipline_policy\":{\"agreed\":true,\"rebuttal_note\":null},\"lease_term\":{\"agreed\":true,\"rebuttal_note\":null},\"use_of_premises\":{\"agreed\":true,\"rebuttal_note\":null},\"maintenance\":{\"agreed\":true,\"rebuttal_note\":null},\"subleasing\":{\"agreed\":true,\"rebuttal_note\":null},\"termination_notice\":{\"agreed\":true,\"rebuttal_note\":null},\"insurance_compliance\":{\"agreed\":true,\"rebuttal_note\":null},\"_proposal_meta\":{\"category\":\"Food & Beverage\",\"target_move_in\":\"2026-11-19\",\"fitout_grace_requested\":true}}', NULL, NULL, 'uploads/applications/15/valid_id_bc84cfc8.jpg', 'uploads/applications/15/sec_dti_6da6191c.jpg', 'uploads/applications/15/business_permit_95663299.jpg', 'uploads/applications/15/bir_cert_86f71bce.jpg'),
(16, 4, 2, 12, 'Jake Peralta', 'Uncle Dog Food (Food & Beverage)', 36, 5000.00, 'approved', '2026-10-20 15:26:58', '{\"security_deposit\":{\"agreed\":true,\"rebuttal_note\":null},\"rent_escalation\":{\"agreed\":true,\"rebuttal_note\":null},\"rent_grace_period\":{\"agreed\":true,\"rebuttal_note\":null},\"rent_restructuring_policy\":{\"agreed\":true,\"rebuttal_note\":null},\"violations_discipline_policy\":{\"agreed\":true,\"rebuttal_note\":null},\"lease_term\":{\"agreed\":true,\"rebuttal_note\":null},\"use_of_premises\":{\"agreed\":true,\"rebuttal_note\":null},\"maintenance\":{\"agreed\":true,\"rebuttal_note\":null},\"subleasing\":{\"agreed\":true,\"rebuttal_note\":null},\"termination_notice\":{\"agreed\":true,\"rebuttal_note\":null},\"insurance_compliance\":{\"agreed\":true,\"rebuttal_note\":null},\"_proposal_meta\":{\"category\":\"Food & Beverage\",\"target_move_in\":\"2026-11-30\",\"fitout_grace_requested\":true}}', NULL, NULL, 'uploads/applications/16/valid_id_1d60f3ec.jpg', 'uploads/applications/16/sec_dti_3af16ec0.jpg', 'uploads/applications/16/business_permit_6cb74fc0.jpg', 'uploads/applications/16/bir_cert_7375d510.jpg'),
(17, 7, 34, NULL, 'Barbie Demor', 'Kylie\'s Cosmetic Retail', 12, 100000.00, 'approved', '2026-10-20 16:48:02', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(18, 7, 34, NULL, 'Melova Heim', 'Colourette Cosmetics', 12, 100000.00, 'pending', '2026-10-20 16:52:44', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(19, 7, 34, NULL, 'Melova Heim', 'Colourette Cosmetics', 12, 100000.00, 'rejected', '2026-10-20 16:53:32', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(20, 7, 34, NULL, 'Melova Heim', 'Colourette Cosmetics', 12, 100000.00, 'rejected', '2026-10-20 16:53:37', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(21, 7, 34, NULL, 'Hemish Hems', 'Cloud Center', 12, 150000.00, 'pending', '2026-10-20 17:00:33', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(22, 7, 34, NULL, 'Anton Corrales', 'Minev Corp', 12, 150000.00, 'approved', '2026-10-20 17:29:56', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(23, 8, 34, NULL, 'Alice Hitch', 'Nerva VA', 12, 50000.00, 'approved', '2026-10-20 18:55:54', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(24, 9, 34, NULL, 'Merida Clan', 'Uma VA', 5, 30000.00, 'pending', '2026-10-20 20:09:24', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(25, 8, 34, NULL, 'Hemish Hems', 'Potato', 4, 40000.00, 'approved', '2026-10-21 00:36:42', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(27, 11, 35, 12, 'Jake Peralta', 'Secret (Food & Beverage)', 12, 500000.00, 'approved', '2026-10-21 06:13:21', '{\"security_deposit\":{\"agreed\":true,\"rebuttal_note\":null},\"rent_escalation\":{\"agreed\":true,\"rebuttal_note\":null},\"rent_grace_period\":{\"agreed\":true,\"rebuttal_note\":null},\"rent_restructuring_policy\":{\"agreed\":true,\"rebuttal_note\":null},\"violations_discipline_policy\":{\"agreed\":true,\"rebuttal_note\":null},\"lease_term\":{\"agreed\":true,\"rebuttal_note\":null},\"use_of_premises\":{\"agreed\":true,\"rebuttal_note\":null},\"maintenance\":{\"agreed\":true,\"rebuttal_note\":null},\"subleasing\":{\"agreed\":true,\"rebuttal_note\":null},\"termination_notice\":{\"agreed\":true,\"rebuttal_note\":null},\"insurance_compliance\":{\"agreed\":true,\"rebuttal_note\":null},\"_proposal_meta\":{\"category\":\"Food & Beverage\",\"target_move_in\":\"2026-11-04\",\"fitout_grace_requested\":true}}', NULL, NULL, 'uploads/applications/27/valid_id_99a0a36f.jpg', 'uploads/applications/27/sec_dti_706302ff.jpg', 'uploads/applications/27/business_permit_0854a4c4.jpg', 'uploads/applications/27/bir_cert_d3e4931d.jpg'),
(28, 9, 34, NULL, 'Merida Clan', 'Uma VA', 12, 70000.00, 'approved', '2026-10-21 19:02:29', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(29, 8, 34, 32, 'Roger Intong', 'Mrs.  DiWHY (Food & Beverage)', 24, 200000.00, 'approved', '2026-10-22 00:19:31', '{\"security_deposit\":{\"agreed\":true,\"rebuttal_note\":null},\"rent_escalation\":{\"agreed\":true,\"rebuttal_note\":null},\"rent_grace_period\":{\"agreed\":true,\"rebuttal_note\":null},\"rent_restructuring_policy\":{\"agreed\":true,\"rebuttal_note\":null},\"violations_discipline_policy\":{\"agreed\":true,\"rebuttal_note\":null},\"lease_term\":{\"agreed\":true,\"rebuttal_note\":null},\"use_of_premises\":{\"agreed\":true,\"rebuttal_note\":null},\"maintenance\":{\"agreed\":true,\"rebuttal_note\":null},\"subleasing\":{\"agreed\":true,\"rebuttal_note\":null},\"termination_notice\":{\"agreed\":true,\"rebuttal_note\":null},\"insurance_compliance\":{\"agreed\":true,\"rebuttal_note\":null},\"_proposal_meta\":{\"category\":\"Food & Beverage\",\"target_move_in\":\"2026-11-23\",\"fitout_grace_requested\":true}}', NULL, NULL, 'uploads/applications/29/valid_id_3fa152cd.jpg', 'uploads/applications/29/sec_dti_693f860a.jpg', 'uploads/applications/29/business_permit_feb68a17.jpg', 'uploads/applications/29/bir_cert_42bbf25e.jpg');

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL,
  `actor_id` int(11) DEFAULT NULL,
  `action` varchar(150) NOT NULL,
  `details` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `actor_id`, `action`, `details`, `created_at`) VALUES
(1, 1, 'login', '', '2026-08-21 02:23:42'),
(2, 2, 'register', 'New lessor: Secret', '2026-08-21 02:32:16'),
(3, 1, 'login', '', '2026-08-21 02:32:42'),
(4, 1, 'Approved lessor verification', 'Lessor #2', '2026-08-21 02:33:07'),
(5, 2, 'login', '', '2026-08-21 02:33:40'),
(6, 1, 'login', '', '2026-08-21 02:49:24'),
(7, 2, 'login', '', '2026-08-21 02:50:32'),
(8, 2, 'Submitted property for verification', 'pwestora', '2026-08-21 02:59:19'),
(9, 1, 'login', '', '2026-08-21 02:59:24'),
(10, 1, 'Approved property verification', 'Property #1', '2026-08-21 03:00:01'),
(11, 2, 'login', '', '2026-08-21 03:01:00'),
(12, 1, 'login', '', '2026-08-21 03:05:56'),
(13, 1, 'login', '', '2026-08-21 06:08:33'),
(14, 1, 'login', '', '2026-08-21 06:23:59'),
(15, 2, 'login', '', '2026-08-21 06:24:20'),
(16, 2, 'login', '', '2026-08-21 06:25:29'),
(17, 1, 'login', '', '2026-08-21 06:26:23'),
(18, 2, 'login', '', '2026-08-21 06:29:58'),
(19, 1, 'login', '', '2026-08-21 07:15:23'),
(20, 2, 'login', '', '2026-08-21 07:15:40'),
(21, 2, 'login', '', '2026-08-21 07:20:43'),
(22, 1, 'login', '', '2026-08-21 07:29:06'),
(23, 2, 'login', '', '2026-08-21 07:34:31'),
(24, 1, 'login', '', '2026-08-21 07:38:17'),
(25, 1, 'Suspended user', 'jmantinola54@gmail.com', '2026-08-21 07:38:43'),
(26, 2, 'login', '', '2026-08-21 07:39:04'),
(27, 2, 'login', '', '2026-08-21 07:39:33'),
(28, 1, 'login', '', '2026-08-21 07:39:48'),
(29, 1, 'Active user', 'jmantinola54@gmail.com', '2026-08-21 07:39:51'),
(30, 2, 'login', '', '2026-08-21 07:40:03'),
(31, 1, 'login', '', '2026-08-21 07:42:14'),
(32, 1, 'Sent lessor notice', 'Compliance Notice to 1 lessor(s)', '2026-08-21 07:42:35'),
(33, 2, 'login', '', '2026-08-21 07:44:04'),
(34, 2, 'login', '', '2026-08-21 07:47:49'),
(35, 1, 'login', '', '2026-08-21 07:49:02'),
(36, 1, 'Sent lessor notice', 'Policy Update to 1 lessor(s)', '2026-08-21 07:49:31'),
(37, 2, 'login', '', '2026-08-21 07:49:58'),
(38, 1, 'login', '', '2026-08-21 08:04:11'),
(39, 3, 'Lessee registered via app', 'jm@gmail.com', '2026-08-21 09:27:25'),
(40, 2, 'login', '', '2026-08-21 09:33:10'),
(41, 2, 'Approved application', 'jm', '2026-08-21 09:33:24'),
(42, 2, 'Approved application', 'jm', '2026-08-21 10:02:16'),
(43, 2, 'Advanced maintenance ticket', 'hhh → Under Review', '2026-08-21 10:18:08'),
(44, 2, 'Advanced maintenance ticket', 'hhh → Approved', '2026-08-21 10:18:48'),
(45, 2, 'Advanced maintenance ticket', 'hhh → Contractor Assigned', '2026-08-21 10:44:30'),
(46, 2, 'Advanced maintenance ticket', 'hhh → Scheduled', '2026-08-21 10:44:33'),
(47, 2, 'Advanced maintenance ticket', 'hhh → In Progress', '2026-08-21 10:44:40'),
(48, 2, 'Advanced maintenance ticket', 'hhh → Inspection', '2026-08-21 10:44:45'),
(49, 4, 'register', 'New lessor: gwapo ako', '2026-08-21 14:16:11'),
(50, 4, 'login', '', '2026-08-21 14:16:23'),
(51, 5, 'Lessee registered via app', 'glenn@gmail.com', '2026-08-21 22:11:11'),
(52, 2, 'Issued violation', 'Late Operating Hours — strike 1', '2026-08-22 04:39:09'),
(53, 2, 'Logged rent payment', '₱1000 via Cash', '2026-08-22 04:54:21'),
(54, 2, 'Logged rent payment', '₱1000 via Cash', '2026-08-22 04:54:44'),
(55, 2, 'Issued violation', 'Late Operating Hours — strike 2', '2026-08-22 05:06:46'),
(56, 4, 'login', '', '2026-08-22 06:11:34'),
(57, 1, 'login', '', '2026-08-22 06:14:13'),
(58, 1, 'Approved lessor verification', 'Lessor #4', '2026-08-22 06:14:27'),
(59, 1, 'Suspended user', 'jmantinola54@gmail.com', '2026-08-22 06:14:35'),
(60, 1, 'Active user', 'jmantinola54@gmail.com', '2026-08-22 06:14:37'),
(61, 1, 'login', '', '2026-08-23 06:52:28'),
(62, 2, 'login (OTP verified)', '', '2026-08-23 07:04:55'),
(63, 6, 'register', 'New lessor: Sample', '2026-08-23 07:20:22'),
(64, 1, 'login (OTP verified)', '', '2026-08-23 07:52:00'),
(65, 1, 'Approved lessor verification', 'Lessor #6', '2026-08-23 07:52:15'),
(66, 6, 'login (OTP verified)', '', '2026-08-23 07:55:09'),
(67, 2, 'login (OTP verified)', '', '2026-08-23 07:59:41'),
(68, 1, 'login (OTP verified)', '', '2026-08-23 08:50:49'),
(69, 2, 'login (OTP verified)', '', '2026-08-23 08:58:02'),
(70, 4, 'login (OTP verified)', '', '2026-08-23 13:57:24'),
(71, 4, 'login (OTP verified)', '', '2026-08-23 14:04:18'),
(72, 2, 'login', '', '2026-08-25 04:43:31'),
(73, 1, 'login', '', '2026-08-25 07:42:56'),
(74, 1, 'login', '', '2026-08-25 07:54:33'),
(75, 1, 'login', '', '2026-08-25 07:59:44'),
(76, 2, 'login', '', '2026-08-25 08:00:00'),
(77, 1, 'login', '', '2026-08-25 08:06:35'),
(78, 2, 'login', '', '2026-08-25 08:11:18'),
(79, 2, 'Advanced maintenance ticket', 'hhh → Completed', '2026-08-25 08:11:50'),
(80, 4, 'login', '', '2026-08-25 11:55:47'),
(81, 2, 'login', '', '2026-08-25 12:32:12'),
(82, 4, 'login', '', '2026-08-25 12:35:19'),
(83, 4, 'login', '', '2026-08-26 00:14:09'),
(84, 1, 'login', '', '2026-08-27 00:34:01'),
(85, 2, 'login', '', '2026-08-27 00:34:27'),
(86, 7, 'Lessee registered via app', 'witness.22.15@gmail.com', '2026-08-27 01:01:54'),
(87, 2, 'login', '', '2026-08-27 01:04:15'),
(88, 2, 'Approved application', 'glenn', '2026-08-27 01:04:28'),
(89, 2, 'login', '', '2026-08-27 01:22:10'),
(90, 8, 'Lessee registered via app', 'paolopastorcot305@gmail.com', '2026-08-27 01:36:11'),
(91, 2, 'login', '', '2026-08-27 09:49:01'),
(92, 2, 'login', '', '2026-08-29 01:15:42'),
(93, 4, 'login', '', '2026-09-03 03:51:11'),
(94, 4, 'login', '', '2026-09-03 17:58:22'),
(95, NULL, 'Lessee registered via app', 'chrisdanielacot5226@gmail.com', '2026-09-06 14:14:42'),
(96, NULL, 'Lessee registered via app', 'chrisdaniel305@gmail.com', '2026-09-06 14:56:32'),
(97, 1, 'login', '', '2026-09-06 14:58:28'),
(98, 2, 'login', '', '2026-09-06 15:00:32'),
(99, 2, 'Approved application', 'Daniel Pastor', '2026-09-06 15:01:51'),
(100, 11, 'Lessee registered via app', 'it.prolifemanagement@gmail.com', '2026-09-07 05:34:38'),
(101, 2, 'login', '', '2026-09-07 06:09:36'),
(102, 4, 'login', '', '2026-09-07 06:10:09'),
(103, 12, 'Lessee registered via app', 'jakeperalta@gmail.com', '2026-09-07 10:00:21'),
(104, 2, 'login', '', '2026-09-07 10:02:51'),
(105, 2, 'Approved application', 'Jake Peralta', '2026-09-07 10:03:01'),
(106, 13, 'Lessee registered via app', 'johnsazon@gmail.com', '2026-09-07 13:48:40'),
(107, 2, 'login', '', '2026-09-08 00:45:50'),
(108, 1, 'login', '', '2026-09-08 00:56:29'),
(109, 14, 'Lessee registered via app', 'holt@gmail.com', '2026-09-12 04:33:22'),
(110, 1, 'login', '', '2026-09-12 04:34:22'),
(111, 2, 'login', '', '2026-09-12 04:37:28'),
(112, 2, 'Rejected application', 'Chris Daniel Acot', '2026-09-12 04:37:49'),
(113, NULL, 'register', 'New lessor: KapeNagus2', '2026-09-12 04:46:52'),
(114, NULL, 'register', 'New lessor: KapeNagus2', '2026-09-12 04:47:34'),
(115, NULL, 'login (OTP verified)', '', '2026-09-12 04:48:02'),
(116, 1, 'login', '', '2026-09-12 04:48:15'),
(117, 1, 'Rejected lessor verification', 'Lessor #15', '2026-09-12 04:48:38'),
(118, 1, 'Approved lessor verification', 'Lessor #16', '2026-09-12 04:48:44'),
(119, NULL, 'login', '', '2026-09-12 04:49:00'),
(120, NULL, 'Submitted property for verification', 'KapeNagus2', '2026-09-12 04:51:13'),
(121, 1, 'Approved property verification', 'Property #2', '2026-09-12 04:51:51'),
(122, 1, 'login', '', '2026-09-13 03:17:15'),
(123, 1, 'Suspended user', 'paolopastoracot305@gmail.com', '2026-09-13 03:17:22'),
(124, 17, 'Lessee registered via app', 'charmaine@gmail.com', '2026-09-14 11:12:40'),
(125, 2, 'login', '', '2026-09-14 11:19:22'),
(126, 1, 'login', '', '2026-09-14 11:20:00'),
(127, 2, 'Rejected application', 'Charmain Acot', '2026-09-14 12:02:18'),
(128, 2, 'Sent counter-proposal/rebuttal', 'Charmain Acot', '2026-09-14 12:09:00'),
(129, 2, 'Approved application', 'Charmain Acot', '2026-09-14 12:32:28'),
(130, 2, 'Updated property details', 'Atrium Commercial Plaza', '2026-09-14 12:52:31'),
(131, 18, 'Lessee registered via app', 'james@gmail.com', '2026-09-14 13:05:28'),
(132, 2, 'login', '', '2026-09-15 06:10:05'),
(133, 2, 'login', '', '2026-09-15 07:46:19'),
(134, 2, 'login', '', '2026-09-15 08:51:57'),
(135, 2, 'login', '', '2026-09-19 14:29:03'),
(136, 2, 'Advanced maintenance ticket', '[Electrical] Breaker tripped → Under Review', '2026-09-19 14:29:24'),
(137, 2, 'Advanced maintenance ticket', '[Electrical] Breaker tripped → Approved', '2026-09-19 14:29:32'),
(138, 2, 'login', '', '2026-09-19 14:43:51'),
(139, 2, 'login', '', '2026-09-19 15:55:55'),
(140, 19, 'Lessee registered via app', 'glennsazon@gmail.com', '2026-09-21 15:24:54'),
(141, 2, 'login', '', '2026-09-22 11:00:20'),
(142, NULL, 'Lessee registered via app', 'jeremy@gmail.com', '2026-09-22 15:21:50'),
(143, 23, 'Lessee registered via app', 'garry@gmail.com', '2026-09-22 15:34:44'),
(144, 1, 'login', '', '2026-09-22 15:41:49'),
(145, 2, 'login', '', '2026-09-22 15:43:46'),
(146, 2, 'login', '', '2026-09-23 13:16:18'),
(147, 2, 'Submitted property for verification', 'MS City Bacolod', '2026-09-23 13:25:34'),
(148, 1, 'login', '', '2026-09-23 13:25:55'),
(149, 1, 'Approved property verification', 'Property #4', '2026-09-23 13:26:08'),
(150, 2, 'Submitted property for verification', 'The J&R Commercial Center', '2026-09-23 13:35:54'),
(151, 1, 'Approved property verification', 'Property #5', '2026-09-23 13:36:08'),
(152, 1, 'login', '', '2026-09-25 08:23:25'),
(153, 4, 'login', '', '2026-09-25 10:16:31'),
(154, 24, 'Lessee registered via app', 'gwapoako@gmail.com', '2026-09-28 05:04:12'),
(155, 2, 'login', '', '2026-09-28 09:13:29'),
(156, 2, 'Rejected application', 'Jeremy Gonzales', '2026-09-28 10:12:17'),
(157, 2, 'Approved application', 'Jeremy Gonzales', '2026-09-28 10:48:16'),
(158, 2, 'login', '', '2026-09-29 00:48:19'),
(159, 25, 'Lessee registered via app', 'jason@gmail.com', '2026-09-29 00:56:06'),
(160, 4, 'login', '', '2026-09-29 01:08:11'),
(161, 2, 'login', '', '2026-09-29 01:08:20'),
(162, 2, 'login', '', '2026-09-29 01:11:05'),
(163, NULL, 'Lessee registered via app', 'george@gmail.com', '2026-10-05 01:24:23'),
(164, 2, 'login', '', '2026-10-07 03:07:39'),
(165, 3, 'Tenant Submitted Payment', '₱2500 for Monthly Rent (Ref: TEST-123456)', '2026-10-07 03:40:14'),
(166, 2, 'Confirmed Tenant Payment', '₱2,500.00 from jm', '2026-10-07 03:45:22'),
(167, 3, 'Tenant Submitted Payment', '₱2500 for Monthly Rent (Ref: JP-REF-998877)', '2026-10-07 04:01:24'),
(168, 2, 'login', '', '2026-10-07 04:05:03'),
(169, 2, 'login', '', '2026-10-07 05:03:10'),
(170, 2, 'Declined Tenant Payment', '₱2,500.00 from jm (Declined by lessor)', '2026-10-07 05:03:17'),
(171, 2, 'login', '', '2026-10-07 05:15:23'),
(172, 12, 'Tenant Submitted Payment', '₱2500 for Monthly Rent (Ref: 2301876)', '2026-10-07 05:16:53'),
(173, 2, 'Confirmed Tenant Payment', '₱2,500.00 from Jake Peralta', '2026-10-07 05:17:06'),
(174, 2, 'login', '', '2026-10-07 14:41:47'),
(175, 2, 'login', '', '2026-10-07 14:46:59'),
(176, NULL, 'Lessee registered via app', 'amy@gmail.com', '2026-10-07 14:49:48'),
(177, NULL, 'Lessee registered via app', 'jose@gmail.com', '2026-10-12 14:05:37'),
(178, 30, 'Lessee registered & phone verified via app', 'chrisdanielacot305@gmai.com', '2026-10-12 14:45:54'),
(179, 2, 'login', '', '2026-10-12 15:48:04'),
(180, NULL, 'Tenant Submitted Payment', '₱2300 for Monthly Rent (Ref: 1029481920)', '2026-10-12 15:49:00'),
(181, NULL, 'Updated profile details', 'Jeremy Gonzales (jeremy@gmail.com, 09266931720)', '2026-10-12 15:49:32'),
(182, 2, 'Confirmed Tenant Payment', '₱2,300.00 from Jeremy Gonzales', '2026-10-12 15:49:54'),
(183, 30, 'Updated profile details', 'Chris Acot (chrisdanielacot305@gmail.com, 09945884126)', '2026-10-12 15:58:14'),
(184, 2, 'Approved application', 'Chris Acot', '2026-10-12 15:59:55'),
(185, 30, 'Tenant Submitted Payment', '₱2800 for Monthly Rent (Ref: 911691315)', '2026-10-12 16:00:55'),
(186, 2, 'Confirmed Tenant Payment', '₱2,800.00 from Chris Acot', '2026-10-12 16:01:04'),
(187, 12, 'Updated profile details', 'Jake Peralta (jakeperalta@gmail.com, 09123141146)', '2026-10-12 16:32:00'),
(188, 12, 'Updated profile details', 'Jake Peralta (jakeperalta@gmail.com, 09123141146)', '2026-10-12 16:34:38'),
(189, 2, 'login', '', '2026-10-13 05:14:30'),
(190, 30, 'Tenant Submitted Payment', '₱500 for Monthly Rent (Ref: 0193810019)', '2026-10-13 05:18:06'),
(191, 2, 'Confirmed Tenant Payment', '₱500.00 from Chris Acot', '2026-10-13 05:18:16'),
(192, 2, 'Confirmed Tenant Payment', '₱500.00 from Chris Acot', '2026-10-13 05:18:18'),
(193, 2, 'Confirmed Tenant Payment', '₱500.00 from Chris Acot', '2026-10-13 05:18:21'),
(194, 2, 'login', '', '2026-10-13 15:30:22'),
(195, 2, 'Advanced maintenance ticket', '[Electrical] Breaker tripped → Contractor Assigned', '2026-10-13 15:30:33'),
(196, 2, 'Advanced maintenance ticket', '[Electrical] Breaker tripped → Scheduled', '2026-10-13 15:30:38'),
(197, 2, 'Advanced maintenance ticket', '[Electrical] Breaker tripped → In Progress', '2026-10-13 15:30:43'),
(198, 2, 'Advanced maintenance ticket', '[Electrical] Breaker tripped → Inspection', '2026-10-13 15:30:50'),
(199, 2, 'Advanced maintenance ticket', '[Electrical] Breaker tripped → Completed', '2026-10-13 15:30:54'),
(200, 2, 'login', '', '2026-10-14 15:33:29'),
(201, 2, 'login', '', '2026-10-14 17:18:39'),
(202, 2, 'Approved installment plan', '2 payments', '2026-10-14 17:19:02'),
(203, 2, 'login', '', '2026-10-15 01:31:49'),
(204, 2, 'login', '', '2026-10-19 04:55:20'),
(205, 2, 'Issued violation', 'Safety Violation — strike 1', '2026-10-19 04:57:19'),
(206, 31, 'Lessee registered & phone verified via app', 'anton@gmail.com', '2026-10-19 05:09:01'),
(207, 1, 'login', '', '2026-10-19 05:24:18'),
(208, 2, 'login', '', '2026-10-19 07:47:00'),
(209, 32, 'Lessee registered & phone verified via app', 'roger@gmail.com', '2026-10-19 07:56:19'),
(210, 2, 'Approved application', 'Roger Intong', '2026-10-19 08:01:23'),
(211, NULL, 'register', 'New lessor: Ayala Malls', '2026-10-19 08:09:03'),
(212, NULL, 'login (OTP verified)', '', '2026-10-19 08:09:27'),
(213, 1, 'login', '', '2026-10-19 08:10:06'),
(214, 1, 'Approved lessor verification', 'Lessor #33', '2026-10-19 08:10:17'),
(215, NULL, 'login', '', '2026-10-19 08:10:48'),
(216, NULL, 'Submitted property for verification', 'Ayala', '2026-10-19 08:11:52'),
(217, 1, 'Approved property verification', 'Property #6', '2026-10-19 08:12:00'),
(218, 2, 'login', '', '2026-10-19 14:36:27'),
(219, 2, 'Approved application', 'Chris Acot', '2026-10-19 14:36:46'),
(220, 1, 'login', '', '2026-10-20 14:38:19'),
(221, 2, 'login', '', '2026-10-20 14:38:50'),
(222, 1, 'login', '', '2026-10-20 14:50:21'),
(223, 2, 'login', '', '2026-10-20 15:27:12'),
(224, 2, 'Approved application', 'Jake Peralta', '2026-10-20 15:27:23'),
(225, 34, 'register', 'New lessor: Mattel Cosmetic', '2026-10-20 15:56:43'),
(226, 34, 'login (OTP verified)', '', '2026-10-20 15:57:35'),
(227, 1, 'login', '', '2026-10-20 16:14:54'),
(228, 1, 'Approved lessor verification', 'Lessor #34', '2026-10-20 16:15:31'),
(229, 34, 'login', '', '2026-10-20 16:15:58'),
(230, 34, 'Submitted property for verification', 'Mattel\'s Buidling', '2026-10-20 16:30:39'),
(231, 1, 'Approved property verification', 'Property #7', '2026-10-20 16:31:19'),
(232, 34, 'Rejected application', 'Melova Heim', '2026-10-20 16:59:49'),
(233, 34, 'Rejected application', 'Melova Heim', '2026-10-20 17:20:32'),
(234, 34, 'login', '', '2026-10-20 17:23:23'),
(235, 34, 'login', '', '2026-10-20 17:36:24'),
(236, 34, 'Approved application', 'Barbie Demor', '2026-10-20 17:36:43'),
(237, 34, 'Submitted property for verification', 'BeeHive', '2026-10-20 17:43:22'),
(238, 1, 'login', '', '2026-10-20 17:46:09'),
(239, 1, 'Approved property verification', 'Property #8', '2026-10-20 17:46:19'),
(240, 34, 'Submitted property for verification', 'NotScoobs', '2026-10-20 17:54:12'),
(241, 34, 'login', '', '2026-10-20 17:54:25'),
(242, 34, 'Updated property details', 'NotScoobs', '2026-10-20 17:54:41'),
(243, 1, 'Approved property verification', 'Property #9', '2026-10-20 17:56:23'),
(244, 34, 'Approved application', 'Alice Hitch', '2026-10-20 18:56:08'),
(245, 34, 'Sent notification', 'General Notice to 1 tenant(s)', '2026-10-20 20:04:09'),
(246, 34, 'Logged rent payment', '₱20000 via Cash for Alice Hitch', '2026-10-20 20:07:41'),
(247, 34, 'login', '', '2026-10-21 00:29:39'),
(248, 34, 'Advanced maintenance ticket', 'dusty cabinets → Under Review', '2026-10-21 00:39:48'),
(249, 34, 'Advanced maintenance ticket', 'dusty cabinets → Approved', '2026-10-21 00:40:00'),
(250, 2, 'login', '', '2026-10-21 00:46:20'),
(251, 34, 'Advanced maintenance ticket', 'dusty cabinets → Contractor Assigned', '2026-10-21 00:56:48'),
(252, 34, 'Advanced maintenance ticket', 'dusty cabinets → Scheduled', '2026-10-21 01:03:01'),
(253, 2, 'login', '', '2026-10-21 01:03:11'),
(254, 34, 'Advanced maintenance ticket', 'Flickering Lights → Under Review', '2026-10-21 01:05:15'),
(255, 34, 'Advanced maintenance ticket', 'Flickering Lights → Approved', '2026-10-21 01:05:17'),
(256, 34, 'login', '', '2026-10-21 02:35:47'),
(257, 2, 'login', '', '2026-10-21 03:15:32'),
(258, 34, 'login', '', '2026-10-21 04:26:21'),
(259, 34, 'Advanced maintenance ticket', 'Flickering Lights → Contractor Assigned', '2026-10-21 04:32:51'),
(260, 34, 'Advanced maintenance ticket', 'dusty cabinets → In Progress', '2026-10-21 04:33:00'),
(261, 34, 'Advanced maintenance ticket', 'Flickering Lights → Scheduled', '2026-10-21 04:33:10'),
(262, 34, 'login', '', '2026-10-21 05:00:57'),
(263, 34, 'Emergency deposit release', '12 — ₱200000.00', '2026-10-21 05:05:13'),
(264, 34, 'login', '', '2026-10-21 05:07:23'),
(265, 2, 'login', '', '2026-10-21 05:10:30'),
(266, 2, 'Emergency deposit release', '5 — ₱500000.00', '2026-10-21 05:11:44'),
(267, 2, 'Logged rent payment', '₱5000 via Cash for Jake Peralta', '2026-10-21 05:16:44'),
(268, 2, 'Advanced maintenance ticket', '[Lighting] Bulb replacement → Under Review', '2026-10-21 05:24:18'),
(269, 2, 'Advanced maintenance ticket', '[Lighting] Bulb replacement → Approved', '2026-10-21 05:25:42'),
(270, 1, 'login', '', '2026-10-21 05:41:33'),
(271, 2, 'login', '', '2026-10-21 05:43:27'),
(272, 2, 'login', '', '2026-10-21 05:45:25'),
(273, 1, 'login', '', '2026-10-21 05:45:35'),
(274, 1, 'Suspended user', 'aireysanni@gmail.com', '2026-10-21 05:50:27'),
(275, 1, 'Active user', 'aireysanni@gmail.com', '2026-10-21 05:50:29'),
(276, 1, 'Suspended user', 'aireysanni@gmail.com', '2026-10-21 05:50:29'),
(277, 1, 'Active user', 'aireysanni@gmail.com', '2026-10-21 05:50:33'),
(278, 1, 'Suspended user', 'roger@gmail.com', '2026-10-21 05:50:35'),
(279, 1, 'Active user', 'roger@gmail.com', '2026-10-21 05:50:35'),
(280, 1, 'Suspended user', 'roger@gmail.com', '2026-10-21 05:50:36'),
(281, 1, 'Active user', 'roger@gmail.com', '2026-10-21 05:50:37'),
(282, 1, 'Suspended user', 'roger@gmail.com', '2026-10-21 05:50:37'),
(283, 1, 'Active user', 'roger@gmail.com', '2026-10-21 05:50:38'),
(284, 1, 'Suspended user', 'roger@gmail.com', '2026-10-21 05:50:39'),
(285, 1, 'Active user', 'roger@gmail.com', '2026-10-21 05:50:39'),
(286, 35, 'register', 'New lessor: Gether\'s Business', '2026-10-21 05:54:16'),
(287, 35, 'login (OTP verified)', '', '2026-10-21 05:56:22'),
(288, 1, 'Approved lessor verification', 'Lessor #35', '2026-10-21 05:56:43'),
(289, 35, 'login', '', '2026-10-21 05:57:34'),
(290, 35, 'Submitted property for verification', 'Testing Building 1', '2026-10-21 05:58:07'),
(291, 1, 'Approved property verification', 'Property #10', '2026-10-21 05:58:50'),
(292, 1, 'Suspended user', 'g.medel@usls.edu.ph', '2026-10-21 06:00:20'),
(293, 1, 'Active user', 'g.medel@usls.edu.ph', '2026-10-21 06:00:20'),
(294, 1, 'Sent lessor notice', 'Policy Update to 1 lessor(s)', '2026-10-21 06:01:04'),
(295, 35, 'Approved application', 'Jake Peralta', '2026-10-21 06:06:35'),
(296, 12, 'Tenant Submitted Payment', '₱140000 for Monthly Rent (Ref: 23716371)', '2026-10-21 06:07:37'),
(297, 35, 'Confirmed Tenant Payment', '₱140,000.00 from Jake Peralta', '2026-10-21 06:08:11'),
(298, 12, 'Tenant Submitted Payment', '₱10000 for Monthly Rent (Ref: 174737271)', '2026-10-21 06:09:14'),
(299, 35, 'Confirmed Tenant Payment', '₱10,000.00 from Jake Peralta', '2026-10-21 06:09:33'),
(300, 35, 'Submitted property for verification', 'Building 2', '2026-10-21 06:11:36'),
(301, 1, 'Approved property verification', 'Property #11', '2026-10-21 06:11:44'),
(302, 35, 'Approved application', 'Jake Peralta', '2026-10-21 06:13:37'),
(303, 12, 'Tenant Submitted Payment', '₱100000 for Monthly Rent (Ref: 1454)', '2026-10-21 06:14:24'),
(304, 4, 'login', '', '2026-10-21 12:57:54'),
(305, 2, 'login', '', '2026-10-21 15:44:31'),
(306, 2, 'login', '', '2026-10-21 18:13:33'),
(307, 30, 'login', '', '2026-10-21 18:15:10'),
(308, 34, 'login', '', '2026-10-21 18:57:53'),
(309, 34, 'Approved application', 'Merida Clan', '2026-10-21 19:03:24'),
(310, 34, 'Approved application', 'Anton Corrales', '2026-10-21 19:03:35'),
(311, 34, 'Approved application', 'Hemish Hems', '2026-10-21 19:03:42'),
(312, 34, 'login', '', '2026-10-21 19:11:41'),
(313, 34, 'Approved installment plan', '2 payments', '2026-10-21 21:08:45'),
(314, 34, 'Approved installment plan', '3 payments', '2026-10-21 21:09:30'),
(315, 34, 'Logged rent payment', '₱10000 via Cash for Merida Clan', '2026-10-21 22:17:41'),
(316, 34, 'Logged rent payment', '₱5000 via Bank Transfer / InstaPay for Barbie Demor', '2026-10-21 22:21:41'),
(317, 34, 'Logged rent payment', '₱1000 via Cash for Barbie Demor', '2026-10-21 22:21:55'),
(318, 34, 'Logged rent payment', '₱5000 via cash for Merida Clan', '2026-10-21 22:26:38'),
(319, 34, 'Logged rent payment', '₱2000 via Palawan for Alice Hitch', '2026-10-21 22:32:09'),
(320, 34, 'login', '', '2026-10-21 22:53:43'),
(321, 34, 'Logged pending rent payment', '₱2500 via gcash for Hemish Hems', '2026-10-21 22:54:21'),
(322, 34, 'Advanced maintenance ticket', 'Flickering Lights → In Progress', '2026-10-21 23:21:31'),
(323, 4, 'login', '', '2026-10-21 23:27:57'),
(324, 1, 'login', '', '2026-10-21 23:30:20'),
(325, 4, 'login', '', '2026-10-21 23:30:40'),
(326, 4, 'Submitted property for verification', 'Glenn\'s Team', '2026-10-21 23:32:00'),
(327, 34, 'Advanced maintenance ticket', 'Water Licking → Under Review', '2026-10-21 23:33:00'),
(328, 34, 'Advanced maintenance ticket', 'Water Licking → Approved', '2026-10-21 23:34:57'),
(329, 34, 'Advanced maintenance ticket', 'Water Licking → Contractor Assigned', '2026-10-21 23:35:04'),
(330, 34, 'Advanced maintenance ticket', 'leakage from the ventilation → Under Review', '2026-10-21 23:35:19'),
(331, 34, 'Advanced maintenance ticket', 'Wall cracks appear → Under Review', '2026-10-21 23:38:27'),
(332, 34, 'Advanced maintenance ticket', 'leakage from the ventilation → Approved', '2026-10-21 23:52:45'),
(333, 34, 'Advanced maintenance ticket', 'leakage from the ventilation → Contractor Assigned', '2026-10-21 23:53:02'),
(334, 34, 'Issued violation', 'Noise Complaint — strike 1', '2026-10-22 00:03:33'),
(335, 34, 'Approved application', 'Roger Intong', '2026-10-22 00:20:41'),
(336, 32, 'Tenant Submitted Payment', '₱10000 for Monthly Rent (Ref: 23019931)', '2026-10-22 00:23:24'),
(337, 34, 'Advanced maintenance ticket', '[Air Conditioning - Not cooling] Waay mn ni ga andar nga aircon!!! → Under Review', '2026-10-22 00:24:07'),
(338, 34, 'Advanced maintenance ticket', '[Air Conditioning - Not cooling] Waay mn ni ga andar nga aircon!!! → Approved', '2026-10-22 00:24:09'),
(339, 34, 'Advanced maintenance ticket', '[Air Conditioning - Not cooling] Waay mn ni ga andar nga aircon!!! → Contractor Assigned', '2026-10-22 00:24:23'),
(340, 34, 'Issued violation', 'Noise Complaint — strike 1', '2026-10-22 00:28:03'),
(341, 34, 'Advanced maintenance ticket', 'Flickering Lights → Inspection', '2026-10-22 00:45:13'),
(342, 34, 'Advanced maintenance ticket', 'Flickering Lights → Completed', '2026-10-22 00:45:14'),
(343, 30, 'Tenant Submitted Payment', '₱2000 for Monthly Rent (Ref: 213213213)', '2026-10-22 00:47:11'),
(344, 34, 'login', '', '2026-10-22 02:50:29'),
(345, 1, 'login', '', '2026-10-22 03:01:40'),
(346, 1, 'Sent lessor notice', 'Compliance Notice to 1 lessor(s)', '2026-10-22 03:17:51'),
(347, 1, 'Sent lessor notice', 'Verification Reminder to 1 lessor(s)', '2026-10-22 03:22:34'),
(348, 34, 'Updated property details', 'Mattel\'s Buidling', '2026-10-22 03:27:50'),
(349, 1, 'login', '', '2026-10-22 04:38:37'),
(350, 1, 'Approved property verification', 'Property #12', '2026-10-22 05:29:01'),
(351, 1, 'Suspended user', 'gwapoako@gmail.com', '2026-10-22 05:29:19'),
(352, 1, 'Suspended user', 'jason@gmail.com', '2026-10-22 05:32:52'),
(353, 1, 'Active user', 'jason@gmail.com', '2026-10-22 05:32:57'),
(354, 1, 'Suspended user', 'jason@gmail.com', '2026-10-22 05:33:05'),
(355, 1, 'Active user', 'jason@gmail.com', '2026-10-22 05:33:18'),
(356, 1, 'Suspended user', 'jason@gmail.com', '2026-10-22 05:34:42'),
(357, 1, 'Suspended user', 'jakeperalta@gmail.com', '2026-10-22 05:36:42'),
(358, 1, 'Suspended user', 'glennxlord@gmail.com', '2026-10-22 05:37:25'),
(359, 1, 'Suspended user', 'glenn@gmail.com', '2026-10-22 05:40:29'),
(360, 1, 'Active user', 'glenn@gmail.com', '2026-10-22 05:44:58'),
(361, 1, 'Suspended user', 'glenn@gmail.com', '2026-10-22 05:45:04'),
(362, 1, 'Active user', 'glenn@gmail.com', '2026-10-22 05:45:11'),
(363, 1, 'Suspended user', 'glenn@gmail.com', '2026-10-22 05:45:25'),
(364, 1, 'Suspended user', 'jose@gmail.com', '2026-10-22 05:54:19'),
(365, 1, 'Active user', 'jose@gmail.com', '2026-10-22 05:57:35'),
(366, 1, 'Suspended user', 'jose@gmail.com', '2026-10-22 05:57:39'),
(367, 1, 'Active user', 'jose@gmail.com', '2026-10-22 05:57:48'),
(368, 1, 'Suspended user', 'jose@gmail.com', '2026-10-22 05:57:50'),
(369, 1, 'Active user', 'jose@gmail.com', '2026-10-22 05:58:22'),
(370, 1, 'Suspended user', 'jose@gmail.com', '2026-10-22 05:58:23'),
(371, 34, 'login', '', '2026-10-22 06:18:07'),
(372, 4, 'login', '', '2026-10-22 06:33:05'),
(373, 1, 'login', '', '2026-10-22 06:38:13'),
(374, 34, 'login', '', '2026-10-22 06:40:36'),
(375, 34, 'Issued violation', 'Lease Violation — strike 2', '2026-10-22 06:41:20'),
(376, 34, 'Sent notification', 'Payment Reminder to 1 tenant(s)', '2026-10-22 06:42:34'),
(377, 4, 'login', '', '2026-10-22 06:44:01'),
(378, 1, 'login', '', '2026-10-22 06:44:12'),
(379, 1, 'Active user', 'gwapoako@gmail.com', '2026-10-22 06:44:44'),
(380, 4, 'login', '', '2026-10-22 06:45:28'),
(381, 1, 'login', '', '2026-10-22 06:45:37'),
(382, 1, 'Suspended user', 'gwapoako@gmail.com', '2026-10-22 06:45:45'),
(383, 1, 'Active user', 'gwapoako@gmail.com', '2026-10-22 06:45:54'),
(384, 4, 'login', '', '2026-10-22 06:46:03'),
(385, 34, 'Advanced maintenance ticket', '[Air Conditioning - Not cooling] Waay mn ni ga andar nga aircon!!! → Scheduled', '2026-10-22 06:46:13'),
(386, 1, 'login', '', '2026-10-22 08:34:01'),
(387, 1, 'login', '', '2026-10-22 10:23:33'),
(388, 1, 'Active user', 'jose@gmail.com', '2026-10-22 10:26:36'),
(389, 1, 'Suspended user', 'jose@gmail.com', '2026-10-22 10:26:38'),
(390, 1, 'Active user', 'jose@gmail.com', '2026-10-22 13:57:48'),
(391, 1, 'Suspended user', 'jose@gmail.com', '2026-10-22 13:57:51'),
(392, 34, 'login', '', '2026-10-22 19:02:17'),
(393, 34, 'Submitted property for verification', 'CyberPunk', '2026-10-22 19:08:28'),
(394, 36, 'register', 'New lessor: Barroness Tower', '2026-10-22 19:14:30'),
(395, 36, 'login (OTP verified)', '', '2026-10-22 19:15:11'),
(396, 34, 'Submitted property for verification', 'CyberPunk', '2026-10-22 19:15:39'),
(397, 1, 'Active user', 'jose@gmail.com', '2026-10-22 19:36:57'),
(398, 1, 'Active user', 'jason@gmail.com', '2026-10-22 19:43:21'),
(399, 1, 'Suspended user', 'jason@gmail.com', '2026-10-22 19:44:02'),
(400, 1, 'Suspended user', 'jose@gmail.com', '2026-10-22 19:47:21'),
(401, 1, 'Active user', 'jose@gmail.com', '2026-10-22 19:47:24'),
(402, 1, 'Sent lessor notice', 'Verification Reminder to 5 lessor(s)', '2026-10-22 20:04:12'),
(403, 1, 'Sent lessor notice', 'Policy Update to 5 lessor(s)', '2026-10-22 20:06:41'),
(404, 1, 'Sent lessor notice', 'Policy Update to 5 lessor(s)', '2026-10-22 20:11:46'),
(405, 34, 'login', '', '2026-10-22 21:27:47'),
(406, 2, 'login', '', '2026-10-22 22:33:46'),
(407, 1, 'login', '', '2026-10-22 23:05:07'),
(408, 1, 'Approved lessor verification', 'Lessor #36', '2026-10-22 23:05:17'),
(409, 1, 'Approved property verification', 'Property #14', '2026-10-22 23:05:27'),
(410, 37, 'register', 'New lessor: Lux Tower', '2026-10-22 23:18:20'),
(411, 37, 'login (OTP verified)', '', '2026-10-22 23:18:40'),
(412, 1, 'Approved lessor ID 37', '', '2026-10-22 23:29:40'),
(413, 37, 'login', '', '2026-10-22 23:40:24'),
(414, 38, 'register', 'New lessor: Flower TOwer', '2026-10-22 23:43:50'),
(415, 38, 'login (OTP verified)', '', '2026-10-22 23:44:09'),
(416, 1, 'Approved lessor ID 38', '', '2026-10-22 23:44:19'),
(417, 1, 'Approved property verification', 'Property #13', '2026-10-22 23:50:10'),
(418, 2, 'login', '', '2026-10-22 23:51:45'),
(419, 2, 'Updated property details', 'The J&R Commercial Center', '2026-10-22 23:52:03'),
(420, 2, 'Updated property details', 'MS City Bacolod', '2026-10-22 23:52:09'),
(421, 2, 'Updated property details', 'Atrium Commercial Plaza', '2026-10-22 23:52:20'),
(422, 2, 'Updated property details', 'The J&R Commercial Center', '2026-10-22 23:54:09'),
(423, 2, 'Updated property details', 'MS City Bacolod', '2026-10-22 23:58:39'),
(424, 2, 'Updated property details', 'MS City Bacolod', '2026-10-22 23:59:01'),
(425, 2, 'Updated property details', 'The J&R Commercial Center', '2026-10-22 23:59:13'),
(426, 2, 'Updated property details', 'Atrium Commercial Plaza', '2026-10-22 23:59:32'),
(427, 2, 'Updated property details', 'Atrium Commercial Plaza', '2026-10-23 00:53:47'),
(428, 12, 'login', '', '2026-10-23 01:02:47'),
(429, 1, 'login', '', '2026-10-23 01:08:56'),
(430, 39, 'Lessee registered & phone verified via app', 'george@gmail.com', '2026-10-23 01:14:08'),
(431, 12, 'login', '', '2026-10-23 02:13:42'),
(432, 1, 'login', '', '2026-10-23 02:14:23'),
(433, 1, 'Active user', 'jakeperalta@gmail.com', '2026-10-23 02:14:43'),
(434, 1, 'login', '', '2026-10-23 02:14:51'),
(435, 12, 'login', '', '2026-10-23 02:15:06'),
(436, 1, 'login', '', '2026-10-23 04:40:15'),
(437, 34, 'login', '', '2026-10-23 04:41:15'),
(438, 2, 'login', '', '2026-10-23 04:49:24'),
(439, 2, 'Advanced maintenance ticket', '[Plumbing - Water leakage] testing → Under Review', '2026-10-23 04:49:43'),
(440, 2, 'Advanced maintenance ticket', '[Plumbing - Water leakage] testing → Approved', '2026-10-23 04:50:20'),
(441, 2, 'Advanced maintenance ticket', '[Plumbing - Water leakage] testing → Contractor Assigned', '2026-10-23 04:50:52'),
(442, 2, 'Updated property details', 'MS City Bacolod', '2026-10-23 04:56:22'),
(443, 2, 'Approved installment plan', '2 payments', '2026-10-23 04:58:27'),
(444, 2, 'Confirmed Tenant Payment', '₱2,000.00 from Chris Acot', '2026-10-23 05:07:16'),
(445, 30, 'Tenant Submitted Payment', '₱5000 for Monthly Rent (Ref: 1234567890)', '2026-10-23 05:09:37'),
(446, 2, 'Confirmed Tenant Payment', '₱5,000.00 from Chris Acot', '2026-10-23 05:09:43'),
(447, 2, 'Logged pending rent payment', '₱9000 via cash for Chris Acot', '2026-10-23 05:11:10'),
(448, 2, 'Confirmed Tenant Payment', '₱9,000.00 from Chris Acot', '2026-10-23 05:11:27'),
(449, 2, 'Advanced maintenance ticket', '[Plumbing - Water leakage] testing → Scheduled', '2026-10-23 05:12:15'),
(450, 2, 'Advanced maintenance ticket', '[Plumbing - Water leakage] testing → In Progress', '2026-10-23 05:12:18'),
(451, 2, 'Issued violation', 'Noise Complaint — strike 2', '2026-10-23 05:12:41'),
(452, 1, 'Suspended user', 'george@gmail.com', '2026-10-23 05:15:27'),
(453, 1, 'Active user', 'george@gmail.com', '2026-10-23 05:15:29'),
(454, 1, 'Active user', 'jason@gmail.com', '2026-10-23 05:15:33'),
(455, 1, 'Suspended user', 'jason@gmail.com', '2026-10-23 05:15:36'),
(456, 1, 'Suspended user', 'chrisdanielacot305@gmail.com', '2026-10-23 05:15:43'),
(457, 1, 'Active user', 'chrisdanielacot305@gmail.com', '2026-10-23 05:15:50'),
(458, 1, 'Suspended user', 'chrisdanielacot305@gmail.com', '2026-10-23 05:15:53'),
(459, 12, 'login', '', '2026-10-23 05:17:27');

-- --------------------------------------------------------

--
-- Table structure for table `deposits`
--

CREATE TABLE `deposits` (
  `id` int(11) NOT NULL,
  `lessor_id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `method` varchar(50) DEFAULT 'Cash',
  `status` enum('locked','released') NOT NULL DEFAULT 'locked',
  `note` varchar(255) DEFAULT NULL,
  `released_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `deposits`
--

INSERT INTO `deposits` (`id`, `lessor_id`, `tenant_id`, `amount`, `created_at`, `method`, `status`, `note`, `released_at`) VALUES
(1, 34, 12, 200000.00, '2026-10-21 21:33:41', 'Cash', 'released', 'Emergency release · Oct 21 · replenishment scheduled', '2026-10-21 05:05:13'),
(2, 34, 13, 50000.00, '2026-10-21 21:33:41', 'Cash', 'locked', NULL, NULL),
(3, 2, 5, 500000.00, '2026-10-21 21:33:41', 'Cash', 'released', 'Emergency release · Oct 21 · replenishment scheduled', '2026-10-21 05:11:44'),
(4, 34, 16, 500000.00, '2026-10-21 21:33:41', 'Cash', 'locked', NULL, NULL),
(5, 34, 16, 120000.00, '2026-10-21 21:33:41', 'Cash', 'locked', NULL, NULL),
(6, 34, 13, 100000.00, '2026-10-21 21:33:41', 'Cash', 'locked', NULL, NULL),
(7, 34, 16, 240000.00, '2026-10-21 21:33:41', 'Cash', 'locked', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `disputes`
--

CREATE TABLE `disputes` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `lessor_id` int(11) NOT NULL,
  `type` varchar(150) NOT NULL,
  `status` enum('open','resolved') NOT NULL DEFAULT 'open',
  `filed_at` datetime NOT NULL DEFAULT current_timestamp(),
  `resolved_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `installment_requests`
--

CREATE TABLE `installment_requests` (
  `id` int(11) NOT NULL,
  `lessor_id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `plan_label` varchar(60) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `current_part` tinyint(4) NOT NULL DEFAULT 1,
  `total_parts` tinyint(4) NOT NULL DEFAULT 2,
  `part1_due_date` date DEFAULT NULL,
  `part2_due_date` date DEFAULT NULL,
  `part1_paid` tinyint(1) NOT NULL DEFAULT 0,
  `part2_paid` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `installment_requests`
--

INSERT INTO `installment_requests` (`id`, `lessor_id`, `tenant_id`, `reason`, `plan_label`, `amount`, `status`, `created_at`, `current_part`, `total_parts`, `part1_due_date`, `part2_due_date`, `part1_paid`, `part2_paid`) VALUES
(2, 2, 5, 'Cash flow delay this month', '2 payments', 2500.00, 'approved', '2026-10-14 17:17:35', 1, 2, NULL, NULL, 0, 0),
(3, 34, 12, 'Seasonal Business Slowdown', '2 payments', 50000.00, 'pending', '2026-10-20 20:08:39', 1, 2, NULL, NULL, 0, 0),
(4, 34, 13, 'Kitchen Accidental Fire', '2 payments', 40000.00, 'pending', '2026-10-21 00:52:43', 1, 2, NULL, NULL, 0, 0),
(5, 34, 13, 'Costly renovation', '2 payments', 65000.00, 'pending', '2026-10-21 01:16:10', 1, 2, NULL, NULL, 0, 0),
(6, 34, 13, 'testing', '3 payments', 3500.00, 'approved', '2026-10-21 05:04:29', 1, 2, NULL, NULL, 0, 0),
(7, 34, 12, 'Seasonal Business Slowdown', '2 payments', 20000.00, 'approved', '2026-10-21 19:01:19', 1, 2, NULL, NULL, 0, 0),
(8, 2, 8, 'Cash flow delay this month', '2 payments', 5000.00, 'approved', '2026-10-23 04:58:18', 1, 2, NULL, NULL, 0, 0);

-- --------------------------------------------------------

--
-- Table structure for table `lease_checklist_acknowledgments`
--

CREATE TABLE `lease_checklist_acknowledgments` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `item_key` varchar(60) NOT NULL,
  `acknowledged_at` datetime NOT NULL DEFAULT current_timestamp(),
  `tenant_id` int(11) DEFAULT NULL,
  `application_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `lease_checklist_acknowledgments`
--

INSERT INTO `lease_checklist_acknowledgments` (`id`, `user_id`, `item_key`, `acknowledged_at`, `tenant_id`, `application_id`) VALUES
(1, 7, 'security_deposit', '2026-08-27 01:03:04', NULL, NULL),
(2, 7, 'rent_escalation', '2026-08-27 01:03:04', NULL, NULL),
(3, 7, 'use_of_premises', '2026-08-27 01:03:04', NULL, NULL),
(4, 7, 'maintenance_responsibility', '2026-08-27 01:03:04', NULL, NULL),
(5, 7, 'no_sublease', '2026-08-27 01:03:04', NULL, NULL),
(6, 7, 'termination_notice', '2026-08-27 01:03:04', NULL, NULL),
(7, 7, 'insurance', '2026-08-27 01:03:04', NULL, NULL),
(8, 7, 'compliance', '2026-08-27 01:03:04', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `lessor_profiles`
--

CREATE TABLE `lessor_profiles` (
  `user_id` int(11) NOT NULL,
  `business_permit_path` varchar(255) DEFAULT NULL,
  `sec_dti_path` varchar(255) DEFAULT NULL,
  `bir_cert_path` varchar(255) DEFAULT NULL,
  `land_title_path` varchar(255) DEFAULT NULL,
  `gov_id_path` varchar(255) DEFAULT NULL,
  `bank_account` varchar(150) DEFAULT NULL,
  `verified_by` int(11) DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `lessor_profiles`
--

INSERT INTO `lessor_profiles` (`user_id`, `business_permit_path`, `sec_dti_path`, `bir_cert_path`, `land_title_path`, `gov_id_path`, `bank_account`, `verified_by`, `verified_at`) VALUES
(2, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-08-21 02:33:07'),
(4, 'uploads/verification/4/business_permit_77f52372.png', 'uploads/verification/4/sec_dti_ac9c0fb6.png', 'uploads/verification/4/bir_cert_055d3d64.png', 'uploads/verification/4/land_title_191e3c3f.png', 'uploads/verification/4/gov_id_8b50f31c.png', NULL, 1, '2026-08-22 06:14:27'),
(6, 'uploads/verification/6/business_permit_b9265cfc.jpg', 'uploads/verification/6/sec_dti_14341017.jpg', 'uploads/verification/6/bir_cert_a60de5f8.jpg', 'uploads/verification/6/land_title_f401abd3.png', 'uploads/verification/6/gov_id_9e8bc6e2.jpg', NULL, 1, '2026-08-23 07:52:15'),
(34, 'uploads/verification/34/business_permit_97e1da93.png', 'uploads/verification/34/sec_dti_dd5c7b6d.png', 'uploads/verification/34/bir_cert_a027ea4f.png', 'uploads/verification/34/land_title_40e5bbaf.png', 'uploads/verification/34/gov_id_da4eb2fd.png', NULL, 1, '2026-10-20 16:15:31'),
(35, 'uploads/verification/35/business_permit_9764bd12.png', 'uploads/verification/35/sec_dti_419161f4.png', 'uploads/verification/35/bir_cert_79a5bc68.png', 'uploads/verification/35/land_title_c8f6fd25.png', 'uploads/verification/35/gov_id_102b6922.png', NULL, 1, '2026-10-21 05:56:43'),
(36, 'uploads/verification/36/business_permit_5987a525.png', 'uploads/verification/36/sec_dti_c14cefb2.png', 'uploads/verification/36/bir_cert_cfbe587c.png', 'uploads/verification/36/land_title_abb9f4f5.png', 'uploads/verification/36/gov_id_154aa5b6.png', NULL, 1, '2026-10-22 23:05:17'),
(37, 'uploads/verification/37/business_permit_d69c94ab.png', 'uploads/verification/37/sec_dti_a3180853.png', 'uploads/verification/37/bir_cert_8ecd7cea.png', 'uploads/verification/37/land_title_47b77b1b.png', 'uploads/verification/37/gov_id_51b6ad2c.png', NULL, 1, '2026-10-22 23:29:40'),
(38, 'uploads/verification/38/business_permit_6243f954.png', 'uploads/verification/38/sec_dti_c7c7065c.png', 'uploads/verification/38/bir_cert_97cc11fd.png', 'uploads/verification/38/land_title_0e6f834b.png', 'uploads/verification/38/gov_id_323bd5b7.png', NULL, 1, '2026-10-22 23:44:19');

-- --------------------------------------------------------

--
-- Table structure for table `maintenance_tickets`
--

CREATE TABLE `maintenance_tickets` (
  `id` int(11) NOT NULL,
  `lessor_id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `category` varchar(40) NOT NULL,
  `priority` enum('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
  `title` varchar(255) NOT NULL,
  `stage` tinyint(4) NOT NULL DEFAULT 0,
  `contractor` varchar(150) DEFAULT NULL,
  `photos` text DEFAULT NULL,
  `sla_due_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `description` text DEFAULT NULL,
  `photo_path` varchar(255) DEFAULT NULL,
  `resolution_notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `maintenance_tickets`
--

INSERT INTO `maintenance_tickets` (`id`, `lessor_id`, `tenant_id`, `category`, `priority`, `title`, `stage`, `contractor`, `photos`, `sla_due_at`, `created_at`, `description`, `photo_path`, `resolution_notes`) VALUES
(1, 2, 2, 'plumbing', 'medium', 'hhh', 7, NULL, NULL, NULL, '2026-08-21 10:17:48', NULL, NULL, NULL),
(2, 2, 5, 'electrical', 'medium', '[Electrical] Breaker tripped', 7, NULL, NULL, NULL, '2026-09-19 14:27:59', NULL, NULL, NULL),
(3, 2, 8, 'lighting', 'medium', '[Lighting] Bulb replacement', 2, NULL, NULL, NULL, '2026-10-19 04:53:57', NULL, NULL, NULL),
(4, 34, 13, 'cleaning', 'medium', 'dusty cabinets', 5, NULL, NULL, '2026-10-21 03:07:02', '2026-10-20 20:07:02', NULL, NULL, NULL),
(5, 34, 12, 'lighting', 'medium', 'Flickering Lights', 7, NULL, NULL, '2026-10-20 22:11:00', '2026-10-20 20:11:00', NULL, NULL, NULL),
(6, 2, 10, 'comfort room', 'urgent', '[Comfort Room - Clogged toilet] Ga bara na di mga e ats ho pls fix', 0, NULL, '[\"uploads\\/maintenance\\/6\\/photo_0f22ef79.jpg\",\"uploads\\/maintenance\\/6\\/photo_292dde81.jpg\"]', NULL, '2026-10-21 02:42:03', NULL, NULL, NULL),
(7, 2, 10, 'electrical', 'medium', 'Flickering Light BuLBS', 0, NULL, NULL, NULL, '2026-10-21 02:44:31', NULL, NULL, NULL),
(8, 34, 16, 'plumbing', 'urgent', 'Water Licking', 3, NULL, NULL, '2026-10-22 09:14:27', '2026-10-21 23:14:27', NULL, NULL, NULL),
(9, 34, 13, 'hvac', 'high', 'leakage from the ventilation', 3, 'Johnny Bravo', NULL, '2026-10-22 19:28:09', '2026-10-21 23:28:09', NULL, NULL, NULL),
(10, 34, 16, 'structural', 'high', 'Wall cracks appear', 1, NULL, NULL, '2026-10-22 14:31:20', '2026-10-21 23:31:20', NULL, NULL, NULL),
(11, 34, 19, 'hvac', 'urgent', '[Air Conditioning - Not cooling] Waay mn ni ga andar nga aircon!!!', 4, 'Raymund Trespic', '[\"uploads\\/maintenance\\/11\\/photo_c91ed671.jpg\"]', NULL, '2026-10-22 00:22:58', NULL, NULL, NULL),
(12, 2, 10, 'cleaning', 'urgent', '[Cleanliness - Trash disposal] Ang basura please ga tambak na', 0, NULL, '[\"uploads\\/maintenance\\/12\\/photo_359ef739.png\"]', NULL, '2026-10-22 00:46:57', NULL, NULL, NULL),
(13, 2, 8, 'plumbing', 'medium', '[Plumbing - Water leakage] testing', 5, 'John Doe', '[\"uploads\\/maintenance\\/13\\/photo_ba86fd95.jpg\"]', NULL, '2026-10-23 04:48:43', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `thread_id` int(11) NOT NULL,
  `sender` enum('lessor','tenant') NOT NULL,
  `notice_type` varchar(60) DEFAULT NULL,
  `body` text NOT NULL,
  `sent_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `messages`
--

INSERT INTO `messages` (`id`, `thread_id`, `sender`, `notice_type`, `body`, `sent_at`) VALUES
(1, 1, 'tenant', NULL, 'Hey', '2026-08-22 05:06:33'),
(2, 1, 'lessor', NULL, 'gahod', '2026-08-22 05:07:09'),
(3, 2, 'lessor', NULL, 'hello', '2026-09-07 10:04:20'),
(4, 1, 'tenant', NULL, 'Sabad simo glenn', '2026-09-08 00:51:54'),
(5, 1, 'lessor', NULL, 'gwapo ko', '2026-09-08 00:51:58'),
(6, 1, 'lessor', NULL, 'gwapo ko', '2026-09-08 00:52:07'),
(7, 2, 'tenant', NULL, 'boss musta', '2026-09-14 10:54:17'),
(8, 3, 'tenant', NULL, 'hi', '2026-09-14 13:18:58'),
(9, 2, 'tenant', NULL, '🛠️ Maintenance Report (#SL-00002)\nCategory: Electrical • Breaker tripped\nDescription: Breaker tripped\nStatus: Pending Review', '2026-09-19 14:27:59'),
(10, 2, 'tenant', NULL, 'salamat gd boss', '2026-09-19 14:30:05'),
(11, 2, 'tenant', NULL, 'meow', '2026-09-21 14:52:40'),
(12, 2, 'tenant', NULL, 'nice bakod', '2026-09-21 15:22:33'),
(13, 4, 'tenant', NULL, '👋 Hello! Just checking in.', '2026-09-28 10:57:33'),
(14, 4, 'tenant', NULL, 'Hello', '2026-09-29 00:47:41'),
(15, 4, 'tenant', NULL, 'meow', '2026-09-29 00:48:33'),
(16, 2, 'tenant', NULL, 'hello kamusta', '2026-10-06 14:09:46'),
(17, 2, 'tenant', NULL, 'arigatou', '2026-10-13 00:48:19'),
(18, 5, 'tenant', NULL, '👋 Hello! Just checking in.', '2026-10-14 14:30:02'),
(19, 5, 'tenant', NULL, 'hh', '2026-10-14 14:46:29'),
(20, 2, 'tenant', NULL, '📋 Payment Restructuring Request\nPlan: 2 payments\nReason: Cash flow delay this month\nSchedule:\n• Payment 1 (50%): ₱1,250 (Oct 31, 2026)\n• Payment 2 (50%): ₱1,250 (Nov 15, 2026)\nStatus: Pending Review (48h Cooling-off Active)', '2026-10-14 17:17:35'),
(21, 5, 'tenant', NULL, '🛠️ Maintenance Report (#SL-00003)\nCategory: Lighting • Bulb replacement\nDescription: Bulb replacement\nStatus: Pending Review', '2026-10-19 04:53:58'),
(22, 5, 'lessor', NULL, 'test gether', '2026-10-19 04:55:53'),
(23, 5, 'lessor', NULL, 'test', '2026-10-19 04:56:10'),
(24, 5, 'lessor', NULL, 'test', '2026-10-19 04:56:24'),
(25, 5, 'lessor', NULL, 'gether', '2026-10-19 04:57:00'),
(26, 6, 'lessor', NULL, 'hihi', '2026-10-20 19:56:45'),
(27, 6, 'lessor', NULL, 'hello', '2026-10-20 20:00:03'),
(28, 6, 'lessor', NULL, 'Konichiwa', '2026-10-20 20:00:23'),
(29, 6, 'lessor', NULL, 'konbawa', '2026-10-20 20:02:49'),
(30, 6, 'lessor', 'General Notice', 'Prepare for a sudden blackout', '2026-10-20 20:04:09'),
(31, 7, 'lessor', NULL, 'hello', '2026-10-21 00:32:32'),
(32, 7, 'lessor', NULL, 'hi', '2026-10-21 00:32:34'),
(33, 7, 'lessor', NULL, 'konbanwa', '2026-10-21 00:36:06'),
(34, 8, 'tenant', NULL, 'hello po', '2026-10-21 01:02:53'),
(35, 7, 'lessor', NULL, 'hi', '2026-10-21 01:13:12'),
(36, 8, 'tenant', NULL, '🛠️ Maintenance Report (#SL-00006)\nCategory: Comfort Room • Clogged toilet\nDescription: Ga bara na di mga e ats ho pls fix\nAttachments: 2 photo(s) attached\nStatus: Pending Review', '2026-10-21 02:42:10'),
(37, 5, 'lessor', NULL, 'sanity check', '2026-10-21 05:26:20'),
(38, 9, 'tenant', NULL, '🛠️ Maintenance Report (#SL-00011)\nCategory: Air Conditioning • Not cooling\nDescription: Waay mn ni ga andar nga aircon!!!\nAttachments: 1 photo(s) attached\nStatus: Pending Review', '2026-10-22 00:22:58'),
(39, 9, 'lessor', NULL, 'Okay', '2026-10-22 00:26:33'),
(40, 9, 'lessor', NULL, 'noted', '2026-10-22 00:26:36'),
(41, 9, 'tenant', NULL, 'so what', '2026-10-22 00:26:56'),
(42, 9, 'lessor', NULL, 'sir raymund will come', '2026-10-22 00:27:18'),
(43, 9, 'lessor', NULL, 'kape kape', '2026-10-22 00:27:28'),
(44, 8, 'tenant', NULL, '🛠️ Maintenance Report (#SL-00012)\nCategory: Cleanliness • Trash disposal\nDescription: Ang basura please ga tambak na\nAttachments: 1 photo(s) attached\nStatus: Pending Review', '2026-10-22 00:46:58'),
(45, 7, 'lessor', NULL, 'tiyi', '2026-10-22 03:06:15'),
(46, 9, 'lessor', 'Payment Reminder', 'Pay', '2026-10-22 06:42:34'),
(47, 8, 'tenant', NULL, 'hello pa follow up pls ang payment thank you', '2026-10-23 02:25:47'),
(48, 5, 'tenant', NULL, '🛠️ Maintenance Report (#SL-00013)\nCategory: Plumbing • Water leakage\nDescription: testing\nAttachments: 1 photo(s) attached\nStatus: Pending Review', '2026-10-23 04:48:43'),
(49, 5, 'tenant', NULL, '📋 Payment Restructuring Request\nPlan: 2 payments\nReason: Cash flow delay this month\nSchedule:\n• Payment 1 (50%): ₱2,500 (Oct 31, 2026)\n• Payment 2 (50%): ₱2,500 (Nov 15, 2026)\nStatus: Pending Review (48h Cooling-off Active)', '2026-10-23 04:58:18'),
(50, 5, 'lessor', 'Infraction Notice', '⚠️ OFFICIAL NOTICE OF INFRACTION (STRIKE 2 OF 3)\nCategory: Noise Complaint\nPenalty Injected: ₱500.00 late/infraction penalty added to billing statement.\n\nA formal citation has been officially recorded against your lease agreement. Please review the citation details and disciplinary policy under your account.', '2026-10-23 05:12:41');

-- --------------------------------------------------------

--
-- Table structure for table `message_threads`
--

CREATE TABLE `message_threads` (
  `id` int(11) NOT NULL,
  `lessor_id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `message_threads`
--

INSERT INTO `message_threads` (`id`, `lessor_id`, `tenant_id`) VALUES
(1, 2, 2),
(2, 2, 5),
(3, 2, 6),
(4, 2, 7),
(5, 2, 8),
(6, 34, 12),
(7, 34, 13),
(8, 2, 10),
(9, 34, 19);

-- --------------------------------------------------------

--
-- Table structure for table `notifications_log`
--

CREATE TABLE `notifications_log` (
  `id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `audience` varchar(60) NOT NULL,
  `title` varchar(150) NOT NULL,
  `body` text NOT NULL,
  `sent_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notifications_log`
--

INSERT INTO `notifications_log` (`id`, `sender_id`, `audience`, `title`, `body`, `sent_at`) VALUES
(1, 1, 'Secret', 'Compliance Notice', 'Pakabuot', '2026-08-21 07:42:35'),
(2, 1, 'All lessors (1)', 'Policy Update', 'gwapo', '2026-08-21 07:49:31'),
(3, 34, '1 selected', 'General Notice', 'Prepare for a sudden blackout', '2026-10-20 20:04:09'),
(4, 1, 'Gether\'s Business', 'Policy Update', 'Testing', '2026-10-21 06:01:04'),
(5, 1, 'Mattel Cosmetic', 'Compliance Notice', 'test', '2026-10-22 03:17:51'),
(6, 1, 'Mattel Cosmetic', 'Verification Reminder', 'test2', '2026-10-22 03:22:34'),
(7, 34, '1 selected', 'Payment Reminder', 'Pay', '2026-10-22 06:42:34'),
(8, 1, 'All lessors (5)', 'Verification Reminder', 'Reminder', '2026-10-22 20:04:12'),
(9, 1, 'All lessors (5)', 'Policy Update', 'Prepare', '2026-10-22 20:06:41'),
(10, 1, 'All lessors (5)', 'Policy Update', 'Please Prepare', '2026-10-22 20:11:46');

-- --------------------------------------------------------

--
-- Table structure for table `notification_recipients`
--

CREATE TABLE `notification_recipients` (
  `id` int(11) NOT NULL,
  `notification_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `read_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notification_recipients`
--

INSERT INTO `notification_recipients` (`id`, `notification_id`, `user_id`, `read_at`) VALUES
(1, 2, 2, '2026-08-21 08:01:39'),
(2, 4, 35, NULL),
(3, 5, 34, '2026-10-22 03:20:32'),
(4, 6, 34, '2026-10-22 03:22:44'),
(6, 8, 35, NULL),
(7, 8, 34, NULL),
(8, 8, 6, NULL),
(9, 8, 2, '2026-10-23 05:13:44'),
(11, 9, 35, NULL),
(12, 9, 34, NULL),
(13, 9, 6, NULL),
(14, 9, 2, '2026-10-23 05:13:38'),
(16, 10, 35, NULL),
(17, 10, 34, NULL),
(18, 10, 6, NULL),
(19, 10, 2, '2026-10-23 05:13:32');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `lessor_id` int(11) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `method` enum('cash','bank_transfer','gcash','check','other') NOT NULL DEFAULT 'cash',
  `note` varchar(255) DEFAULT NULL,
  `paid_at` date NOT NULL,
  `logged_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `status` enum('pending','approved','declined') NOT NULL DEFAULT 'approved',
  `or_number` varchar(100) DEFAULT NULL,
  `proof_image` varchar(255) DEFAULT NULL,
  `billing_period` varchar(30) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `tenant_id`, `lessor_id`, `amount`, `method`, `note`, `paid_at`, `logged_by`, `created_at`, `status`, `or_number`, `proof_image`, `billing_period`) VALUES
(1, 1, 2, 1000.00, 'cash', NULL, '2026-08-22', 2, '2026-08-22 04:54:21', 'approved', NULL, NULL, NULL),
(2, 2, 2, 1000.00, 'cash', NULL, '2026-08-22', 2, '2026-08-22 04:54:44', 'approved', NULL, NULL, NULL),
(3, 2, 2, 2500.00, 'bank_transfer', '[VERIFIED] Target: Monthly Rent | Ref: TEST-123456 | Testing payment insertion', '2026-10-07', 2, '2026-10-07 03:40:14', 'approved', NULL, NULL, NULL),
(4, 2, 2, 2500.00, 'bank_transfer', '[DECLINED: Declined by lessor] Target: Monthly Rent | Ref: JP-REF-998877 | Payment from Jake Peralta mobile', '2026-10-07', NULL, '2026-10-07 04:01:24', 'approved', NULL, NULL, NULL),
(5, 5, 2, 2500.00, 'bank_transfer', '[VERIFIED] Target: Monthly Rent | Ref: 2301876', '2026-10-07', 2, '2026-10-07 05:16:53', 'approved', NULL, NULL, NULL),
(6, 7, 2, 2300.00, 'bank_transfer', '[VERIFIED] Target: Monthly Rent | Ref: 1029481920', '2026-10-12', 2, '2026-10-12 15:49:00', 'approved', NULL, NULL, NULL),
(7, 8, 2, 2800.00, 'bank_transfer', '[VERIFIED] Target: Monthly Rent | Ref: 911691315', '2026-10-12', 2, '2026-10-12 16:00:55', 'approved', NULL, NULL, NULL),
(8, 8, 2, 500.00, 'bank_transfer', '[VERIFIED] [VERIFIED] [VERIFIED] Target: Monthly Rent | Ref: 0193810019', '2026-10-13', 2, '2026-10-13 05:18:06', 'approved', NULL, NULL, NULL),
(9, 13, 34, 20000.00, 'cash', NULL, '2026-10-19', 34, '2026-10-20 20:07:41', 'approved', NULL, NULL, NULL),
(10, 5, 2, 5000.00, 'cash', NULL, '2026-10-21', 2, '2026-10-21 05:16:42', 'approved', NULL, NULL, NULL),
(13, 15, 35, 100000.00, 'bank_transfer', '[PENDING] Target: Monthly Rent | Ref: 1454', '2026-10-21', NULL, '2026-10-21 06:14:24', 'approved', NULL, NULL, NULL),
(14, 16, 34, 10000.00, 'cash', NULL, '2026-10-21', 34, '2026-10-21 22:17:41', 'approved', NULL, NULL, NULL),
(15, 12, 34, 5000.00, 'bank_transfer', NULL, '2026-10-21', 34, '2026-10-21 22:21:41', 'approved', NULL, NULL, NULL),
(16, 12, 34, 1000.00, 'cash', NULL, '2026-10-21', 34, '2026-10-21 22:21:55', 'approved', NULL, NULL, NULL),
(17, 16, 34, 5000.00, 'cash', NULL, '2026-10-21', 34, '2026-10-21 22:26:38', 'approved', NULL, NULL, NULL),
(18, 13, 34, 2000.00, '', NULL, '2026-10-21', 34, '2026-10-21 22:32:09', 'approved', NULL, NULL, NULL),
(19, 18, 34, 2500.00, 'gcash', '[PENDING] Offline manual entry', '2026-10-21', NULL, '2026-10-21 22:54:21', 'approved', NULL, NULL, NULL),
(20, 19, 34, 10000.00, 'bank_transfer', '[PENDING] Target: Monthly Rent | Ref: 23019931', '2026-10-22', NULL, '2026-10-22 00:23:24', 'approved', NULL, NULL, NULL),
(21, 10, 2, 2000.00, 'bank_transfer', '[VERIFIED] Target: Monthly Rent | Ref: 213213213', '2026-10-22', 2, '2026-10-22 00:47:11', 'approved', NULL, NULL, NULL),
(22, 10, 2, 5000.00, 'bank_transfer', '[VERIFIED] Target: Monthly Rent | Ref: 1234567890', '2026-10-23', 2, '2026-10-23 05:09:37', 'approved', NULL, NULL, NULL),
(23, 8, 2, 9000.00, 'cash', '[VERIFIED] Offline manual entry', '2026-10-23', 2, '2026-10-23 05:11:10', 'approved', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `phone_verifications`
--

CREATE TABLE `phone_verifications` (
  `phone` varchar(20) NOT NULL,
  `otp_code` varchar(6) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `phone_verifications`
--

INSERT INTO `phone_verifications` (`phone`, `otp_code`, `expires_at`, `created_at`) VALUES
('639481016790', '913732', '2026-10-15 00:50:39', '2026-10-15 00:40:39'),
('639606986671', '307464', '2026-10-15 00:49:24', '2026-10-15 00:39:24');

-- --------------------------------------------------------

--
-- Table structure for table `platform_settings`
--

CREATE TABLE `platform_settings` (
  `setting_key` varchar(100) NOT NULL,
  `setting_value` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `platform_settings`
--

INSERT INTO `platform_settings` (`setting_key`, `setting_value`) VALUES
('auto_generate_eviction', '0'),
('auto_renewal_offers', '1'),
('maintenance_mode', '0'),
('platform_fee_percent', '5'),
('qr_audit_required', '1');

-- --------------------------------------------------------

--
-- Table structure for table `properties`
--

CREATE TABLE `properties` (
  `id` int(11) NOT NULL,
  `lessor_id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `type` varchar(60) NOT NULL,
  `address` varchar(255) DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `asking_rent` decimal(12,2) DEFAULT NULL,
  `floor_area` varchar(255) DEFAULT NULL,
  `lease_term` varchar(255) DEFAULT NULL,
  `escalation` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `status` enum('pending','verified','rejected') NOT NULL DEFAULT 'pending',
  `title_doc_path` varchar(255) DEFAULT NULL,
  `tax_dec_path` varchar(255) DEFAULT NULL,
  `photos_path` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `occupancy_status` enum('available','occupied','reserved','under_maintenance') NOT NULL DEFAULT 'available'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `properties`
--

INSERT INTO `properties` (`id`, `lessor_id`, `name`, `type`, `address`, `latitude`, `longitude`, `asking_rent`, `floor_area`, `lease_term`, `escalation`, `description`, `status`, `title_doc_path`, `tax_dec_path`, `photos_path`, `created_at`, `occupancy_status`) VALUES
(1, 2, 'Atrium Commercial Plaza', 'Commercial Lot', 'Lacson Street, Bacolod City', 10.6812024, 122.9551428, 2000.00, '5 x 5', '1', '5', NULL, 'verified', 'uploads/properties/1/title_doc_97bf08ad.jpg', 'uploads/properties/1/tax_dec_253bc4b8.jpg', 'uploads/properties/1/photos_37ca3a94.jpg', '2026-08-21 02:59:19', 'available'),
(4, 2, 'MS City Bacolod', 'Commercial Lot', 'MS City Bacolod, Bacolod, 6100 Negros Occidental', 10.6718045, 122.9443041, 5000.00, '500', '1', '2', NULL, 'verified', 'uploads/properties/4/title_doc_f73183f9.png', 'uploads/properties/4/tax_dec_1d65cb1f.png', NULL, '2026-09-23 13:25:34', 'available'),
(5, 2, 'The J&R Commercial Center', 'Retail', 'Vista Street, Brgy. Alijis, Bacolod City', 10.6451105, 122.9403127, 3000.00, '1 x 1', '1', '2', NULL, 'verified', 'uploads/properties/5/title_doc_d87ad8e1.jpg', 'uploads/properties/5/tax_dec_a844b423.jpg', NULL, '2026-09-23 13:35:54', 'available'),
(7, 34, 'Mattel\'s Buidling', 'Office', '88 Locsin A. Palanca Avenue, Bacolod City, Western Visayas, Philippines', 10.6724620, 122.9426982, 100000.00, '1000', '1', '3%', NULL, 'verified', 'uploads/properties/7/title_doc_33a37df7.png', 'uploads/properties/7/tax_dec_0b60f19b.jpg', NULL, '2026-10-20 16:30:39', 'available'),
(8, 34, 'BeeHive', 'Coworking Space', '108-C Locsin A. Palanca Ave, Bacolod City', 10.6785042, 122.9496882, 200000.00, NULL, NULL, NULL, NULL, 'verified', 'uploads/properties/8/title_doc_f9f31492.png', 'uploads/properties/8/tax_dec_5d986ee3.png', NULL, '2026-10-20 17:43:22', 'available'),
(9, 34, 'NotScoobs', 'Warehouse', 'Warehouse No. 4, Delta Logistics Compound, Bredco Port Area, Bacolod City, Western Visayas, Philippines', 10.6776207, 122.9463404, 10000.00, NULL, NULL, NULL, NULL, 'verified', 'uploads/properties/9/title_doc_ef5e3208.png', 'uploads/properties/9/tax_dec_a3da4e61.png', NULL, '2026-10-20 17:54:12', 'available'),
(11, 35, 'Building 2', 'Retail', 'test', NULL, NULL, 500000.00, NULL, NULL, NULL, NULL, 'verified', 'uploads/properties/11/title_doc_8cbb3371.png', 'uploads/properties/11/tax_dec_d5763f9a.png', NULL, '2026-10-21 06:11:36', 'available'),
(12, 4, 'Glenn\'s Team', 'Office', 'NEW YORK', 10.6676089, 122.9626143, 20000.00, NULL, NULL, NULL, NULL, 'verified', 'uploads/properties/12/title_doc_66fd16e6.png', 'uploads/properties/12/tax_dec_68665814.png', NULL, '2026-10-21 23:32:00', 'available'),
(13, 34, 'CyberPunk', 'Office', '42 Sampaguita Street, Barangay Estefania, Bacolod City, Negros Occidental, 6100, Philippines', 10.6709827, 122.9645354, 250000.00, '2000', '2', '4%', 'Prime office space in Bacolod CIty', 'verified', 'uploads/properties/13/title_doc_b576aa19.png', 'uploads/properties/13/tax_dec_59de8dbf.png', NULL, '2026-10-22 19:08:28', 'available'),
(14, 34, 'CyberPunk', 'Office', '42 Sampaguita Street, Barangay Estefania, Bacolod City, Negros Occidental, 6100, Philippines', 10.6709827, 122.9645354, 250000.00, '2000', '2', '4%', 'Prime office space in Bacolod CIty', 'verified', 'uploads/properties/14/title_doc_6f6057ff.png', 'uploads/properties/14/tax_dec_88d89750.png', NULL, '2026-10-22 19:15:39', 'available');

-- --------------------------------------------------------

--
-- Table structure for table `property_photos`
--

CREATE TABLE `property_photos` (
  `id` int(11) NOT NULL,
  `property_id` int(11) NOT NULL,
  `photo_path` varchar(255) NOT NULL,
  `uploaded_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `property_photos`
--

INSERT INTO `property_photos` (`id`, `property_id`, `photo_path`, `uploaded_at`) VALUES
(2, 1, 'uploads/properties/1/photo_3fa34348.jpg', '2026-09-14 12:52:31'),
(3, 4, 'uploads/properties/4/photo_16c39c8d.jpg', '2026-09-23 13:25:34'),
(4, 5, 'uploads/properties/5/photo_200efb27.jpg', '2026-09-23 13:35:54'),
(6, 7, 'uploads/properties/7/photo_20c334f6.webp', '2026-10-20 16:30:39'),
(7, 8, 'uploads/properties/8/photo_dffcd817.jpg', '2026-10-20 17:43:22'),
(8, 9, 'uploads/properties/9/photo_0ed29efc.jpg', '2026-10-20 17:54:12'),
(10, 11, 'uploads/properties/11/photo_e1383f1c.png', '2026-10-21 06:11:36'),
(11, 12, 'uploads/properties/12/photo_b5863724.png', '2026-10-21 23:32:00'),
(12, 13, 'uploads/properties/13/photo_3275bab6.jpg', '2026-10-22 19:08:28'),
(13, 14, 'uploads/properties/14/photo_7fcd34d1.jpg', '2026-10-22 19:15:39');

-- --------------------------------------------------------

--
-- Table structure for table `sms_reminder_logs`
--

CREATE TABLE `sms_reminder_logs` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `lessee_id` int(11) DEFAULT NULL,
  `billing_month` varchar(7) NOT NULL,
  `reminder_type` enum('3_day_advance','overdue_grace','restructure_due') NOT NULL,
  `amount_due` decimal(12,2) NOT NULL DEFAULT 0.00,
  `sent_to` varchar(30) NOT NULL,
  `sms_message` text NOT NULL,
  `sent_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tenants`
--

CREATE TABLE `tenants` (
  `id` int(11) NOT NULL,
  `lessor_id` int(11) NOT NULL,
  `property_id` int(11) NOT NULL,
  `application_id` int(11) DEFAULT NULL,
  `lessee_id` int(11) DEFAULT NULL,
  `tenant_name` varchar(150) NOT NULL,
  `business_name` varchar(150) DEFAULT NULL,
  `offline_email` varchar(150) DEFAULT NULL,
  `offline_phone` varchar(30) DEFAULT NULL,
  `unit_label` varchar(100) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `monthly_rent` decimal(12,2) NOT NULL DEFAULT 0.00,
  `due_day` tinyint(2) NOT NULL DEFAULT 30,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `security_deposit` decimal(12,2) NOT NULL DEFAULT 0.00,
  `advance_rent` decimal(12,2) NOT NULL DEFAULT 0.00,
  `contract_pdf` varchar(255) DEFAULT NULL,
  `status` enum('active','grace_period','overdue','expired','terminated') NOT NULL DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tenants`
--

INSERT INTO `tenants` (`id`, `lessor_id`, `property_id`, `application_id`, `lessee_id`, `tenant_name`, `business_name`, `offline_email`, `offline_phone`, `unit_label`, `created_at`, `monthly_rent`, `due_day`, `start_date`, `end_date`, `security_deposit`, `advance_rent`, `contract_pdf`, `status`) VALUES
(1, 2, 1, 1, NULL, 'jm', NULL, NULL, NULL, NULL, '2026-08-21 09:33:24', 0.00, 30, NULL, NULL, 0.00, 0.00, NULL, 'active'),
(2, 2, 1, 2, 3, 'jm', NULL, NULL, NULL, NULL, '2026-08-21 10:02:16', 0.00, 30, NULL, NULL, 0.00, 0.00, NULL, 'active'),
(3, 2, 1, 3, 7, 'glenn', NULL, NULL, NULL, NULL, '2026-08-27 01:04:28', 0.00, 30, NULL, NULL, 0.00, 0.00, NULL, 'active'),
(4, 2, 1, 5, NULL, 'Daniel Pastor', NULL, NULL, NULL, NULL, '2026-09-06 15:01:51', 0.00, 30, NULL, NULL, 0.00, 0.00, NULL, 'active'),
(5, 2, 1, 6, 12, 'Jake Peralta', 'Uncle Bro Cafe', NULL, NULL, NULL, '2026-09-07 10:03:01', 0.00, 30, NULL, NULL, 0.00, 0.00, NULL, 'active'),
(6, 2, 1, 8, 17, 'Charmain Acot', NULL, NULL, NULL, NULL, '2026-09-14 12:32:28', 0.00, 30, NULL, NULL, 0.00, 0.00, NULL, 'active'),
(7, 2, 5, 11, NULL, 'Jeremy Gonzales', NULL, NULL, NULL, NULL, '2026-09-28 10:48:16', 0.00, 30, NULL, NULL, 0.00, 0.00, NULL, 'active'),
(8, 2, 4, 12, 30, 'Chris Acot', NULL, NULL, NULL, NULL, '2026-10-12 15:59:55', 0.00, 30, NULL, NULL, 0.00, 0.00, NULL, 'active'),
(9, 2, 5, 15, 32, 'Roger Intong', NULL, NULL, NULL, NULL, '2026-10-19 08:01:23', 0.00, 30, NULL, NULL, 0.00, 0.00, NULL, 'active'),
(10, 2, 5, 13, 30, 'Chris Acot', NULL, NULL, NULL, NULL, '2026-10-19 14:36:46', 0.00, 30, NULL, NULL, 0.00, 0.00, NULL, 'active'),
(11, 2, 4, 16, 12, 'Jake Peralta', NULL, NULL, NULL, NULL, '2026-10-20 15:27:23', 0.00, 30, NULL, NULL, 0.00, 0.00, NULL, 'active'),
(12, 34, 7, 17, NULL, 'Barbie Demor', NULL, NULL, NULL, NULL, '2026-10-20 17:36:43', 0.00, 30, NULL, NULL, 0.00, 0.00, NULL, 'active'),
(13, 34, 8, 23, NULL, 'Alice Hitch', NULL, NULL, NULL, NULL, '2026-10-20 18:56:08', 0.00, 30, NULL, NULL, 0.00, 0.00, NULL, 'active'),
(15, 35, 11, 27, 12, 'Jake Peralta', NULL, NULL, NULL, NULL, '2026-10-21 06:13:37', 0.00, 30, NULL, NULL, 0.00, 0.00, NULL, 'active'),
(16, 34, 9, 28, NULL, 'Merida Clan', NULL, NULL, NULL, NULL, '2026-10-21 19:03:24', 0.00, 30, NULL, NULL, 0.00, 0.00, NULL, 'active'),
(17, 34, 7, 22, NULL, 'Anton Corrales', 'Nerva VA', 'antonluiscorrales360@gmail.com', '0912345678', NULL, '2026-10-21 19:03:35', 0.00, 30, '2026-10-26', '2026-11-22', 0.00, 0.00, NULL, 'active'),
(18, 34, 8, 25, NULL, 'Hemish Hems', NULL, NULL, NULL, NULL, '2026-10-21 19:03:42', 0.00, 30, NULL, NULL, 0.00, 0.00, NULL, 'active'),
(19, 34, 8, 29, 32, 'Roger Intong', NULL, NULL, NULL, NULL, '2026-10-22 00:20:41', 0.00, 30, NULL, NULL, 0.00, 0.00, NULL, 'active');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `role` enum('lessor','superadmin','lessee') NOT NULL DEFAULT 'lessor',
  `status` enum('pending','active','suspended') NOT NULL DEFAULT 'pending',
  `company_name` varchar(150) DEFAULT NULL,
  `full_name` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `api_token` varchar(64) DEFAULT NULL,
  `otp_code` varchar(6) DEFAULT NULL,
  `otp_expires_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `avatar_url` varchar(255) DEFAULT NULL,
  `fcm_token` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `role`, `status`, `company_name`, `full_name`, `email`, `phone`, `password_hash`, `api_token`, `otp_code`, `otp_expires_at`, `created_at`, `updated_at`, `avatar_url`, `fcm_token`) VALUES
(1, 'superadmin', 'active', NULL, 'John Michael Antinola', 'pwestora.unknownprogrammer@gmail.com', NULL, '$2y$10$A5DbzVabC1Ysu14JAMBJGeX/fzdO8a/iZzNsy8flypxo/4ETM2Mc2', NULL, NULL, NULL, '2026-08-21 02:22:51', '2026-08-23 08:50:49', NULL, NULL),
(2, 'lessor', 'active', 'Secret', 'glenn', 'jmantinola54@gmail.com', '0911', '$2y$10$ohDDjgx2N4pe9KTIlgXJx.kBFQpI9FOuYvilQVaGAO1fTranky/Ga', NULL, NULL, NULL, '2026-08-21 02:32:16', '2026-08-23 08:58:02', NULL, NULL),
(3, 'lessee', 'active', NULL, 'jm', 'jm@gmail.com', '588558', '$2y$10$2CE9Oa3PYijMUq5uqdbqquZZrXBQfvIZ4UmWChFna43VN3.xUW22.', '529f64c6af45b83b98a6eeef6de9d037ae4741876177a771e3fa550ca4dee281', NULL, NULL, '2026-08-21 09:27:25', '2026-10-19 05:08:07', NULL, NULL),
(4, 'lessor', 'suspended', 'gwapo ako', 'gwaposiglenn?', 'glennxlord@gmail.com', '09646454545454', '$2y$10$6JQXg1fjpn0kZCXeSWdHBOeLtLnpBkJTEVy68Z45xocNcm1fDQmse', NULL, NULL, NULL, '2026-08-21 14:16:11', '2026-10-22 05:37:25', NULL, NULL),
(5, 'lessee', 'suspended', NULL, 'h', 'glenn@gmail.com', '39', '$2y$10$0r35W.kN81dvkhWSdBPn6eKxU0DekhtdAkzK0SClosxYxnqBEDK2.', '930ba507aa99e10f3a0ee2e9b3ad3883582f27bae87da55c6f9ce9ef81db758b', NULL, NULL, '2026-08-21 22:11:11', '2026-10-22 05:45:25', NULL, NULL),
(6, 'lessor', 'active', 'Sample', 'Bacolod', 'jmantinola58@gmail.com', '09323232', '$2y$10$JP1w8Cq6ZEggbYx8PNNlGeRzlTDN/AUXBj9KSjvd7xDIBArAOaAJC', NULL, NULL, NULL, '2026-08-23 07:20:22', '2026-08-23 07:55:09', NULL, NULL),
(7, 'lessee', 'active', NULL, 'glenn', 'witness.22.15@gmail.com', '123456789', '$2y$10$wRXV/KSPpzMW1DwdSqmLJu4AWxPDSbhUkqGprZQPQpKjGCrO7OCMa', '6b6d6103c433884bb22083a687d56f013b9ef800090b200c89e2f9f5bc42aebe', NULL, NULL, '2026-08-27 01:01:54', '2026-08-27 01:01:54', NULL, NULL),
(8, 'lessee', 'active', NULL, 'glenngwapo', 'paolopastorcot305@gmail.com', '09649454588', '$2y$10$S.HX8Fdw.foiE.P.UiM4vuZACi/o5X5m5V7O7CjhCocNfgcSf/KZS', '56cf9e21c7a26e381914ca2d070443b59fed3367c9b15916d2253f2a003517d7', NULL, NULL, '2026-08-27 01:36:11', '2026-08-27 01:36:11', NULL, NULL),
(11, 'lessee', 'active', NULL, 'jm', 'it.prolifemanagement@gmail.com', '688686', '$2y$10$RLnytSI9nOSeF6wGjN2pHuXyQv7loqcTDewbwjkIpyUVrCBFlEYEy', '7ff2e5cd78e6ce9c86fafcb37f4f0bab50fd6a3acc3ff3b28d9f5465f5c696da', NULL, NULL, '2026-09-07 05:34:38', '2026-09-07 05:34:38', NULL, NULL),
(12, 'lessee', 'active', NULL, 'Jake Peralta', 'jakeperalta@gmail.com', '09123141146', '$2y$10$bbfDBJyaOx6adVzjLmCxwe2kPEaWvg6vpVGj48xEqUeTZ6JYsS4jW', 'daf7cb5efa8265913da70bc933032bded3ab6dbe9c8f1795cbe89af25d05ddd4', NULL, NULL, '2026-09-07 10:00:21', '2026-10-23 04:39:17', NULL, NULL),
(13, 'lessee', 'active', NULL, 'John Sazon', 'johnsazon@gmail.com', '09945884122', '$2y$10$42L/ToWnbwHocHQbFzcI6edodd3dt4CMnS/FyAPA.CppA6C1OyicC', '85425aead077c44f4aa287f70aca9d1794a9166646dbf52e51404bb05a94bbfd', NULL, NULL, '2026-09-07 13:48:40', '2026-09-08 00:44:53', NULL, NULL),
(14, 'lessee', 'active', NULL, 'Holt Raymund', 'holt@gmail.com', '09266982730', '$2y$10$PMnMN9NGUEkkrSCyqs5g1uBBmuwRQHqe.GyZNaCcqiLUrBVUEFM0C', '0604c2fffb69cfaa6c297c9566a16e4d519f8beea49c532f1f65a05b864f0948', NULL, NULL, '2026-09-12 04:33:22', '2026-09-12 04:33:22', NULL, NULL),
(17, 'lessee', 'active', NULL, 'Charmain Acot', 'charmaine@gmail.com', '09937481293', '$2y$10$t0AQW/DB63EKZBQVJj2QI.dcgzK9t2FjUAtzHkYmrQHiutqVMQX5S', '363147476a08922748fc2dab03f23a2eba9b9b6e5d64f60beb60cb06807e00df', NULL, NULL, '2026-09-14 11:12:40', '2026-09-14 13:44:14', NULL, NULL),
(18, 'lessee', 'active', NULL, 'James Bondey', 'james@gmail.com', '0994531876', '$2y$10$QGmDDX6G0YFC2V4sdafrWuqLUmzDuVj/bu58XjLR8JvDjJ3g/GJ4W', '12ef6f54535db9ba5905befbf85becdf8586dde3d45a4762481801a67a72b6d6', NULL, NULL, '2026-09-14 13:05:28', '2026-09-14 13:05:28', NULL, NULL),
(19, 'lessee', 'active', NULL, 'Glenn Sazon Jr.', 'glennsazon@gmail.com', '09686867213', '$2y$10$0xEGPeSH1i3m2f3PlpubNui65x6E9I/jkKdvsUp1R5qJ0jnz8TG0e', '4b65c43977e184c87dc49674175b8089e1ffc68ae9f7fd326be6cd87c1f93355', NULL, NULL, '2026-09-21 15:24:54', '2026-09-21 15:24:54', NULL, NULL),
(23, 'lessee', 'active', NULL, 'Garry Patrick', 'garry@gmail.com', '094587613425', '$2y$10$fk4YDWYslexpNqotT5xjm.uL9RsX4p0IfAlSFrdZr/52akJha.CCS', 'df83fc685353a883d472c25639f68f7a43f0c49c4217384d1705279aa0d2c0da', NULL, NULL, '2026-09-22 15:34:44', '2026-09-22 15:36:40', NULL, NULL),
(24, 'lessee', 'active', NULL, 'gwapo ako', 'gwapoako@gmail.com', '09543453234', '$2y$10$k5me1/ZUoCXCPUssDqOz9.au8XfYdU84t19Anttok16AB3Qq.6vyu', 'a46c2d27cc8dbd9c17dd6cd039fe1d5ac43dbe4a7f1d9a11028fc756959724e4', NULL, NULL, '2026-09-28 05:04:12', '2026-10-22 06:45:54', NULL, NULL),
(25, 'lessee', 'suspended', NULL, 'Jason Yap', 'jason@gmail.com', '92664319734', '$2y$10$F8PtzAcLZkKYA0bjoD2II./SWu15qB16haAVZOeybSartuiY4f1Sm', 'aedbcd1058a386eff7b42f84ab5191e0acaec020d3a0f61a510c7139d4f868b3', NULL, NULL, '2026-09-29 00:56:06', '2026-10-23 05:15:36', NULL, NULL),
(30, 'lessee', 'suspended', NULL, 'Chris Acot', 'chrisdanielacot305@gmail.com', '09945884126', '$2y$10$dvTOlZBQm5H1KUekjkaIseSK3wV3JBaFUv.CicPEEfqbCMsU6bI8W', '993bb2cd00b22488872317986cc0c6a850aa7c199c7a02331dd9e18a551699fe', NULL, NULL, '2026-10-12 14:45:54', '2026-10-23 14:53:01', NULL, NULL),
(31, 'lessee', 'active', NULL, 'Anton', 'anton@gmail.com', '09673254411', '$2y$10$8zAYf8Lwq7gpAWQRpQ/77.cjXedW.a7.XhoOhC0ud/RSmH/LBw9ai', 'eca80432a9131de51c1637b63377159bf49056972e6ba5f64cf3251f1ccfb0d4', NULL, NULL, '2026-10-19 05:09:01', '2026-10-19 05:09:01', NULL, NULL),
(32, 'lessee', 'active', NULL, 'Roger Intong', 'roger@gmail.com', '09770260508', '$2y$10$/76JrEkBCZHnwHtlcomV5eZPBAmR7hPd4zAZCJiLBei0QPrrdAQua', 'fd492d91bf9ee587b03ce516f97b9725d52a34da0281c06c948a0ee752540ab3', NULL, NULL, '2026-10-19 07:56:19', '2026-10-23 01:19:51', NULL, NULL),
(34, 'lessor', 'active', 'Mattel Cosmetic', 'Aireys Ann Ibugan', 'aireysanni@gmail.com', '09052119399', '$2y$10$BCMa03V1nq1dyJ3ERTBdee4qTnRqHmR3UesqQ6dND4Tzk2vetkSMW', NULL, NULL, NULL, '2026-10-20 15:56:43', '2026-10-21 05:50:33', NULL, NULL),
(35, 'lessor', 'active', 'Gether\'s Business', 'Gether Medel', 'g.medel@usls.edu.ph', '09457250735', '$2y$10$C0gYcyF5XgsD2pnQJKfYq.GNXpbbujREFkKDDCO7Q/tbidXMER6Hi', NULL, NULL, NULL, '2026-10-21 05:54:16', '2026-10-21 06:00:20', NULL, NULL),
(36, 'lessor', 'active', 'Barroness Tower', 'Dame Barroness', 'damebarroness@gmail.com', '09052119399', '$2y$10$2HPWzDvxsjCBWjOgzhlRz.tvMo9Pl4.NmFjiKs7EgM4cfYbhz24CS', NULL, NULL, NULL, '2026-10-22 19:14:30', '2026-10-22 23:05:17', NULL, NULL),
(37, 'lessor', 'active', 'Lux Tower', 'Aireys Ann', 'sirensaireys63@gmail.com', '09052119399', '$2y$10$6orxB4qW57JO1Tv/MmEqpOfYIMZmDisJkLHWPoJGzCVVdUC8XH9FK', NULL, NULL, NULL, '2026-10-22 23:18:20', '2026-10-22 23:29:40', NULL, NULL),
(38, 'lessor', 'active', 'Flower TOwer', 'Aireys Ann', 's2301268@usls.edu.ph', '09052119399', '$2y$10$Ak9l0aC1fxF.BdP1Y573NuEbFr4l4/5IgofK1uHK/746cHJTMfX36', NULL, NULL, NULL, '2026-10-22 23:43:50', '2026-10-22 23:44:19', NULL, NULL),
(39, 'lessee', 'active', NULL, 'George Jeremy', 'george@gmail.com', '09266931720', '$2y$10$9xoBTB0Vc4MCkDE5rKo6vu08vWOQ14vEbS85q3EWN1Dwr8G2piZLO', '72c47872ddbff09b063d4354cae44c435ce47467573645e28b8b744bc5a5fe2d', NULL, NULL, '2026-10-23 01:14:08', '2026-10-23 05:15:29', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `violations`
--

CREATE TABLE `violations` (
  `id` int(11) NOT NULL,
  `lessor_id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `category` varchar(100) NOT NULL,
  `strike` tinyint(4) NOT NULL DEFAULT 1,
  `acknowledged` tinyint(1) NOT NULL DEFAULT 0,
  `issued_at` datetime NOT NULL DEFAULT current_timestamp(),
  `description` text DEFAULT NULL,
  `evidence_photo` varchar(255) DEFAULT NULL,
  `fine_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `acknowledged_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `violations`
--

INSERT INTO `violations` (`id`, `lessor_id`, `tenant_id`, `category`, `strike`, `acknowledged`, `issued_at`, `description`, `evidence_photo`, `fine_amount`, `acknowledged_at`) VALUES
(1, 2, 2, 'Late Operating Hours', 1, 1, '2026-08-22 04:39:09', NULL, NULL, 0.00, NULL),
(2, 2, 2, 'Late Operating Hours', 2, 1, '2026-08-22 05:06:46', NULL, NULL, 0.00, NULL),
(3, 2, 8, 'Safety Violation', 1, 1, '2026-10-19 04:57:19', NULL, NULL, 0.00, NULL),
(4, 34, 18, 'Noise Complaint', 1, 0, '2026-10-22 00:03:33', NULL, NULL, 0.00, NULL),
(5, 34, 19, 'Noise Complaint', 1, 1, '2026-10-22 00:28:03', NULL, NULL, 0.00, NULL),
(6, 34, 19, 'Lease Violation', 2, 1, '2026-10-22 06:41:20', NULL, NULL, 0.00, NULL),
(7, 2, 8, 'Noise Complaint', 2, 1, '2026-10-23 05:12:41', NULL, NULL, 0.00, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `applications`
--
ALTER TABLE `applications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `property_id` (`property_id`),
  ADD KEY `lessor_id` (`lessor_id`),
  ADD KEY `fk_applications_lessee` (`lessee_id`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `actor_id` (`actor_id`);

--
-- Indexes for table `deposits`
--
ALTER TABLE `deposits`
  ADD PRIMARY KEY (`id`),
  ADD KEY `lessor_id` (`lessor_id`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- Indexes for table `disputes`
--
ALTER TABLE `disputes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tenant_id` (`tenant_id`),
  ADD KEY `lessor_id` (`lessor_id`);

--
-- Indexes for table `installment_requests`
--
ALTER TABLE `installment_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `lessor_id` (`lessor_id`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- Indexes for table `lease_checklist_acknowledgments`
--
ALTER TABLE `lease_checklist_acknowledgments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_user_item` (`user_id`,`item_key`);

--
-- Indexes for table `lessor_profiles`
--
ALTER TABLE `lessor_profiles`
  ADD PRIMARY KEY (`user_id`),
  ADD KEY `verified_by` (`verified_by`);

--
-- Indexes for table `maintenance_tickets`
--
ALTER TABLE `maintenance_tickets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `lessor_id` (`lessor_id`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `thread_id` (`thread_id`);

--
-- Indexes for table `message_threads`
--
ALTER TABLE `message_threads`
  ADD PRIMARY KEY (`id`),
  ADD KEY `lessor_id` (`lessor_id`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- Indexes for table `notifications_log`
--
ALTER TABLE `notifications_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sender_id` (`sender_id`);

--
-- Indexes for table `notification_recipients`
--
ALTER TABLE `notification_recipients`
  ADD PRIMARY KEY (`id`),
  ADD KEY `notification_id` (`notification_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tenant_id` (`tenant_id`),
  ADD KEY `lessor_id` (`lessor_id`),
  ADD KEY `logged_by` (`logged_by`);

--
-- Indexes for table `phone_verifications`
--
ALTER TABLE `phone_verifications`
  ADD PRIMARY KEY (`phone`);

--
-- Indexes for table `platform_settings`
--
ALTER TABLE `platform_settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- Indexes for table `properties`
--
ALTER TABLE `properties`
  ADD PRIMARY KEY (`id`),
  ADD KEY `lessor_id` (`lessor_id`);

--
-- Indexes for table `property_photos`
--
ALTER TABLE `property_photos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `property_id` (`property_id`);

--
-- Indexes for table `sms_reminder_logs`
--
ALTER TABLE `sms_reminder_logs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_reminder` (`tenant_id`,`billing_month`,`reminder_type`),
  ADD KEY `idx_tenant_month` (`tenant_id`,`billing_month`);

--
-- Indexes for table `tenants`
--
ALTER TABLE `tenants`
  ADD PRIMARY KEY (`id`),
  ADD KEY `lessor_id` (`lessor_id`),
  ADD KEY `property_id` (`property_id`),
  ADD KEY `application_id` (`application_id`),
  ADD KEY `fk_tenants_lessee` (`lessee_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `api_token` (`api_token`);

--
-- Indexes for table `violations`
--
ALTER TABLE `violations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `lessor_id` (`lessor_id`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `applications`
--
ALTER TABLE `applications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=460;

--
-- AUTO_INCREMENT for table `deposits`
--
ALTER TABLE `deposits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `disputes`
--
ALTER TABLE `disputes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `installment_requests`
--
ALTER TABLE `installment_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `lease_checklist_acknowledgments`
--
ALTER TABLE `lease_checklist_acknowledgments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `maintenance_tickets`
--
ALTER TABLE `maintenance_tickets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=51;

--
-- AUTO_INCREMENT for table `message_threads`
--
ALTER TABLE `message_threads`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `notifications_log`
--
ALTER TABLE `notifications_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `notification_recipients`
--
ALTER TABLE `notification_recipients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `properties`
--
ALTER TABLE `properties`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `property_photos`
--
ALTER TABLE `property_photos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `sms_reminder_logs`
--
ALTER TABLE `sms_reminder_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tenants`
--
ALTER TABLE `tenants`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- AUTO_INCREMENT for table `violations`
--
ALTER TABLE `violations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `applications`
--
ALTER TABLE `applications`
  ADD CONSTRAINT `applications_ibfk_1` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `applications_ibfk_2` FOREIGN KEY (`lessor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_applications_lessee` FOREIGN KEY (`lessee_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `deposits`
--
ALTER TABLE `deposits`
  ADD CONSTRAINT `deposits_ibfk_1` FOREIGN KEY (`lessor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `deposits_ibfk_2` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `disputes`
--
ALTER TABLE `disputes`
  ADD CONSTRAINT `disputes_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `disputes_ibfk_2` FOREIGN KEY (`lessor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `installment_requests`
--
ALTER TABLE `installment_requests`
  ADD CONSTRAINT `installment_requests_ibfk_1` FOREIGN KEY (`lessor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `installment_requests_ibfk_2` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `lease_checklist_acknowledgments`
--
ALTER TABLE `lease_checklist_acknowledgments`
  ADD CONSTRAINT `lease_checklist_acknowledgments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `lessor_profiles`
--
ALTER TABLE `lessor_profiles`
  ADD CONSTRAINT `lessor_profiles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `lessor_profiles_ibfk_2` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `maintenance_tickets`
--
ALTER TABLE `maintenance_tickets`
  ADD CONSTRAINT `maintenance_tickets_ibfk_1` FOREIGN KEY (`lessor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `maintenance_tickets_ibfk_2` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`thread_id`) REFERENCES `message_threads` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `message_threads`
--
ALTER TABLE `message_threads`
  ADD CONSTRAINT `message_threads_ibfk_1` FOREIGN KEY (`lessor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `message_threads_ibfk_2` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications_log`
--
ALTER TABLE `notifications_log`
  ADD CONSTRAINT `notifications_log_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notification_recipients`
--
ALTER TABLE `notification_recipients`
  ADD CONSTRAINT `notification_recipients_ibfk_1` FOREIGN KEY (`notification_id`) REFERENCES `notifications_log` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `notification_recipients_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`lessor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payments_ibfk_3` FOREIGN KEY (`logged_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `properties`
--
ALTER TABLE `properties`
  ADD CONSTRAINT `properties_ibfk_1` FOREIGN KEY (`lessor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `property_photos`
--
ALTER TABLE `property_photos`
  ADD CONSTRAINT `property_photos_ibfk_1` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tenants`
--
ALTER TABLE `tenants`
  ADD CONSTRAINT `fk_tenants_lessee` FOREIGN KEY (`lessee_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `tenants_ibfk_1` FOREIGN KEY (`lessor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tenants_ibfk_2` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tenants_ibfk_3` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `violations`
--
ALTER TABLE `violations`
  ADD CONSTRAINT `violations_ibfk_1` FOREIGN KEY (`lessor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `violations_ibfk_2` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
