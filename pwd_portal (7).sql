-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 21, 2025 at 02:09 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `pwd_portal`
--

-- --------------------------------------------------------

--
-- Table structure for table `applications`
--

CREATE TABLE `applications` (
  `application_id` varchar(40) NOT NULL,
  `user_id` varchar(40) NOT NULL,
  `job_id` varchar(40) NOT NULL,
  `status` enum('Pending','Approved','Declined') DEFAULT 'Pending',
  `match_score` decimal(6,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `relevant_experience` int(11) DEFAULT 0,
  `application_education` varchar(120) DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `applications`
--

INSERT INTO `applications` (`application_id`, `user_id`, `job_id`, `status`, `match_score`, `created_at`, `relevant_experience`, `application_education`) VALUES
('APP_1J56U9PAH33215219', 'USR_1J56U7Q2426AF4170', 'JOB_1J56U590659990EF4', 'Approved', 40.00, '2025-09-15 15:01:10', 0, ''),
('APP_1J5B158UIF2EE3C6C', 'USR_1J5B0PRRK7965472D', 'JOB_1J56U590659990EF4', 'Declined', 20.00, '2025-09-17 05:08:06', 0, ''),
('APP_1J5B1DLV7FE0CCF14', 'USR_1J5B0PRRK7965472D', 'JOB_1J5B1B2JF185B0192', 'Pending', 100.00, '2025-09-17 05:12:41', 5, ''),
('APP_1J5B1HES97C9294CA', 'USR_1J5B1GD9TC6DE8AEB', 'JOB_1J5B1B2JF185B0192', 'Pending', 73.33, '2025-09-17 05:14:45', 1, ''),
('APP_1J5B2E0FN35A02180', 'USR_1J5B2BNJN20DE0A2C', 'JOB_1J5B1B2JF185B0192', 'Pending', 86.67, '2025-09-17 05:30:21', 3, 'High School Diploma'),
('APP_1J5B2H67B770C7FD5', 'USR_1J5B2FRH3F148E76C', 'JOB_1J5B1B2JF185B0192', 'Pending', 86.67, '2025-09-17 05:32:05', 1, 'Bachelor'),
('APP_1J5B2O1OKF6330851', 'USR_1J5B2M5T83B7D8173', 'JOB_1J5B1B2JF185B0192', 'Pending', 73.33, '2025-09-17 05:35:50', 4, 'Bachelor'),
('APP_1J5B8B3A3F609053B', 'USR_1J5B2M5T83B7D8173', 'JOB_1J5B83SA6065725E0', 'Pending', 60.00, '2025-09-17 07:13:37', 1, 'Vocational/Technical'),
('APP_1J5C2J1OJ91D04C62', 'USR_1J5B2M5T83B7D8173', 'JOB_1J5C0UL0AA5A4BEEB', 'Approved', 45.00, '2025-09-17 14:52:20', 0, 'Bachelor’s'),
('APP_1J5DL5080FAC97761', 'USR_1J5B2M5T83B7D8173', 'JOB_1J5DKU5CI792319F1', 'Pending', 31.82, '2025-09-18 05:35:57', 0, 'Bachelor’s'),
('APP_1J5DLBTOCED88E6B8', 'USR_1J5B2FRH3F148E76C', 'JOB_1J5DKU5CI792319F1', 'Approved', 81.82, '2025-09-18 05:39:44', 5, 'Bachelor’s'),
('APP_1J5G4PDL709F9ECBE', 'USR_1J5G4NT5ED833F9DB', 'JOB_1J5B83SA6065725E0', 'Pending', 86.67, '2025-09-19 04:47:44', 2, 'Bachelor’s'),
('APP_1J5GAHCSRE7A0B9B8', 'USR_1J5G4NT5ED833F9DB', 'JOB_1J5G5H24KCB077F5E', 'Pending', 76.67, '2025-09-19 06:28:12', 1, 'Elementary'),
('APP_1J5JCCNGL2B0B9745', 'USR_1J5JCBS0N9F9EABA0', 'JOB_1J5DKU5CI792319F1', 'Pending', 78.18, '2025-09-20 10:58:20', 2, 'Elementary'),
('APP_1J5LK5IL44B88FBDB', 'USR_1J5G4NT5ED833F9DB', 'JOB_1J5DKU5CI792319F1', 'Pending', 43.64, '2025-09-21 07:52:43', 1, 'Senior High School'),
('APP_1J5LLE7LU8D858D96', 'USR_1J5LLAQD65A58E26F', 'JOB_1J5B83SA6065725E0', 'Pending', 46.67, '2025-09-21 08:14:55', 0, '');

-- --------------------------------------------------------

--
-- Table structure for table `application_skills`
--

CREATE TABLE `application_skills` (
  `application_skill_id` varchar(40) NOT NULL,
  `application_id` varchar(40) NOT NULL,
  `skill_id` varchar(40) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `application_skills`
--

INSERT INTO `application_skills` (`application_skill_id`, `application_id`, `skill_id`, `created_at`) VALUES
('ASK_1J5B1DLV8193332E4', 'APP_1J5B1DLV7FE0CCF14', 'SKL_1J5B1B2JI2BF5B8B9', '2025-09-17 05:12:41'),
('ASK_1J5B1DLV8DB36CDE3', 'APP_1J5B1DLV7FE0CCF14', 'SKL_1J5B1B2JH147C43F1', '2025-09-17 05:12:41'),
('ASK_1J5B1DLV9526ECDB5', 'APP_1J5B1DLV7FE0CCF14', 'SKL_1J5B1B2JJ33509B84', '2025-09-17 05:12:41'),
('ASK_1J5B1HESBEBEC4399', 'APP_1J5B1HES97C9294CA', 'SKL_1J5B1B2JI2BF5B8B9', '2025-09-17 05:14:45'),
('ASK_1J5B2E0FP2E179D1A', 'APP_1J5B2E0FN35A02180', 'SKL_1J5B1B2JI2BF5B8B9', '2025-09-17 05:30:21'),
('ASK_1J5B2E0FP6BC5ADFB', 'APP_1J5B2E0FN35A02180', 'SKL_1J5B1B2JJ33509B84', '2025-09-17 05:30:21'),
('ASK_1J5B2H67D10B78202', 'APP_1J5B2H67B770C7FD5', 'SKL_1J5B1B2JJ33509B84', '2025-09-17 05:32:05'),
('ASK_1J5B2H67DDE1F716F', 'APP_1J5B2H67B770C7FD5', 'SKL_1J5B1B2JI2BF5B8B9', '2025-09-17 05:32:05'),
('ASK_1J5B2O1OM4E4C6E52', 'APP_1J5B2O1OKF6330851', 'SKL_1J5B1B2JI2BF5B8B9', '2025-09-17 05:35:50'),
('ASK_1J5B8B3A572A6D149', 'APP_1J5B8B3A3F609053B', 'SKL_1J5B83SAA769B70BC', '2025-09-17 07:13:37'),
('ASK_1J5B8B3A6239161EB', 'APP_1J5B8B3A3F609053B', 'SKL_1J5B83SAA16AA05D2', '2025-09-17 07:13:37'),
('ASK_1J5B8B3A6637D6B4B', 'APP_1J5B8B3A3F609053B', 'SKL_1J5B83SA93F55C7F9', '2025-09-17 07:13:37'),
('ASK_1J5B8B3A7D6E27CD1', 'APP_1J5B8B3A3F609053B', 'SKL_1J5B1B2JI2BF5B8B9', '2025-09-17 07:13:37'),
('ASK_1J5B8B3A7FAE2F3BF', 'APP_1J5B8B3A3F609053B', 'SKL_1J5B1B2JJ33509B84', '2025-09-17 07:13:37'),
('ASK_1J5B8B3A8E3E9AD68', 'APP_1J5B8B3A3F609053B', 'SKL_1J5B83SAA67C5E1CA', '2025-09-17 07:13:37'),
('ASK_1J5B8B3A900C2CC72', 'APP_1J5B8B3A3F609053B', 'SKL_1J5B83SA90FE0FEE7', '2025-09-17 07:13:37'),
('ASK_1J5DL50841A48F2A4', 'APP_1J5DL5080FAC97761', 'SKL_1J5DKUUN4839D1F73', '2025-09-18 05:35:57'),
('ASK_1J5DL5085CC8E0176', 'APP_1J5DL5080FAC97761', 'SKL_1J5DKU5D0A19BAEBD', '2025-09-18 05:35:57'),
('ASK_1J5DL508623028E66', 'APP_1J5DL5080FAC97761', 'SKL_1J5B83SAA67C5E1CA', '2025-09-18 05:35:57'),
('ASK_1J5DL508680BDE3A0', 'APP_1J5DL5080FAC97761', 'SKL_1J5B1B2JJ33509B84', '2025-09-18 05:35:57'),
('ASK_1J5DL508764B43C78', 'APP_1J5DL5080FAC97761', 'SKL_1J5DKU5D16AA94CA6', '2025-09-18 05:35:57'),
('ASK_1J5DL50876C2B38EA', 'APP_1J5DL5080FAC97761', 'SKL_1J5DKU5CV4E041A8A', '2025-09-18 05:35:57'),
('ASK_1J5DLBTOEB2480BBE', 'APP_1J5DLBTOCED88E6B8', 'SKL_1J5DKU5D0A19BAEBD', '2025-09-18 05:39:44'),
('ASK_1J5DLBTOEF9BBD7A0', 'APP_1J5DLBTOCED88E6B8', 'SKL_1J5DKUUN4839D1F73', '2025-09-18 05:39:44'),
('ASK_1J5DLBTOF49268706', 'APP_1J5DLBTOCED88E6B8', 'SKL_1J5B83SAA67C5E1CA', '2025-09-18 05:39:44'),
('ASK_1J5DLBTOFD6C57BC5', 'APP_1J5DLBTOCED88E6B8', 'SKL_1J5B1B2JJ33509B84', '2025-09-18 05:39:44'),
('ASK_1J5DLBTOG41F098C6', 'APP_1J5DLBTOCED88E6B8', 'SKL_1J5DKU5D16AA94CA6', '2025-09-18 05:39:44'),
('ASK_1J5DLBTOHA3FC3187', 'APP_1J5DLBTOCED88E6B8', 'SKL_1J5DKU5CV4E041A8A', '2025-09-18 05:39:44'),
('ASK_1J5G4PDLA8FB39EB7', 'APP_1J5G4PDL709F9ECBE', 'SKL_1J5BOAHOR18D08123', '2025-09-19 04:47:44'),
('ASK_1J5G4PDLB123E9B58', 'APP_1J5G4PDL709F9ECBE', 'SKL_1J5B83SAA67C5E1CA', '2025-09-19 04:47:44'),
('ASK_1J5GAHCST096540CF', 'APP_1J5GAHCSRE7A0B9B8', 'SKL_1J5GAF24GAB7A1B8C', '2025-09-19 06:28:12'),
('ASK_1J5GAHCSUF24494FA', 'APP_1J5GAHCSRE7A0B9B8', 'SKL_1J5GAF24G009B7ED8', '2025-09-19 06:28:12'),
('ASK_1J5JCCNGQ720E66AB', 'APP_1J5JCCNGL2B0B9745', 'SKL_1J5DKU5CUC9E48B63', '2025-09-20 10:58:20'),
('ASK_1J5JCCNGUD12BBE52', 'APP_1J5JCCNGL2B0B9745', 'SKL_1J5DKUUN4839D1F73', '2025-09-20 10:58:20'),
('ASK_1J5JCCNH00F1E939C', 'APP_1J5JCCNGL2B0B9745', 'SKL_1J5DKU5D0A19BAEBD', '2025-09-20 10:58:20'),
('ASK_1J5JCCNH30B054F0A', 'APP_1J5JCCNGL2B0B9745', 'SKL_1J5DKU5CV979368C1', '2025-09-20 10:58:20'),
('ASK_1J5JCCNH7D3E7B858', 'APP_1J5JCCNGL2B0B9745', 'SKL_1J5B83SAA67C5E1CA', '2025-09-20 10:58:20'),
('ASK_1J5LK5IL6D7978CA2', 'APP_1J5LK5IL44B88FBDB', 'SKL_1J5DKU5CUC9E48B63', '2025-09-21 07:52:43'),
('ASK_1J5LLE7M0BA94B268', 'APP_1J5LLE7LU8D858D96', 'SKL_1J5BOAHOR18D08123', '2025-09-21 08:14:55'),
('ASK_1J5LLE7M2680D1F3F', 'APP_1J5LLE7LU8D858D96', 'SKL_1J5B1B2JJ33509B84', '2025-09-21 08:14:55');

-- --------------------------------------------------------

--
-- Table structure for table `jobs`
--

CREATE TABLE `jobs` (
  `job_id` varchar(40) NOT NULL,
  `employer_id` varchar(40) NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text NOT NULL,
  `required_experience` int(11) DEFAULT 0,
  `required_education` varchar(120) DEFAULT NULL,
  `required_skills_input` text DEFAULT NULL,
  `location_city` varchar(120) DEFAULT NULL,
  `location_region` varchar(120) DEFAULT NULL,
  `remote_option` enum('On-site','Hybrid','Work From Home') DEFAULT 'Work From Home',
  `employment_type` enum('Full time','Part time','Contract','Temporary','Internship') DEFAULT 'Full time',
  `salary_currency` varchar(8) DEFAULT 'PHP',
  `salary_min` int(11) DEFAULT NULL,
  `salary_max` int(11) DEFAULT NULL,
  `salary_period` enum('monthly','yearly','hourly') DEFAULT 'monthly',
  `status` enum('Open','Suspended','Closed') NOT NULL DEFAULT 'Open',
  `accessibility_tags` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ;

--
-- Dumping data for table `jobs`
--

INSERT INTO `jobs` (`job_id`, `employer_id`, `title`, `description`, `required_experience`, `required_education`, `required_skills_input`, `location_city`, `location_region`, `remote_option`, `employment_type`, `salary_currency`, `salary_min`, `salary_max`, `salary_period`, `status`, `accessibility_tags`, `created_at`) VALUES
('JOB_1J56U590659990EF4', 'USR_1J56TV15M1F2FE280', 'Call Center', 'Call Center night shift haha', 1, 'College Grad', NULL, '', '', 'Work From Home', 'Full time', 'PHP', NULL, NULL, 'monthly', 'Open', 'PWD-Friendly,Work From Home,Wheelchair Accessible', '2025-09-15 14:58:42'),
('JOB_1J5B1B2JF185B0192', 'USR_1J56TV15M1F2FE280', 'FrontEnd Developer', 'FrontEnd Developer that has infinite imagination soled', 0, '', NULL, '', '', 'Work From Home', 'Full time', 'PHP', NULL, NULL, 'monthly', 'Open', 'PWD-Friendly,Work From Home,Wheelchair Accessible', '2025-09-17 05:11:16'),
('JOB_1J5B83SA6065725E0', 'USR_1J5B68JTBEBF5DAEB', 'Graphic Designer', 'Graphic Designer ETC ETC YTESS', 1, '', 'Customer Support, JavaScript, PHP', 'Lipa City', 'Batangas', 'Work From Home', 'Full time', 'PHP', 15000, 30000, 'monthly', 'Open', 'PWD-Friendly,Screen Reader Friendly,Flexible Hours,Wheelchair Accessible,Internet Allowance,Asynchronous,Work From Home', '2025-09-17 07:09:40'),
('JOB_1J5C0UL0AA5A4BEEB', 'USR_1J5BVJSAO53BB41D8', 'Project Manager', 'kaya bangon na mga dyos ahon na, sumakay sa torrent; pero kaya bang sumabay ng mga old gods sa current', 3, 'Master’s', '', 'Batangas City', 'Batangas', 'Work From Home', 'Full time', 'PHP', 50000, 75000, 'yearly', 'Open', 'PWD-Friendly,Wheelchair Accessible,Screen Reader Friendly,Flexible Hours,Internet Allowance,Asynchronous,Work From Home', '2025-09-17 14:23:43'),
('JOB_1J5DKU5CI792319F1', 'USR_1J5BVJSAO53BB41D8', 'Front End Developer Professor', 'SIMULA NUNG HUMALEK PABALEK SAYO DI NA MAALIS SA ISIPAN KO', 2, '', 'JavaScript, PHP, Python, C#, Java, SQL, HTML/CSS, React, Laravel, Django, Git', 'Quezon City', 'Manila', 'Work From Home', 'Part time', 'PHP', 75000, 100000, 'monthly', 'Open', 'PWD-Friendly,Wheelchair Accessible,Screen Reader Friendly,Work From Home', '2025-09-18 05:32:13'),
('JOB_1J5G583J9001F7873', 'USR_1J5G4VMMM25284FA7', 'Suntukan', 'pang malakasan lang boss', 3, 'Doctorate', 'Customer Support', 'marawi city', 'lanao del sur', 'Work From Home', 'Full time', 'PHP', 10000, 50000, 'monthly', 'Suspended', 'PWD-Friendly,Work From Home', '2025-09-19 04:55:45'),
('JOB_1J5G5H24KCB077F5E', 'USR_1J5G4VMMM25284FA7', 'Virtual Assistant', 'pang halimaw lang to boss', 1, 'High School', '70+ WPM Typing, Flexible Schedule, Strong', 'Lipa City', 'Batangas', 'Work From Home', 'Full time', 'PHP', 50000, 60000, 'monthly', 'Suspended', 'PWD-Friendly,Work From Home', '2025-09-19 05:00:38'),
('JOB_1J5JDC38LCCF5B43E', 'USR_1J5JCE1NFE1D671C3', 'job 1', 'asdasdasd', 5, 'College', '70+ WPM Typing, Flexible Schedule, Team Player, Professional Attitude, Strong Communication, Hotdog, Itlog, Sabaw', 'Lipa City', 'Batangas', 'Work From Home', 'Part time', 'PHP', 1000, 2000, 'monthly', 'Open', 'PWD-Friendly,Work From Home', '2025-09-20 11:15:28'),
('JOB_1J5JDNR6OE7874045', 'USR_1J5JCE1NFE1D671C3', 'job 1', 'asdasdasd', 5, 'College', '70+ WPM Typing, Flexible Schedule, Team Player, Professional Attitude, Strong Communication, Hotdog, Itlog, Sabaw', 'Lipa City', 'Batangas', 'Work From Home', 'Part time', 'PHP', 1000, 2000, 'monthly', 'Open', 'PWD-Friendly,Work From Home', '2025-09-20 11:21:53');

-- --------------------------------------------------------

--
-- Table structure for table `job_reports`
--

CREATE TABLE `job_reports` (
  `report_id` varchar(40) NOT NULL,
  `job_id` varchar(40) NOT NULL,
  `reporter_user_id` varchar(40) NOT NULL,
  `reason` varchar(100) NOT NULL,
  `details` text DEFAULT NULL,
  `status` enum('Open','Resolved') DEFAULT 'Open',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `job_skills`
--

CREATE TABLE `job_skills` (
  `job_skill_id` varchar(40) NOT NULL,
  `job_id` varchar(40) NOT NULL,
  `skill_id` varchar(40) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `job_skills`
--

INSERT INTO `job_skills` (`job_skill_id`, `job_id`, `skill_id`, `created_at`) VALUES
('JSK_1J56U590AEC8083E4', 'JOB_1J56U590659990EF4', 'SKL_1J56U59082963C9AB', '2025-09-15 14:58:42'),
('JSK_1J56U590B5293221B', 'JOB_1J56U590659990EF4', 'SKL_1J56U5909C3D51800', '2025-09-15 14:58:42'),
('JSK_1J5B1B2JK06342D94', 'JOB_1J5B1B2JF185B0192', 'SKL_1J5B1B2JH147C43F1', '2025-09-17 05:11:16'),
('JSK_1J5B1B2JLE2468C1F', 'JOB_1J5B1B2JF185B0192', 'SKL_1J5B1B2JI2BF5B8B9', '2025-09-17 05:11:16'),
('JSK_1J5B1B2JMEEC3BA04', 'JOB_1J5B1B2JF185B0192', 'SKL_1J5B1B2JJ33509B84', '2025-09-17 05:11:16'),
('JSK_1J5DL0T4FD7985984', 'JOB_1J5DKU5CI792319F1', 'SKL_1J5DKU5CUC9E48B63', '2025-09-18 05:33:43'),
('JSK_1J5DL0T4G69DA7493', 'JOB_1J5DKU5CI792319F1', 'SKL_1J5DKUUN41B422A0E', '2025-09-18 05:33:43'),
('JSK_1J5DL0T4GF9B5E8B7', 'JOB_1J5DKU5CI792319F1', 'SKL_1J5DKUUN4839D1F73', '2025-09-18 05:33:43'),
('JSK_1J5DL0T4H7499DB64', 'JOB_1J5DKU5CI792319F1', 'SKL_1J5DKU5D0A19BAEBD', '2025-09-18 05:33:43'),
('JSK_1J5DL0T4I78DCB847', 'JOB_1J5DKU5CI792319F1', 'SKL_1J5DKU5CV979368C1', '2025-09-18 05:33:43'),
('JSK_1J5DL0T4JE1480C0E', 'JOB_1J5DKU5CI792319F1', 'SKL_1J5B1B2JJ33509B84', '2025-09-18 05:33:43'),
('JSK_1J5DL0T4K6908882A', 'JOB_1J5DKU5CI792319F1', 'SKL_1J5DKUUN3EDF5DCF0', '2025-09-18 05:33:43'),
('JSK_1J5DL0T4KA2533473', 'JOB_1J5DKU5CI792319F1', 'SKL_1J5B83SAA67C5E1CA', '2025-09-18 05:33:43'),
('JSK_1J5DL0T4L13B28CA7', 'JOB_1J5DKU5CI792319F1', 'SKL_1J5DKU5CT019E9663', '2025-09-18 05:33:43'),
('JSK_1J5DL0T4LBFCE4D6E', 'JOB_1J5DKU5CI792319F1', 'SKL_1J5DKU5D16AA94CA6', '2025-09-18 05:33:43'),
('JSK_1J5DL0T4M54B0A03A', 'JOB_1J5DKU5CI792319F1', 'SKL_1J5DKU5CV4E041A8A', '2025-09-18 05:33:43'),
('JSK_1J5G5A40R02EB98D3', 'JOB_1J5G583J9001F7873', 'SKL_1J5BOAHOR18D08123', '2025-09-19 04:56:51'),
('JSK_1J5GAG7J9A9998AA1', 'JOB_1J5G5H24KCB077F5E', 'SKL_1J5GAF24GAB7A1B8C', '2025-09-19 06:27:34'),
('JSK_1J5GAG7JA6FA66D89', 'JOB_1J5G5H24KCB077F5E', 'SKL_1J5GAF24G009B7ED8', '2025-09-19 06:27:34'),
('JSK_1J5GAG7JB0F9125EF', 'JOB_1J5G5H24KCB077F5E', 'SKL_1J5GADQVE2C60B6AA', '2025-09-19 06:27:34'),
('JSK_1J5JD781C41EBB437', 'JOB_1J5B83SA6065725E0', 'SKL_1J5B1B2JJ33509B84', '2025-09-20 11:12:49'),
('JSK_1J5JD781CA70CBB77', 'JOB_1J5B83SA6065725E0', 'SKL_1J5BOAHOR18D08123', '2025-09-20 11:12:49'),
('JSK_1J5JD781FC0A0742B', 'JOB_1J5B83SA6065725E0', 'SKL_1J5B83SAA67C5E1CA', '2025-09-20 11:12:49'),
('JSK_1J5JDC39AD426DCF4', 'JOB_1J5JDC38LCCF5B43E', 'SKL_1J5GAF24GAB7A1B8C', '2025-09-20 11:15:28'),
('JSK_1J5JDC39CFBEE938C', 'JOB_1J5JDC38LCCF5B43E', 'SKL_1J5GAF24G009B7ED8', '2025-09-20 11:15:28'),
('JSK_1J5JDC39F17145445', 'JOB_1J5JDC38LCCF5B43E', 'SKL_1J5JDC38O90BB7B46', '2025-09-20 11:15:28'),
('JSK_1J5JDC39IED76EB81', 'JOB_1J5JDC38LCCF5B43E', 'SKL_1J5JDC38S834D3E3A', '2025-09-20 11:15:28'),
('JSK_1J5JDC39L4125732B', 'JOB_1J5JDC38LCCF5B43E', 'SKL_1J5JDC38UFB050C2D', '2025-09-20 11:15:28'),
('JSK_1J5JDC39O44D08F3A', 'JOB_1J5JDC38LCCF5B43E', 'SKL_1J5JDC391C569BD8D', '2025-09-20 11:15:28'),
('JSK_1J5JDC39REAC89507', 'JOB_1J5JDC38LCCF5B43E', 'SKL_1J5JDC3945E06F9AC', '2025-09-20 11:15:28'),
('JSK_1J5JDC39T07F03F54', 'JOB_1J5JDC38LCCF5B43E', 'SKL_1J5JDC3967C8D33E5', '2025-09-20 11:15:28'),
('JSK_1J5JDNR6T21EDA8B6', 'JOB_1J5JDNR6OE7874045', 'SKL_1J5GAF24GAB7A1B8C', '2025-09-20 11:21:53'),
('JSK_1J5JDNR6V5FABB5BB', 'JOB_1J5JDNR6OE7874045', 'SKL_1J5GAF24G009B7ED8', '2025-09-20 11:21:53'),
('JSK_1J5JDNR702A7455D9', 'JOB_1J5JDNR6OE7874045', 'SKL_1J5JDC391C569BD8D', '2025-09-20 11:21:53'),
('JSK_1J5JDNR7111558ED6', 'JOB_1J5JDNR6OE7874045', 'SKL_1J5JDC3945E06F9AC', '2025-09-20 11:21:53'),
('JSK_1J5JDNR73639AF29E', 'JOB_1J5JDNR6OE7874045', 'SKL_1J5JDC38S834D3E3A', '2025-09-20 11:21:53'),
('JSK_1J5JDNR754ADD0815', 'JOB_1J5JDNR6OE7874045', 'SKL_1J5JDC3967C8D33E5', '2025-09-20 11:21:53'),
('JSK_1J5JDNR76380F3C1D', 'JOB_1J5JDNR6OE7874045', 'SKL_1J5JDC38UFB050C2D', '2025-09-20 11:21:53'),
('JSK_1J5JDNR7873FD7FC5', 'JOB_1J5JDNR6OE7874045', 'SKL_1J5JDC38O90BB7B46', '2025-09-20 11:21:53');

-- --------------------------------------------------------

--
-- Table structure for table `skills`
--

CREATE TABLE `skills` (
  `skill_id` varchar(40) NOT NULL,
  `name` varchar(120) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `skills`
--

INSERT INTO `skills` (`skill_id`, `name`) VALUES
('SKL_1J56U59082963C9AB', '50 wpm'),
('SKL_1J5GAF24GAB7A1B8C', '70+ WPM Typing'),
('SKL_1J5B83SAA769B70BC', 'Accessibility'),
('SKL_1J5B83SAA16AA05D2', 'Bootstrap'),
('SKL_1J5DKU5CUC9E48B63', 'C#'),
('SKL_1J5B83SA93F55C7F9', 'Communication'),
('SKL_1J5B1B2JI2BF5B8B9', 'CSS'),
('SKL_1J5BOAHOR18D08123', 'Customer Support'),
('SKL_1J5DKUUN41B422A0E', 'Django'),
('SKL_1J5GAF24G009B7ED8', 'Flexible Schedule'),
('SKL_1J56U5909C3D51800', 'Friendly'),
('SKL_1J5DKUUN4839D1F73', 'Git'),
('SKL_1J5JDC391C569BD8D', 'Hotdog'),
('SKL_1J5B1B2JH147C43F1', 'HTML'),
('SKL_1J5DKU5D0A19BAEBD', 'HTML/CSS'),
('SKL_1J5JDC3945E06F9AC', 'Itlog'),
('SKL_1J5DKU5CV979368C1', 'Java'),
('SKL_1J5B1B2JJ33509B84', 'JavaScript'),
('SKL_1J5DKUUN3EDF5DCF0', 'Laravel'),
('SKL_1J56TV17J49F91DA1', 'none'),
('SKL_1J5B83SAA67C5E1CA', 'PHP'),
('SKL_1J5JDC38S834D3E3A', 'Professional Attitude'),
('SKL_1J5DKU5CT019E9663', 'Python'),
('SKL_1J5DKU5D16AA94CA6', 'React'),
('SKL_1J5JDC3967C8D33E5', 'Sabaw'),
('SKL_1J5DKU5CV4E041A8A', 'SQL'),
('SKL_1J5GADQVE2C60B6AA', 'Strong'),
('SKL_1J5JDC38UFB050C2D', 'Strong Communication'),
('SKL_1J5JDC38O90BB7B46', 'Team Player'),
('SKL_1J5B83SA90FE0FEE7', 'Teamwork'),
('SKL_1J5B83SAA5491B4B8', 'Time Management');

-- --------------------------------------------------------

--
-- Table structure for table `support_tickets`
--

CREATE TABLE `support_tickets` (
  `ticket_id` varchar(40) NOT NULL,
  `user_id` varchar(40) DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  `email` varchar(190) NOT NULL,
  `subject` varchar(150) NOT NULL,
  `message` text NOT NULL,
  `status` enum('Open','Pending','Resolved','Closed') NOT NULL DEFAULT 'Open',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `support_tickets`
--

INSERT INTO `support_tickets` (`ticket_id`, `user_id`, `name`, `email`, `subject`, `message`, `status`, `created_at`, `updated_at`) VALUES
('TCK-dd523ac1d9', NULL, 'Job Seeker', 'jobseeker1@gmail.com', 'Account Suspension', 'Bakit po nasuspend account ko boss', 'Open', '2025-09-19 11:29:48', '2025-09-20 10:30:59');

--
-- Triggers `support_tickets`
--
DELIMITER $$
CREATE TRIGGER `trg_support_tickets_update` BEFORE UPDATE ON `support_tickets` FOR EACH ROW SET NEW.updated_at = CURRENT_TIMESTAMP
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` varchar(40) NOT NULL,
  `name` varchar(120) NOT NULL,
  `date_of_birth` date DEFAULT NULL,
  `gender` enum('Male','Female','Non-binary','Prefer not to say') DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `region` varchar(100) DEFAULT NULL,
  `province` varchar(100) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `full_address` varchar(255) DEFAULT NULL,
  `email` varchar(180) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('job_seeker','employer','admin') NOT NULL DEFAULT 'job_seeker',
  `experience` int(11) DEFAULT 0,
  `education` varchar(120) DEFAULT NULL,
  `education_level` varchar(120) DEFAULT NULL,
  `primary_skill_summary` text DEFAULT NULL,
  `profile_completeness` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `profile_last_calculated` datetime DEFAULT NULL,
  `disability` varchar(255) DEFAULT NULL,
  `disability_type` varchar(120) DEFAULT NULL,
  `disability_severity` enum('Mild','Moderate','Severe') DEFAULT NULL,
  `assistive_devices` varchar(255) DEFAULT NULL,
  `pwd_id_number` varbinary(255) DEFAULT NULL,
  `pwd_id_last4` varchar(8) DEFAULT NULL,
  `pwd_id_status` enum('None','Pending','Verified','Rejected') NOT NULL DEFAULT 'None',
  `pwd_id_review_note` text DEFAULT NULL,
  `pwd_id_reviewed_at` datetime DEFAULT NULL,
  `pwd_id_reviewed_by` varchar(40) DEFAULT NULL,
  `resume` varchar(255) DEFAULT NULL,
  `video_intro` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `company_name` varchar(255) DEFAULT '',
  `business_email` varchar(255) DEFAULT '',
  `company_website` varchar(255) DEFAULT NULL,
  `company_phone` varchar(64) DEFAULT NULL,
  `business_permit_number` varchar(100) DEFAULT NULL,
  `employer_status` enum('Pending','Approved','Suspended','Rejected') DEFAULT 'Pending',
  `employer_doc` varchar(255) DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `name`, `date_of_birth`, `gender`, `phone`, `region`, `province`, `city`, `full_address`, `email`, `password`, `role`, `experience`, `education`, `education_level`, `primary_skill_summary`, `profile_completeness`, `profile_last_calculated`, `disability`, `disability_type`, `disability_severity`, `assistive_devices`, `pwd_id_number`, `pwd_id_last4`, `pwd_id_status`, `pwd_id_review_note`, `pwd_id_reviewed_at`, `pwd_id_reviewed_by`, `resume`, `video_intro`, `created_at`, `company_name`, `business_email`, `company_website`, `company_phone`, `business_permit_number`, `employer_status`, `employer_doc`) VALUES
('USR_1J56TV15M1F2FE280', 'Admin User', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'adminuser@admin.com', '$2y$10$URsuz1WYWjlxA2j8aykupuzkBtbhG3MCxA5W7HbX8lBkmLayhibG2', 'admin', 2, 'College', NULL, NULL, 0, NULL, '', NULL, NULL, NULL, NULL, NULL, 'None', NULL, NULL, NULL, NULL, NULL, '2025-09-15 14:55:18', '', '', NULL, NULL, NULL, 'Pending', ''),
('USR_1J56U7Q2426AF4170', 'Job Seeker', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'jobseeker@gmail.com', '$2y$10$hFzZ8GnI.RU3ZurxILgvIOxoX33RLVi0E79DTWIS1ieaIc9e1w0U.', 'job_seeker', 2, 'College', NULL, NULL, 0, NULL, 'amputated legs', NULL, NULL, NULL, NULL, NULL, 'None', NULL, NULL, NULL, NULL, NULL, '2025-09-15 15:00:05', '', '', NULL, NULL, NULL, 'Pending', ''),
('USR_1J5B0PRRK7965472D', 'Stephen Hawkings', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'shh123@gmail.com', '$2y$10$5xgH1ZSay6sZUnMTm3LKc.mOUl7TVRdG1aktqphzlrLR3ueJgxgPW', 'job_seeker', 0, 'Bachelor', NULL, NULL, 0, NULL, 'cant walk', NULL, NULL, NULL, NULL, NULL, 'None', NULL, NULL, NULL, NULL, NULL, '2025-09-17 05:01:52', '', '', NULL, NULL, NULL, 'Pending', ''),
('USR_1J5B1GD9TC6DE8AEB', 'Malupiton', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'malopit@gmail.com', '$2y$10$Z8UQOYZsC7g0VeU.d5nt7uJ5UDP6Q7eGMWgkd3Bz4V1qS6opYY/Nu', 'job_seeker', 0, 'High School Diploma', NULL, NULL, 0, NULL, '', NULL, NULL, NULL, NULL, NULL, 'None', NULL, NULL, NULL, NULL, NULL, '2025-09-17 05:14:11', '', '', NULL, NULL, NULL, 'Pending', ''),
('USR_1J5B2BNJN20DE0A2C', 'haha', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'haha@gmail.com', '$2y$10$wy4pI0GNixrWvy/Lgn61S.dPfPVk7uyf593qQvgho.iQ6WMzgYn3S', 'job_seeker', 0, '', NULL, NULL, 0, NULL, '', NULL, NULL, NULL, NULL, NULL, 'None', NULL, NULL, NULL, NULL, NULL, '2025-09-17 05:29:06', '', '', NULL, NULL, NULL, 'Pending', ''),
('USR_1J5B2FRH3F148E76C', 'hehe', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'hehe@gmail.com', '$2y$10$Xt64xNHCrF1H04PdPkUVT.rb0seAl.5xnVeS60ZywzVzNLXWcxyg2', 'job_seeker', 0, '', NULL, NULL, 0, NULL, '', NULL, NULL, NULL, NULL, NULL, 'None', NULL, NULL, NULL, NULL, NULL, '2025-09-17 05:31:21', '', '', NULL, NULL, NULL, 'Pending', ''),
('USR_1J5B2M5T83B7D8173', 'ranuel glenn biray', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'biray@gmail.com', '$2y$10$yFMXORSd5wL2dDuIj4I64Oce4.4Vu3QWTFDvxrbA3MfBMcv8iNpwq', 'job_seeker', 0, '', NULL, NULL, 0, NULL, '', NULL, NULL, NULL, NULL, NULL, 'None', NULL, NULL, NULL, NULL, NULL, '2025-09-17 05:34:48', '', '', NULL, NULL, NULL, 'Pending', ''),
('USR_1J5B68JTBEBF5DAEB', 'Fishbook', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'fishbook@gmail.com', '$2y$10$5G3U0I7pPr4jRbhqJJqpp.uD0b.dHiw1rGb6nBTMQX7A0mb9vjRvm', 'employer', 0, '', NULL, NULL, 0, NULL, '', NULL, NULL, NULL, NULL, NULL, 'None', NULL, NULL, NULL, NULL, NULL, '2025-09-17 06:37:18', 'Pesbook', 'Pesbook@pesbook.com', NULL, NULL, '123JaSD9', 'Approved', NULL),
('USR_1J5BVJSAO53BB41D8', 'Lebron James', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'lebronjames@gmail.com', '$2y$10$RZ5iiJJmlUgMT8DVyTzhuOv6uYvvR74qmm9PyAz8BhTIM/YFpreee', 'employer', 0, '', NULL, NULL, 0, NULL, 'amputated legs', NULL, NULL, NULL, NULL, NULL, 'None', NULL, NULL, NULL, NULL, NULL, '2025-09-17 14:00:22', 'NBA', 'nba@2k.com', NULL, NULL, 'MA2i93S', 'Approved', NULL),
('USR_1J5EE25HV5A06B07B', 'Dayet A. Alcantara', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'dayet@gmail.com', '$2y$10$enKkSufnM71IlFz79jRpDuAJMZ819B4dVaYcX.VZRDUx6mKSdsH/a', 'employer', 0, '', NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'None', NULL, NULL, NULL, NULL, NULL, '2025-09-18 12:51:19', 'NU', '', NULL, NULL, 'KSZ291', 'Approved', NULL),
('USR_1J5EE439NF8AC4B2A', 'Pusakal A. Aso', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'bago@gmail.com', '$2y$10$lGzHl/s/pXPW9ZAgGbNDLeUW7ttQ3bQza3uOcleuAHLOoIItFH.Mm', 'job_seeker', 0, '', NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'None', NULL, NULL, NULL, NULL, NULL, '2025-09-18 12:52:22', '', '', NULL, NULL, NULL, 'Pending', NULL),
('USR_1J5G4NT5ED833F9DB', 'Jobseeker 1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'jobseeker1@gmail.com', '$2y$10$EYt/npX0Q4WSDTbyUhErTeBTLTKg0lYHWRqy1ZJ9ModR1RGgsUnu.', 'job_seeker', 0, '', NULL, NULL, 25, '2025-09-21 13:04:16', '', NULL, NULL, NULL, NULL, NULL, 'None', NULL, NULL, NULL, 'uploads/resumes/res_68ce952d65999.pdf', 'uploads/videos/vid_68ce952d6633f.mp4', '2025-09-19 04:46:54', '', '', NULL, NULL, '', 'Pending', NULL),
('USR_1J5G4VMMM25284FA7', 'Employer 1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'employer1@gmail.com', '$2y$10$F4JHMFHVWq4QTRHTZv7mruxLmh78lruIwjL0NKtwPe/fjJrmxvJAK', 'employer', 0, '', NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'None', NULL, NULL, NULL, NULL, NULL, '2025-09-19 04:51:10', 'Employerz', 'employer1@employer.com', NULL, NULL, '9jASDHq', 'Suspended', NULL),
('USR_1J5J4N17RBD36BED7', 'Job S. 2', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'jobseeker2@gmail.com', '$2y$10$ntvPqcpGHmf5BbK51VoZtudiSq3yEbYl9fT86z.N3VSW6gNafrRf6', 'job_seeker', 0, '', NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'None', NULL, NULL, NULL, NULL, NULL, '2025-09-20 08:44:09', '', '', NULL, NULL, NULL, 'Pending', NULL),
('USR_1J5J4O6NF7F3E6B7B', 'Employer 2', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'employer2@gmail.com', '$2y$10$5emJJXIbvSF9RK72ooHZKO0QrIrpMKWCRbn6iUW4aOXifqGk4R2ja', 'employer', 0, '', NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'None', NULL, NULL, NULL, NULL, NULL, '2025-09-20 08:44:47', 'Tigreal', 'tigreal@gmail.com', NULL, NULL, 'MA2i95S', 'Pending', NULL),
('USR_1J5J6RC3C4EB2EC4A', 'Asdasd Asd', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'asdasd@gmail.com', '$2y$10$xb3HYP6uMy3JE.nz8hJpZ.nXl3Bde94iOKxn5eZEI1Rfsj170rMji', 'job_seeker', 0, '', NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'None', NULL, NULL, NULL, NULL, NULL, '2025-09-20 09:21:28', '', '', NULL, NULL, NULL, 'Pending', NULL),
('USR_1J5J6SHHO92741CCB', 'Asd Asd', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'asdsssd@gmail.com', '$2y$10$y3RFVSMX5yFXBTbAcgAcz.gXj1GUrl7yeaj/pwNLYRetFO5XYvs9C', 'employer', 0, '', NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'None', NULL, NULL, NULL, NULL, NULL, '2025-09-20 09:22:07', 'NBAasd', '', NULL, NULL, 'MA2i95SSASD', 'Approved', NULL),
('USR_1J5JCBS0N9F9EABA0', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'jobseeker3@gmail.com', '$2y$10$85r9JaBwmMZrnK9LYeA8EOfmjMnuDvAlxMsme.n4Hx5iKjygFHOiu', 'job_seeker', 0, '', NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'None', NULL, NULL, NULL, NULL, NULL, '2025-09-20 10:57:52', '', '', NULL, NULL, NULL, 'Pending', NULL),
('USR_1J5JCE1NFE1D671C3', 'Employer', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'employer3@gmail.com', '$2y$10$gcY9rOxXwHGdIf84XbEpXuIwZoMh8qgDvfZV324cY2URRm23E5/dS', 'employer', 0, '', NULL, NULL, 0, NULL, 'hahahaha', NULL, NULL, NULL, NULL, NULL, 'None', NULL, NULL, NULL, NULL, NULL, '2025-09-20 10:59:03', 'NBA', 'nba@2k.com', 'https://facebook.com', '09291192929', 'MA2i95SAS', 'Approved', 'uploads/employers/empdoc_68cfcf546bd19.png'),
('USR_1J5LLAQD65A58E26F', 'Job Seeker', NULL, NULL, '09556302474', '4a', 'batangasd', 'manila', 'saas dasd qwd qwd asd', 'jobseeker4@gmail.com', '$2y$10$r97qYG35iPOC6hcaGPZ9H.1Aggm75ccJY5txBuyK/Xdmt8MiNYHN.', 'job_seeker', 0, '', 'High School', 'asdasd', 72, '2025-09-21 17:09:58', 'amputated legs', 'Visual', 'Mild', 'Wheelchair', 0x434b412b6278347430662f3579374f4b4a5347562f374832444358654e58637779736e4a69625a4c7775413d, '3123', 'Pending', NULL, NULL, NULL, NULL, NULL, '2025-09-21 08:13:03', '', '', NULL, NULL, NULL, 'Pending', NULL),
('USR_1J5M1Q37H8E143F78', 'Job Seeker', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'jobseeker5@gmail.com', '$2y$10$X6E2dO09/GhFQZNCNFVj9.P/iInkQhkW19e9EZuOGXQfzv546JRjC', 'job_seeker', 0, '', NULL, NULL, 0, NULL, 'Intellectual', NULL, NULL, NULL, 0x415344415330313233313233313233, '3123', 'Pending', NULL, NULL, NULL, NULL, NULL, '2025-09-21 11:51:07', '', '', NULL, NULL, NULL, 'Pending', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_certifications`
--

CREATE TABLE `user_certifications` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` varchar(40) NOT NULL,
  `name` varchar(180) NOT NULL,
  `issuer` varchar(150) DEFAULT NULL,
  `issued_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `credential_id` varchar(120) DEFAULT NULL,
  `attachment_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_certifications`
--

INSERT INTO `user_certifications` (`id`, `user_id`, `name`, `issuer`, `issued_date`, `expiry_date`, `credential_id`, `attachment_path`, `created_at`) VALUES
(1, 'USR_1J5LLAQD65A58E26F', 'cisco', 'cisco', '2025-08-31', '2025-12-04', '123123', NULL, '2025-09-21 09:09:41');

-- --------------------------------------------------------

--
-- Table structure for table `user_experiences`
--

CREATE TABLE `user_experiences` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` varchar(40) NOT NULL,
  `company` varchar(150) NOT NULL,
  `position` varchar(150) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `is_current` tinyint(1) NOT NULL DEFAULT 0,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_experiences`
--

INSERT INTO `user_experiences` (`id`, `user_id`, `company`, `position`, `start_date`, `end_date`, `is_current`, `description`, `created_at`) VALUES
(1, 'USR_1J5LLAQD65A58E26F', 'alorica', 'malupet', '2025-09-03', NULL, 1, 'wala', '2025-09-21 08:20:19');

-- --------------------------------------------------------

--
-- Table structure for table `user_skills`
--

CREATE TABLE `user_skills` (
  `user_skill_id` varchar(40) NOT NULL,
  `user_id` varchar(40) NOT NULL,
  `skill_id` varchar(40) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_skills`
--

INSERT INTO `user_skills` (`user_skill_id`, `user_id`, `skill_id`, `created_at`) VALUES
('USK_1J56TV17K18882837', 'USR_1J56TV15M1F2FE280', 'SKL_1J56TV17J49F91DA1', '2025-09-15 14:55:18'),
('USK_1J56U8JAB1F84B21C', 'USR_1J56U7Q2426AF4170', 'SKL_1J56U59082963C9AB', '2025-09-15 15:00:31');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `applications`
--
ALTER TABLE `applications`
  ADD PRIMARY KEY (`application_id`),
  ADD UNIQUE KEY `uniq_app_user_job` (`user_id`,`job_id`),
  ADD KEY `idx_applications_user` (`user_id`),
  ADD KEY `idx_applications_job` (`job_id`),
  ADD KEY `idx_app_job_match` (`job_id`,`match_score`);

--
-- Indexes for table `application_skills`
--
ALTER TABLE `application_skills`
  ADD PRIMARY KEY (`application_skill_id`),
  ADD UNIQUE KEY `uniq_app_skill` (`application_id`,`skill_id`),
  ADD KEY `skill_id` (`skill_id`);

--
-- Indexes for table `jobs`
--
ALTER TABLE `jobs`
  ADD PRIMARY KEY (`job_id`),
  ADD KEY `idx_jobs_location` (`location_region`,`location_city`),
  ADD KEY `idx_jobs_status` (`status`),
  ADD KEY `idx_jobs_employer` (`employer_id`),
  ADD KEY `idx_jobs_employer_status_created` (`employer_id`,`status`,`created_at`),
  ADD KEY `idx_jobs_meta` (`employment_type`,`salary_min`,`salary_max`);
ALTER TABLE `jobs` ADD FULLTEXT KEY `ft_title_desc` (`title`,`description`);

--
-- Indexes for table `job_reports`
--
ALTER TABLE `job_reports`
  ADD PRIMARY KEY (`report_id`),
  ADD KEY `reporter_user_id` (`reporter_user_id`),
  ADD KEY `job_id` (`job_id`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `job_skills`
--
ALTER TABLE `job_skills`
  ADD PRIMARY KEY (`job_skill_id`),
  ADD UNIQUE KEY `uniq_job_skill` (`job_id`,`skill_id`),
  ADD KEY `skill_id` (`skill_id`);

--
-- Indexes for table `skills`
--
ALTER TABLE `skills`
  ADD PRIMARY KEY (`skill_id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `support_tickets`
--
ALTER TABLE `support_tickets`
  ADD PRIMARY KEY (`ticket_id`),
  ADD KEY `idx_support_status_created` (`status`,`created_at`),
  ADD KEY `fk_support_user` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `uniq_business_permit` (`business_permit_number`),
  ADD KEY `idx_users_employer_status` (`employer_status`),
  ADD KEY `idx_pwd_status` (`pwd_id_status`),
  ADD KEY `idx_pwd_reviewed_at` (`pwd_id_reviewed_at`);

--
-- Indexes for table `user_certifications`
--
ALTER TABLE `user_certifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_certs_user` (`user_id`),
  ADD KEY `idx_certs_issued` (`issued_date`);

--
-- Indexes for table `user_experiences`
--
ALTER TABLE `user_experiences`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_exp_user` (`user_id`),
  ADD KEY `idx_exp_dates` (`start_date`,`end_date`);

--
-- Indexes for table `user_skills`
--
ALTER TABLE `user_skills`
  ADD PRIMARY KEY (`user_skill_id`),
  ADD UNIQUE KEY `uniq_user_skill` (`user_id`,`skill_id`),
  ADD KEY `skill_id` (`skill_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `user_certifications`
--
ALTER TABLE `user_certifications`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `user_experiences`
--
ALTER TABLE `user_experiences`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `applications`
--
ALTER TABLE `applications`
  ADD CONSTRAINT `applications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `applications_ibfk_2` FOREIGN KEY (`job_id`) REFERENCES `jobs` (`job_id`) ON DELETE CASCADE;

--
-- Constraints for table `application_skills`
--
ALTER TABLE `application_skills`
  ADD CONSTRAINT `application_skills_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `applications` (`application_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `application_skills_ibfk_2` FOREIGN KEY (`skill_id`) REFERENCES `skills` (`skill_id`) ON DELETE CASCADE;

--
-- Constraints for table `jobs`
--
ALTER TABLE `jobs`
  ADD CONSTRAINT `jobs_ibfk_1` FOREIGN KEY (`employer_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `job_reports`
--
ALTER TABLE `job_reports`
  ADD CONSTRAINT `job_reports_ibfk_1` FOREIGN KEY (`job_id`) REFERENCES `jobs` (`job_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `job_reports_ibfk_2` FOREIGN KEY (`reporter_user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `job_skills`
--
ALTER TABLE `job_skills`
  ADD CONSTRAINT `job_skills_ibfk_1` FOREIGN KEY (`job_id`) REFERENCES `jobs` (`job_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `job_skills_ibfk_2` FOREIGN KEY (`skill_id`) REFERENCES `skills` (`skill_id`) ON DELETE CASCADE;

--
-- Constraints for table `support_tickets`
--
ALTER TABLE `support_tickets`
  ADD CONSTRAINT `fk_support_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `user_certifications`
--
ALTER TABLE `user_certifications`
  ADD CONSTRAINT `fk_user_certs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `user_experiences`
--
ALTER TABLE `user_experiences`
  ADD CONSTRAINT `fk_user_experiences_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `user_skills`
--
ALTER TABLE `user_skills`
  ADD CONSTRAINT `user_skills_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_skills_ibfk_2` FOREIGN KEY (`skill_id`) REFERENCES `skills` (`skill_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
