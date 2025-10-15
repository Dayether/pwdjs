-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 15, 2025 at 07:38 AM
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
-- Table structure for table `admin_tasks_log`
--

CREATE TABLE `admin_tasks_log` (
  `id` int(11) NOT NULL,
  `task` varchar(100) NOT NULL,
  `actor_user_id` varchar(40) NOT NULL,
  `mode` varchar(20) NOT NULL,
  `users_scanned` int(11) NOT NULL DEFAULT 0,
  `users_updated` int(11) NOT NULL DEFAULT 0,
  `jobs_scanned` int(11) NOT NULL DEFAULT 0,
  `jobs_updated` int(11) NOT NULL DEFAULT 0,
  `details` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admin_tasks_log`
--

INSERT INTO `admin_tasks_log` (`id`, `task`, `actor_user_id`, `mode`, `users_scanned`, `users_updated`, `jobs_scanned`, `jobs_updated`, `details`, `created_at`) VALUES
(1, 'normalize_disabilities', 'USR_1J56TV15M1F2FE280', 'apply', 30, 0, 8, 0, '{\"examples\":{\"users\":{\"scanned\":30,\"updated\":0,\"examples\":[]},\"jobs\":{\"scanned\":8,\"updated\":0,\"examples\":[]}}}', '2025-10-03 13:22:22'),
(2, 'status_change', 'USR_1J56TV15M1F2FE280', 'single', 0, 0, 0, 0, '{\"target_user_id\":\"USR_1J6PJ6BSSEB2C4CDA\",\"target_role\":\"job_seeker\",\"changed_field\":\"pwd_id_status\",\"new_value\":\"Rejected\",\"reason\":\"pls\"}', '2025-10-06 14:16:28'),
(3, 'status_change', 'USR_1J56TV15M1F2FE280', 'single', 0, 0, 0, 0, '{\"target_user_id\":\"USR_1J5STL1HNF2D40513\",\"target_role\":\"employer\",\"changed_field\":\"employer_status\",\"new_value\":\"Suspended\",\"reason\":\"wala lang\"}', '2025-10-06 14:17:10'),
(4, 'status_change', 'USR_1J56TV15M1F2FE280', 'single', 0, 0, 0, 0, '{\"target_user_id\":\"USR_1J5STL1HNF2D40513\",\"target_role\":\"employer\",\"changed_field\":\"employer_status\",\"new_value\":\"Approved\",\"reason\":\"ok na\"}', '2025-10-06 14:17:33'),
(5, 'status_change', 'USR_1J56TV15M1F2FE280', 'single', 0, 0, 0, 0, '{\"target_user_id\":\"USR_1J5SSJ78OD24BDF34\",\"target_role\":\"job_seeker\",\"changed_field\":\"pwd_id_status\",\"new_value\":\"Verified\",\"reason\":\"asd\"}', '2025-10-06 14:52:32'),
(6, 'status_change', 'USR_1J56TV15M1F2FE280', 'single', 0, 0, 0, 0, '{\"target_user_id\":\"USR_1J5SSAARPCF2B618C\",\"target_role\":\"job_seeker\",\"changed_field\":\"pwd_id_status\",\"new_value\":\"Verified\",\"reason\":\"ge\"}', '2025-10-06 15:07:30'),
(7, 'status_change', 'USR_1J56TV15M1F2FE280', 'single', 0, 0, 0, 0, '{\"target_user_id\":\"USR_1J71RFCV5FFBD0014\",\"target_role\":\"employer\",\"changed_field\":\"employer_status\",\"new_value\":\"Approved\",\"reason\":\"hahaha\"}', '2025-10-08 12:07:18'),
(8, 'job_moderation', 'USR_1J56TV15M1F2FE280', 'single', 0, 0, 0, 1, '{\"job_id\":\"JOB_1J745IBUEB8BF98EA\",\"new_status\":\"Approved\",\"reason\":\"\"}', '2025-10-09 09:56:50');

-- --------------------------------------------------------

--
-- Table structure for table `applications`
--

CREATE TABLE `applications` (
  `application_id` varchar(40) NOT NULL,
  `user_id` varchar(40) NOT NULL,
  `job_id` varchar(40) NOT NULL,
  `status` enum('Pending','Approved','Declined') DEFAULT 'Pending',
  `employer_feedback` text DEFAULT NULL,
  `decision_at` timestamp NULL DEFAULT NULL,
  `match_score` decimal(6,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `relevant_experience` int(11) DEFAULT 0,
  `application_education` varchar(120) DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `applications`
--

INSERT INTO `applications` (`application_id`, `user_id`, `job_id`, `status`, `employer_feedback`, `decision_at`, `match_score`, `created_at`, `relevant_experience`, `application_education`) VALUES
('APP_1J6KH90H516AE49A0', 'USR_1J5SSJ78OD24BDF34', 'JOB_1J6KFMG4FCC9F9752', 'Approved', '', '2025-10-05 08:17:22', 77.14, '2025-10-03 07:58:37', 0, 'Masteral'),
('APP_1J6KTEM3E2ED7AC5E', 'USR_1J5SSJ78OD24BDF34', 'JOB_1J6KOGEI8AE6E86DE', 'Approved', 'SIGE PRE TAWAGAN MO Q BUKAS SAH MAY BITAW KA G', '2025-10-05 05:44:58', 75.00, '2025-10-03 11:31:26', 0, 'Masteral');

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
('ASK_1J6KH90H90AAF2573', 'APP_1J6KH90H516AE49A0', 'SKL_1J5BOAHOR18D08123', '2025-10-03 07:58:37'),
('ASK_1J6KH90HBF5FF25BE', 'APP_1J6KH90H516AE49A0', 'SKL_1J5B1B2JJ33509B84', '2025-10-03 07:58:37'),
('ASK_1J6KH90HC279862E9', 'APP_1J6KH90H516AE49A0', 'SKL_1J5B83SAA67C5E1CA', '2025-10-03 07:58:37'),
('ASK_1J6KTEM3H65667699', 'APP_1J6KTEM3E2ED7AC5E', 'SKL_1J5BOAHOR18D08123', '2025-10-03 11:31:26'),
('ASK_1J6KTEM3HF76E5D92', 'APP_1J6KTEM3E2ED7AC5E', 'SKL_1J5B1B2JJ33509B84', '2025-10-03 11:31:26'),
('ASK_1J6KTEM3IE9EDC389', 'APP_1J6KTEM3E2ED7AC5E', 'SKL_1J5B83SAA67C5E1CA', '2025-10-03 11:31:26');

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
  `job_image` varchar(255) DEFAULT NULL,
  `status` enum('Open','Suspended','Closed') NOT NULL DEFAULT 'Open',
  `moderation_status` enum('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  `moderation_reason` text DEFAULT NULL,
  `moderation_decided_at` datetime DEFAULT NULL,
  `moderation_decided_by` varchar(64) DEFAULT NULL,
  `accessibility_tags` varchar(255) DEFAULT NULL,
  `applicable_pwd_types` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `archived_at` datetime DEFAULT NULL
) ;

--
-- Dumping data for table `jobs`
--

INSERT INTO `jobs` (`job_id`, `employer_id`, `title`, `description`, `required_experience`, `required_education`, `required_skills_input`, `location_city`, `location_region`, `remote_option`, `employment_type`, `salary_currency`, `salary_min`, `salary_max`, `salary_period`, `job_image`, `status`, `moderation_status`, `moderation_reason`, `moderation_decided_at`, `moderation_decided_by`, `accessibility_tags`, `applicable_pwd_types`, `created_at`, `archived_at`) VALUES
('JOB_1J6IDOF2T3BFB7F78', 'USR_1J5STL1HNF2D40513', 'Graphic Designer', 'AU Graphic Designer (Remote)\r\n\r\nBackground\r\n\r\nWe’re PeoplePartners, and we’re hiring for one of our amazing Aussie clients in the education sector. They need a creative eye with pixel-perfect instincts to bring their brand to life. If you’re just starting out in your design career and want to level up with real campaigns and an international team—you’ll love this one.\r\n\r\nAbout the Role\r\n\r\nThis is a work-from-home role based in the Philippines, with a Monday to Friday schedule. You’ll be designing everything from eye-catching social graphics to professional brochures and internal templates—always with brand consistency and creative flair.\r\n\r\nYour Key Responsibilities:\r\n\r\nPrioritize briefs and manage your daily design queue\r\n\r\nDesign social media posts, Meta ads, and campaign graphics\r\n\r\nCreate flyers, brochures, and course material for print\r\n\r\nDevelop web assets: banners, icons, and imagery\r\n\r\nRetouch photos and prep presentations\r\n\r\nBonus if you can do short-form video or motion graphics!\r\n\r\nJob Requirements:\r\nAbout YOU\r\n\r\nYou’re creative, organized, and love making things look great.\r\n\r\nYou’ve got a sharp eye for detail and love juggling multiple briefs.\r\n\r\nYou stay up-to-date with design trends and love feedback that helps you grow.\r\n\r\nAbout YOUR Work History\r\n\r\nComfortable in Adobe Creative Cloud (Photoshop, InDesign, Illustrator).\r\n\r\nCanva know-how for quick design jobs.\r\n\r\nExperience with both print and digital assets.\r\n\r\nFamiliarity with Meta Business Suite, ActiveCampaign, or Microsoft 365 = a plus.\r\n\r\nAbout the Culture YOU Thrive In\r\n\r\nFeedback-friendly, growth-minded, and collaborative.\r\n\r\nFun and fast-paced, but chill when it counts.\r\n\r\nYou’re flexible, positive, and take pride in your work.\r\n\r\nKey Responsibilities YOU Can’t Wait to Do\r\n\r\nTurn a vague brief into a polished, on-brand asset.\r\n\r\nHelp shape the visual identity of a respected learning brand.\r\n\r\nBe a dependable creative partner for the marketing team.\r\n\r\nHow YOU Will Be Measured\r\n\r\nTimely delivery of assets.\r\n\r\nBrand consistency across designs.\r\n\r\nResponsiveness to feedback.\r\n\r\nContribution to campaign impact.\r\n\r\nWhen & Where YOU Will Work\r\n\r\nFull-time, Monday–Friday.\r\n\r\nDay-shift.\r\n\r\nWork-from-home (PH-based).\r\n\r\nSound like your next creative chapter? Apply now and start designing work you’ll be proud of!\r\n\r\nCompany Benefits:\r\nPermanent Work-from-home setup\r\n\r\nCompany-provided equipment\r\n\r\nSecondary Wi-Fi Modem\r\n\r\n21 Leave Credits Annually - Leave benefits begin on Day 1.\r\n\r\n100% conversion of UNUSED leave credits\r\n\r\nHMO on Day 1\r\n\r\n13th Month Pay\r\n\r\nMonthly Gift Voucher\r\n\r\nMilestone Tokens (Birthday/Anniversary/Christmas).\r\n\r\nA Life Beyond the Screen #WorkLifeBalance.\r\n\r\nActive employee engagements physically such as Christmas Party & Team Building, and virtual events such as town-hall with prizes.', 5, 'College', '70+ WPM Typing, Flexible Schedule, Team Player, Professional Attitude, Strong Communication, Adaptable / Quick Learner, Customer Support, JavaScript, PHP', 'Lipa City', 'Batangas', 'Work From Home', 'Full time', 'PHP', 10000, 100000, 'monthly', 'uploads/job_images/job_68de6da0d1e06.jpg', 'Open', 'Approved', NULL, NULL, NULL, 'Flexible Hours,Night Shift Option,Training Provided,Internet Allowance,Equipment Provided', NULL, '2025-10-02 12:18:40', NULL),
('JOB_1J6KAD0E300D04B6E', 'USR_1J5STL1HNF2D40513', 'Virtual Assistant (Remote)', 'Background\r\nWe’re looking for a reliable and detail-oriented Virtual Assistant to support a growing business with day-to-day tasks. This role is perfect for someone who enjoys working independently, staying organized, and helping teams succeed by keeping operations smooth.\r\n\r\nAbout the Role\r\nThis is a work-from-home role based in the Philippines. You will handle tasks like email management, calendar scheduling, online research, and light project coordination. You’ll be an important part of the team, making sure deadlines are met and processes run efficiently.\r\n\r\nYour Key Responsibilities:\r\n\r\nManage emails and respond to inquiries in a professional manner\r\n\r\nSchedule meetings, appointments, and organize calendars\r\n\r\nAssist with preparing reports, presentations, and spreadsheets\r\n\r\nConduct online research and gather data as needed\r\n\r\nSupport social media management by drafting content or scheduling posts\r\n\r\nPerform administrative tasks such as file organization and documentation\r\n\r\nJob Requirements – About YOU\r\n\r\nOrganized, detail-oriented, and able to multitask effectively\r\n\r\nStrong written and verbal communication skills\r\n\r\nTech-savvy and comfortable with common tools (Google Workspace, MS Office, or project management apps)\r\n\r\nReliable internet connection and ability to work independently\r\n\r\nA problem-solver who can adapt quickly to changing priorities\r\n\r\nAbout YOUR Work History\r\n\r\nPrevious experience as a Virtual Assistant, Admin Assistant, or Customer Service Rep is a plus\r\n\r\nExperience with social media scheduling or light graphic design (Canva) is an advantage\r\n\r\nOpen to entry-level applicants who are fast learners and willing to train\r\n\r\nAbout the Culture YOU Thrive In\r\n\r\nCollaborative and supportive, where feedback is encouraged\r\n\r\nGrowth-oriented and values proactive team players\r\n\r\nFlexible and focused on results rather than strict processes\r\n\r\nHow YOU Will Be Measured\r\n\r\nAccuracy and timeliness of completed tasks\r\n\r\nResponsiveness to communication\r\n\r\nAbility to manage workload with minimal supervision\r\n\r\nWhen & Where YOU Will Work\r\n\r\nFull-time, Monday to Friday\r\n\r\nDay shift or flexible schedule depending on client needs\r\n\r\nWork-from-home (Philippines-based)\r\n\r\nCompany Benefits:\r\n\r\nPermanent remote setup\r\n\r\n13th Month Pay\r\n\r\nPaid vacation and sick leave credits\r\n\r\nHMO coverage upon regularization\r\n\r\nTraining and career development opportunities\r\n\r\nInternet allowance or company-provided tools (where applicable)', 5, '', '70+ WPM Typing, Flexible Schedule, Team Player, Professional Attitude, Strong Communication, Adaptable / Quick Learner, Customer Support', 'Marikina', 'Manila', 'Work From Home', 'Full time', 'PHP', 50000, 80000, 'monthly', 'uploads/job_images/job_68df6604857aa.png', 'Open', 'Approved', NULL, NULL, NULL, 'Flexible Hours,Night Shift Option', 'Physical disability', '2025-10-03 05:58:28', NULL),
('JOB_1J6KAHD8636FA98A2', 'USR_1J5STL1HNF2D40513', 'Data Entry Clerk (Remote)', 'Background\r\nWe are seeking a detail-oriented Data Entry Clerk to help maintain and update company databases. This role is perfect for individuals who enjoy working with information, have excellent typing skills, and are looking for a stable work-from-home opportunity.\r\n\r\nAbout the Role\r\nThis position requires accuracy and efficiency in entering data into spreadsheets and systems. You will review source documents, check for errors, and ensure records remain updated.\r\n\r\nYour Key Responsibilities:\r\n\r\nEnter and update data into company systems\r\n\r\nReview documents for accuracy and completeness\r\n\r\nMaintain confidentiality of sensitive information\r\n\r\nGenerate reports and summaries when required\r\n\r\nPerform quality checks on data entries\r\n\r\nJob Requirements – About YOU\r\n\r\nFast and accurate typing skills (at least 40 WPM)\r\n\r\nStrong attention to detail\r\n\r\nAbility to work independently with minimal supervision\r\n\r\nFamiliarity with MS Excel, Google Sheets, or database systems\r\n\r\nReliable internet connection\r\n\r\nAbout YOUR Work History\r\n\r\nPrevious experience in data entry or clerical work is a plus\r\n\r\nFresh graduates are welcome to apply if confident in computer skills\r\n\r\nAbout the Culture YOU Thrive In\r\n\r\nAccuracy and reliability are valued\r\n\r\nTeam-oriented, but with independence in daily tasks\r\n\r\nGrowth-minded, with opportunities for training\r\n\r\nHow YOU Will Be Measured\r\n\r\nAccuracy of data entries\r\n\r\nTimeliness of work completion\r\n\r\nAbility to handle assigned workload consistently\r\n\r\nWhen & Where YOU Will Work\r\n\r\nFull-time or part-time available\r\n\r\nWork-from-home, Philippines-based\r\n\r\nFlexible schedule may be offered depending on project\r\n\r\nCompany Benefits:\r\n\r\nPermanent work-from-home setup\r\n\r\n13th Month Pay\r\n\r\nPaid leave credits after probationary period\r\n\r\nHMO coverage upon regularization\r\n\r\nTraining and career growth opportunities', 0, '', 'Flexible Schedule, Team Player, Professional Attitude, Strong Communication, Adaptable / Quick Learner, Customer Support, JavaScript, PHP', 'Lipa City', 'Batangas', 'Work From Home', 'Full time', 'PHP', 20000, 60000, 'monthly', 'uploads/job_images/job_68df6694b5094.png', 'Open', 'Approved', NULL, NULL, NULL, 'Flexible Hours', 'Communication disorder', '2025-10-03 06:00:52', NULL),
('JOB_1J6KAMDI1A25FB3DF', 'USR_1J5STL1HNF2D40513', 'Virtual Recruiter (Remote)', 'Background\r\nWe are looking for a motivated Virtual Recruiter to help source and screen candidates for various job openings. This role is perfect for someone who enjoys talking to people, evaluating skills, and matching the right talent to the right opportunity.\r\n\r\nAbout the Role\r\nAs a Virtual Recruiter, you will handle the full recruitment cycle online—from posting job ads and reviewing applications to conducting initial interviews. You will collaborate with hiring managers and help build strong teams.\r\n\r\nYour Key Responsibilities:\r\n\r\nPost job ads on online platforms and social media\r\n\r\nSource and screen applicants based on job requirements\r\n\r\nConduct initial interviews via video calls or phone\r\n\r\nCoordinate with hiring managers for shortlists and feedback\r\n\r\nGuide candidates through the hiring process and answer inquiries\r\n\r\nMaintain candidate records in databases or applicant tracking systems\r\n\r\nJob Requirements – About YOU\r\n\r\nStrong communication and interpersonal skills\r\n\r\nComfortable using online recruitment platforms (JobStreet, LinkedIn, etc.)\r\n\r\nOrganized, detail-oriented, and able to manage multiple job openings\r\n\r\nAbility to work independently in a remote environment\r\n\r\nReliable internet connection and a professional workspace\r\n\r\nAbout YOUR Work History\r\n\r\nPrevious experience in recruitment, HR, or talent acquisition is preferred\r\n\r\nFamiliarity with applicant tracking systems is a plus\r\n\r\nFresh graduates with HR or Psychology backgrounds are welcome to apply\r\n\r\nAbout the Culture YOU Thrive In\r\n\r\nPeople-focused and collaborative\r\n\r\nResults-driven but supportive\r\n\r\nEncourages initiative and continuous learning\r\n\r\nHow YOU Will Be Measured\r\n\r\nQuality and number of candidates sourced\r\n\r\nTimeliness in filling job openings\r\n\r\nCandidate experience and feedback\r\n\r\nWhen & Where YOU Will Work\r\n\r\nFull-time, remote (Philippines-based)\r\n\r\nFlexible hours, depending on recruitment needs\r\n\r\nCompany Benefits:\r\n\r\nWork-from-home setup\r\n\r\nHMO after probation\r\n\r\nPaid leave credits\r\n\r\n13th Month Pay\r\n\r\nTraining and career development opportunities', 2, 'High School', 'Flexible Schedule, Professional Attitude, Strong Communication, Adaptable / Quick Learner, Malupet, Siraulo', 'Batangas City', 'Batangas', 'Work From Home', 'Part time', 'PHP', 70000, 100000, 'monthly', NULL, 'Open', 'Approved', NULL, NULL, NULL, 'Flexible Hours,Night Shift Option,Training Provided,Internet Allowance', 'Physical disability', '2025-10-03 06:03:36', NULL),
('JOB_1J6KAP1OL26669B73', 'USR_1J5STL1HNF2D40513', 'Call Center Agent (Voice/Non-Voice)', 'Background\r\nOur client is expanding and needs motivated Call Center Agents for both voice and non-voice accounts.\r\n\r\nAbout the Role\r\nYou will answer customer calls or respond to chats and emails, assisting with billing, product questions, and troubleshooting.\r\n\r\nYour Key Responsibilities:\r\n\r\nHandle inbound calls, emails, or chat support\r\n\r\nFollow call scripts and company processes\r\n\r\nEscalate complex issues to senior agents\r\n\r\nEnsure customer satisfaction in every interaction\r\n\r\nJob Requirements – About YOU\r\n\r\nClear speaking voice and good English skills (for voice accounts)\r\n\r\nStrong written skills (for non-voice accounts)\r\n\r\nWillingness to work on shifting schedules\r\n\r\nAbout YOUR Work History\r\n\r\nExperience in BPO is preferred but not required\r\n\r\nAbout the Culture YOU Thrive In\r\n\r\nPerformance-driven but supportive\r\n\r\nFast-paced and goal-oriented\r\n\r\nHow YOU Will Be Measured\r\n\r\nQuality assurance scores\r\n\r\nAttendance and punctuality\r\n\r\nCustomer satisfaction feedback\r\n\r\nWhen & Where YOU Will Work\r\n\r\nOffice-based or remote depending on account\r\n\r\nShifting schedules\r\n\r\nCompany Benefits:\r\n\r\nCompetitive salary + allowances\r\n\r\nHMO and life insurance\r\n\r\nPaid leave credits\r\n\r\nIncentives for performance', 1, 'High School', '70+ WPM Typing, Flexible Schedule, Strong Communication, Mabigat kamay, 6 footer, makunat mukha', 'Quezon City', 'Quezon', 'Work From Home', 'Part time', 'PHP', 60000, 90000, 'monthly', 'uploads/job_images/job_68df678f1e873.jpg', 'Open', 'Approved', NULL, NULL, NULL, 'Flexible Hours', 'Hearing disability', '2025-10-03 06:05:03', NULL),
('JOB_1J6KFMG4FCC9F9752', 'USR_1J5STL1HNF2D40513', 'Content Writer', 'Background\r\nWe are seeking a creative Content Writer to produce engaging written materials.\r\n\r\nAbout the Role\r\nYou will write blogs, articles, and web content that are accurate, clear, and SEO-friendly.\r\n\r\nYour Key Responsibilities:\r\n\r\nWrite, edit, and proofread content\r\n\r\nResearch topics\r\n\r\nFollow SEO best practices\r\n\r\nJob Requirements – About YOU\r\n\r\nStrong writing skills in English\r\n\r\nAbility to meet deadlines\r\n\r\nAbout YOUR Work History\r\n\r\nWriting experience is a plus\r\n\r\nWhen & Where YOU Will Work\r\n\r\nRemote, flexible schedule\r\n\r\nCompany Benefits:\r\n\r\nWFH setup\r\n\r\nPaid leave credits\r\n\r\n13th Month Pay\r\n\r\n10. IT Support Specialist\r\n\r\nBackground\r\nWe are hiring an IT Support Specialist to assist employees and clients with technical issues.\r\n\r\nAbout the Role\r\nYou will troubleshoot, maintain, and support hardware and software systems.\r\n\r\nYour Key Responsibilities:\r\n\r\nProvide tech support via phone, email, or chat\r\n\r\nInstall and configure software/hardware\r\n\r\nTroubleshoot network and system issues\r\n\r\nJob Requirements – About YOU\r\n\r\nStrong IT knowledge\r\n\r\nGood problem-solving skills\r\n\r\nAbout YOUR Work History\r\n\r\nExperience in IT support is a plus\r\n\r\nWhen & Where YOU Will Work\r\n\r\nOffice-based or remote depending on employer\r\n\r\nCompany Benefits:\r\n\r\nCompetitive salary\r\n\r\nHMO\r\n\r\nPaid leave', 0, 'Elementary', '70+ WPM Typing, Flexible Schedule, Professional Attitude, Adaptable / Quick Learner, Customer Support, PHP, Javascript', 'Lipa City', 'Batangas', 'Work From Home', 'Full time', 'PHP', 30000, 80000, 'monthly', 'uploads/job_images/job_68df7bb665056.jpg', 'Open', 'Approved', NULL, NULL, NULL, 'Flexible Hours,Night Shift Option,Training Provided,Internet Allowance,Equipment Provided', 'Physical disability', '2025-10-03 07:31:02', NULL),
('JOB_1J6KOGEI8AE6E86DE', 'USR_1J5STL1HNF2D40513', 'Virtual Project Manager (Remote)', 'Background\r\nWe’re seeking a detail-oriented Virtual Project Manager to oversee projects for clients and internal teams. This role is ideal for someone who enjoys planning, organizing, and making sure deadlines are met while coordinating with people online.\r\n\r\nAbout the Role\r\nAs a Virtual Project Manager, you will be responsible for managing projects from start to finish. You’ll communicate with team members, track progress, and ensure that tasks are delivered on time and within budget.\r\n\r\nYour Key Responsibilities:\r\n\r\nPlan and manage project timelines, tasks, and deliverables\r\n\r\nCoordinate with team members through online tools\r\n\r\nMonitor progress and adjust schedules when needed\r\n\r\nPrepare project reports and updates for stakeholders\r\n\r\nEnsure projects meet quality standards and deadlines\r\n\r\nJob Requirements – About YOU\r\n\r\nStrong leadership and organizational skills\r\n\r\nExcellent communication and problem-solving abilities\r\n\r\nComfortable using project management tools (Trello, Asana, ClickUp, Jira)\r\n\r\nAble to work independently and manage multiple projects\r\n\r\nStable internet connection and professional remote setup\r\n\r\nAbout YOUR Work History\r\n\r\nExperience in project management or team leadership is preferred\r\n\r\nFamiliarity with agile or other project management methodologies is a plus\r\n\r\nAbout the Culture YOU Thrive In\r\n\r\nCollaborative and goal-driven\r\n\r\nFlexible, feedback-friendly environment\r\n\r\nValues accountability and teamwork\r\n\r\nHow YOU Will Be Measured\r\n\r\nOn-time delivery of projects\r\n\r\nQuality and accuracy of completed tasks\r\n\r\nTeam and client satisfaction\r\n\r\nWhen & Where YOU Will Work\r\n\r\nFull-time, remote (Philippines-based)\r\n\r\nFlexible work hours depending on client/project needs\r\n\r\nCompany Benefits:\r\n\r\nPermanent remote setup\r\n\r\nPaid leave credits\r\n\r\nHMO upon regularization\r\n\r\n13th Month Pay\r\n\r\nTraining and career development opportunities', 0, 'Senior High School', 'Flexible Schedule, Team Player, Professional Attitude, Strong Communication, Adaptable / Quick Learner, Customer Support, JavaScript, PHP', 'Lipa City', 'Batangas', 'Work From Home', 'Full time', 'PHP', 90000, 120000, 'monthly', NULL, 'Open', 'Approved', NULL, NULL, NULL, 'Flexible Hours,Night Shift Option,Training Provided', 'Physical disability', '2025-10-03 10:05:01', NULL),
('JOB_1J6KU03VCCC93CAD3', 'USR_1J5STL1HNF2D40513', 'Virtual Sales Representative (Remote)', 'Background\r\nWe’re looking for a motivated Virtual Sales Representative to join our growing team. If you enjoy building relationships with clients, meeting sales goals, and working from the comfort of your home, this role is for you.\r\n\r\nAbout the Role\r\nAs a Virtual Sales Representative, you will promote products or services to potential customers through online platforms, phone calls, and virtual meetings. You will nurture client relationships, handle inquiries, and close deals while maintaining excellent customer satisfaction.\r\n\r\nYour Key Responsibilities:\r\n\r\nReach out to prospective clients via phone, email, and online meetings\r\n\r\nPresent products and services in a clear and engaging manner\r\n\r\nNegotiate and close sales deals to meet monthly targets\r\n\r\nBuild and maintain strong client relationships\r\n\r\nMaintain accurate records of sales activities and customer interactions\r\n\r\nJob Requirements – About YOU\r\n\r\nStrong communication and persuasive skills\r\n\r\nGoal-oriented and self-motivated\r\n\r\nComfortable using CRM tools and online platforms\r\n\r\nAbility to work independently with minimal supervision\r\n\r\nReliable internet connection and professional remote setup\r\n\r\nAbout YOUR Work History\r\n\r\nPrevious experience in sales, telesales, or customer service is a plus\r\n\r\nFamiliarity with online selling platforms or digital marketing is an advantage\r\n\r\nAbout the Culture YOU Thrive In\r\n\r\nPerformance-driven but supportive\r\n\r\nEncourages continuous learning and professional growth\r\n\r\nRecognizes and rewards achievements\r\n\r\nHow YOU Will Be Measured\r\n\r\nNumber of deals closed and sales targets achieved\r\n\r\nCustomer satisfaction and retention\r\n\r\nConsistency in communication and follow-ups\r\n\r\nWhen & Where YOU Will Work\r\n\r\nFull-time, remote (Philippines-based)\r\n\r\nMonday to Friday schedule, with flexible hours based on client needs\r\n\r\nCompany Benefits:\r\n\r\nPermanent work-from-home setup\r\n\r\nCompetitive base salary plus commissions\r\n\r\nHMO coverage after probation\r\n\r\nPaid leave credits and 13th Month Pay\r\n\r\nCareer growth opportunities and sales training', 0, 'High School', '70+ WPM Typing, Flexible Schedule, Team Player, Professional Attitude, Strong Communication, Customer Support, JavaScript, PHP', 'Lipa City', 'Batangas', 'Work From Home', 'Full time', 'PHP', 50000, 60000, 'monthly', 'uploads/job_images/job_68dfb649acf13.png', 'Open', 'Approved', NULL, NULL, NULL, 'Flexible Hours,Night Shift Option,Training Provided,Internet Allowance,Equipment Provided', 'Physical disability', '2025-10-03 11:40:57', NULL),
('JOB_1J745IBUEB8BF98EA', 'USR_1J5STL1HNF2D40513', 'Online Singer', 'Singer / Vocal Performer\r\n\r\nBackground\r\nWe’re looking for a talented Singer to perform at events, record music, and bring songs to life through powerful vocals and stage presence. This role is perfect for someone who loves music, enjoys performing, and can connect with an audience through their voice.\r\n\r\nAbout the Role\r\nAs a Singer, you will rehearse and perform live or recorded songs. You may work with a band, production team, or music studio. Your performance can include concerts, private events, commercials, or recording projects.\r\n\r\nYour Key Responsibilities:\r\n\r\nPerform songs live or in studio recordings\r\n\r\nRehearse regularly to maintain vocal strength and quality\r\n\r\nWork with musical directors, producers, or other performers\r\n\r\nMemorize lyrics and melodies accurately\r\n\r\nInterpret songs with emotion and stage presence\r\n\r\nParticipate in sound checks, promotions, or event rehearsals\r\n\r\nFollow performance schedules and call times\r\n\r\nJob Requirements – About YOU\r\n\r\nStrong and stable singing voice with good pitch and tone\r\n\r\nConfident in performing in front of audiences or in a studio\r\n\r\nAble to memorize lyrics and adapt to different music genres\r\n\r\nOpen to direction and collaboration\r\n\r\nPhysically fit to maintain vocal stamina during performances\r\n\r\nAbout YOUR Work History\r\n\r\nExperience performing in events, competitions, choirs, or bands is an advantage\r\n\r\nRecording or entertainment background is a plus\r\n\r\nNew talents who can showcase vocal skills are welcome to apply\r\n\r\nAbout the Culture YOU Thrive In\r\n\r\nCreative and artistic\r\n\r\nPassionate about music and collaboration\r\n\r\nEncourages personal style and growth\r\n\r\nHow YOU Will Be Measured\r\n\r\nVocal performance quality and consistency\r\n\r\nAudience or client feedback\r\n\r\nProfessionalism and punctuality\r\n\r\nAbility to adapt to different songs and performance styles\r\n\r\nWhen & Where YOU Will Work\r\n\r\nFlexible schedule depending on bookings, events, or projects\r\n\r\nMay involve travel or rehearsals\r\n\r\nOn-site performances or studio sessions\r\n\r\nCompany Benefits:\r\n\r\nCompetitive performance fees or salary (depending on contract)\r\n\r\nExposure to live events and professional productions\r\n\r\nTraining, coaching, or development opportunities\r\n\r\nTravel and accommodation covered for booked events (if applicable)', 3, 'Elementary', 'Flexible Schedule, Singer, Professional', 'Batangas City', 'Batangas', 'Work From Home', 'Full time', 'PHP', 50000, 100000, 'monthly', NULL, 'Open', 'Approved', NULL, '2025-10-09 17:56:50', 'USR_1J56TV15M1F2FE280', 'Training Provided', 'Speech impairment', '2025-10-09 09:41:52', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `job_applicable_pwd_types`
--

CREATE TABLE `job_applicable_pwd_types` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `job_id` varchar(40) NOT NULL,
  `pwd_type` varchar(120) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `job_applicable_pwd_types`
--

INSERT INTO `job_applicable_pwd_types` (`id`, `job_id`, `pwd_type`, `created_at`) VALUES
(1, 'JOB_1J6KAD0E300D04B6E', 'Physical disability', '2025-10-04 13:32:12'),
(2, 'JOB_1J6KAHD8636FA98A2', 'Communication disorder', '2025-10-04 13:32:12'),
(3, 'JOB_1J6KAMDI1A25FB3DF', 'Physical disability', '2025-10-04 13:32:12'),
(4, 'JOB_1J6KAP1OL26669B73', 'Hearing disability', '2025-10-04 13:32:12'),
(5, 'JOB_1J6KFMG4FCC9F9752', 'Physical disability', '2025-10-04 13:32:12'),
(6, 'JOB_1J6KOGEI8AE6E86DE', 'Physical disability', '2025-10-04 13:32:12'),
(7, 'JOB_1J6KU03VCCC93CAD3', 'Physical disability', '2025-10-04 13:32:12'),
(8, 'JOB_1J745IBUEB8BF98EA', 'Speech impairment', '2025-10-09 09:41:52');

-- --------------------------------------------------------

--
-- Table structure for table `job_reports`
--

CREATE TABLE `job_reports` (
  `report_id` varchar(40) NOT NULL,
  `job_id` varchar(40) NOT NULL,
  `reporter_user_id` varchar(40) DEFAULT NULL,
  `reason` varchar(100) NOT NULL,
  `details` text DEFAULT NULL,
  `status` enum('Open','Resolved') DEFAULT 'Open',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `job_reports`
--

INSERT INTO `job_reports` (`report_id`, `job_id`, `reporter_user_id`, `reason`, `details`, `status`, `created_at`) VALUES
('RPT_1J6NK69BCF5CE3545', 'JOB_1J6KU03VCCC93CAD3', 'USR_1J5SSJ78OD24BDF34', 'Inappropriate Content', 'hahaha', 'Resolved', '2025-10-04 12:47:17');

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
('JSK_1J6IDOF3C5EB248A9', 'JOB_1J6IDOF2T3BFB7F78', 'SKL_1J5GAF24GAB7A1B8C', '2025-10-02 12:18:40'),
('JSK_1J6IDOF3F6471E93D', 'JOB_1J6IDOF2T3BFB7F78', 'SKL_1J5OD19IA42500B94', '2025-10-02 12:18:40'),
('JSK_1J6IDOF3HD8AE96A3', 'JOB_1J6IDOF2T3BFB7F78', 'SKL_1J5BOAHOR18D08123', '2025-10-02 12:18:40'),
('JSK_1J6IDOF3K8D966029', 'JOB_1J6IDOF2T3BFB7F78', 'SKL_1J5GAF24G009B7ED8', '2025-10-02 12:18:40'),
('JSK_1J6IDOF3L16DFC74C', 'JOB_1J6IDOF2T3BFB7F78', 'SKL_1J5B1B2JJ33509B84', '2025-10-02 12:18:40'),
('JSK_1J6IDOF3NA0340CE3', 'JOB_1J6IDOF2T3BFB7F78', 'SKL_1J5B83SAA67C5E1CA', '2025-10-02 12:18:40'),
('JSK_1J6IDOF3P47FDB970', 'JOB_1J6IDOF2T3BFB7F78', 'SKL_1J5JDC38S834D3E3A', '2025-10-02 12:18:40'),
('JSK_1J6IDOF3TAC953FED', 'JOB_1J6IDOF2T3BFB7F78', 'SKL_1J5JDC38UFB050C2D', '2025-10-02 12:18:40'),
('JSK_1J6IDOF3V5394F488', 'JOB_1J6IDOF2T3BFB7F78', 'SKL_1J5JDC38O90BB7B46', '2025-10-02 12:18:40'),
('JSK_1J6KAD0EN11580C2B', 'JOB_1J6KAD0E300D04B6E', 'SKL_1J5OD19IA42500B94', '2025-10-03 05:58:28'),
('JSK_1J6KAD0ENE1684DFD', 'JOB_1J6KAD0E300D04B6E', 'SKL_1J5GAF24GAB7A1B8C', '2025-10-03 05:58:28'),
('JSK_1J6KAD0EOB1F3C665', 'JOB_1J6KAD0E300D04B6E', 'SKL_1J5BOAHOR18D08123', '2025-10-03 05:58:28'),
('JSK_1J6KAD0EPA2C189A6', 'JOB_1J6KAD0E300D04B6E', 'SKL_1J5GAF24G009B7ED8', '2025-10-03 05:58:28'),
('JSK_1J6KAD0EQ7CA22E72', 'JOB_1J6KAD0E300D04B6E', 'SKL_1J5JDC38UFB050C2D', '2025-10-03 05:58:28'),
('JSK_1J6KAD0EQAA76D42F', 'JOB_1J6KAD0E300D04B6E', 'SKL_1J5JDC38S834D3E3A', '2025-10-03 05:58:28'),
('JSK_1J6KAD0ER87C7A40C', 'JOB_1J6KAD0E300D04B6E', 'SKL_1J5JDC38O90BB7B46', '2025-10-03 05:58:28'),
('JSK_1J6KAHD881FB7B140', 'JOB_1J6KAHD8636FA98A2', 'SKL_1J5OD19IA42500B94', '2025-10-03 06:00:52'),
('JSK_1J6KAHD894C52FBFA', 'JOB_1J6KAHD8636FA98A2', 'SKL_1J5BOAHOR18D08123', '2025-10-03 06:00:52'),
('JSK_1J6KAHD8A15366C48', 'JOB_1J6KAHD8636FA98A2', 'SKL_1J5B1B2JJ33509B84', '2025-10-03 06:00:52'),
('JSK_1J6KAHD8A9EC9D735', 'JOB_1J6KAHD8636FA98A2', 'SKL_1J5GAF24G009B7ED8', '2025-10-03 06:00:52'),
('JSK_1J6KAHD8B85C5B9A7', 'JOB_1J6KAHD8636FA98A2', 'SKL_1J5B83SAA67C5E1CA', '2025-10-03 06:00:52'),
('JSK_1J6KAHD8BADD28206', 'JOB_1J6KAHD8636FA98A2', 'SKL_1J5JDC38S834D3E3A', '2025-10-03 06:00:52'),
('JSK_1J6KAHD8C15EA98D6', 'JOB_1J6KAHD8636FA98A2', 'SKL_1J5JDC38UFB050C2D', '2025-10-03 06:00:52'),
('JSK_1J6KAHD8CCAA37B15', 'JOB_1J6KAHD8636FA98A2', 'SKL_1J5JDC38O90BB7B46', '2025-10-03 06:00:52'),
('JSK_1J6KAMDI44CD82304', 'JOB_1J6KAMDI1A25FB3DF', 'SKL_1J5OD19IA42500B94', '2025-10-03 06:03:36'),
('JSK_1J6KAMDI5761E2F21', 'JOB_1J6KAMDI1A25FB3DF', 'SKL_1J5GAF24G009B7ED8', '2025-10-03 06:03:36'),
('JSK_1J6KAMDI5B996E0DE', 'JOB_1J6KAMDI1A25FB3DF', 'SKL_1J5JDC38S834D3E3A', '2025-10-03 06:03:36'),
('JSK_1J6KAMDI5E93EB1D7', 'JOB_1J6KAMDI1A25FB3DF', 'SKL_1J5JDC38UFB050C2D', '2025-10-03 06:03:36'),
('JSK_1J6KAMDI659A66A13', 'JOB_1J6KAMDI1A25FB3DF', 'SKL_1J6KAMDI42CB3D785', '2025-10-03 06:03:36'),
('JSK_1J6KAMDI6823C259B', 'JOB_1J6KAMDI1A25FB3DF', 'SKL_1J6KAMDI3ADD79CB4', '2025-10-03 06:03:36'),
('JSK_1J6KAP1ON362B5571', 'JOB_1J6KAP1OL26669B73', 'SKL_1J5SU29CC09F182CE', '2025-10-03 06:05:03'),
('JSK_1J6KAP1OO44C0C145', 'JOB_1J6KAP1OL26669B73', 'SKL_1J5GAF24G009B7ED8', '2025-10-03 06:05:03'),
('JSK_1J6KAP1OO672CDFCA', 'JOB_1J6KAP1OL26669B73', 'SKL_1J5GAF24GAB7A1B8C', '2025-10-03 06:05:03'),
('JSK_1J6KAP1OP65D6B770', 'JOB_1J6KAP1OL26669B73', 'SKL_1J5SU29CCD93FBE25', '2025-10-03 06:05:03'),
('JSK_1J6KAP1OP82EABE44', 'JOB_1J6KAP1OL26669B73', 'SKL_1J5SU29CB4D297EA6', '2025-10-03 06:05:03'),
('JSK_1J6KAP1OP85A14306', 'JOB_1J6KAP1OL26669B73', 'SKL_1J5JDC38UFB050C2D', '2025-10-03 06:05:03'),
('JSK_1J6KFMG4OFFC59C45', 'JOB_1J6KFMG4FCC9F9752', 'SKL_1J5GAF24GAB7A1B8C', '2025-10-03 07:31:02'),
('JSK_1J6KFMG4U9961AA3A', 'JOB_1J6KFMG4FCC9F9752', 'SKL_1J5OD19IA42500B94', '2025-10-03 07:31:02'),
('JSK_1J6KFMG4VC0E9BD8F', 'JOB_1J6KFMG4FCC9F9752', 'SKL_1J5BOAHOR18D08123', '2025-10-03 07:31:02'),
('JSK_1J6KFMG539BE58206', 'JOB_1J6KFMG4FCC9F9752', 'SKL_1J5GAF24G009B7ED8', '2025-10-03 07:31:02'),
('JSK_1J6KFMG57008D373F', 'JOB_1J6KFMG4FCC9F9752', 'SKL_1J5B1B2JJ33509B84', '2025-10-03 07:31:02'),
('JSK_1J6KFMG5B97825294', 'JOB_1J6KFMG4FCC9F9752', 'SKL_1J5B83SAA67C5E1CA', '2025-10-03 07:31:02'),
('JSK_1J6KFMG5F263724D1', 'JOB_1J6KFMG4FCC9F9752', 'SKL_1J5JDC38S834D3E3A', '2025-10-03 07:31:02'),
('JSK_1J6KOGEIG003CC715', 'JOB_1J6KOGEI8AE6E86DE', 'SKL_1J5BOAHOR18D08123', '2025-10-03 10:05:01'),
('JSK_1J6KOGEIG1BD6B824', 'JOB_1J6KOGEI8AE6E86DE', 'SKL_1J5OD19IA42500B94', '2025-10-03 10:05:01'),
('JSK_1J6KOGEIH854BE62F', 'JOB_1J6KOGEI8AE6E86DE', 'SKL_1J5GAF24G009B7ED8', '2025-10-03 10:05:01'),
('JSK_1J6KOGEIIE0D01DEF', 'JOB_1J6KOGEI8AE6E86DE', 'SKL_1J5B1B2JJ33509B84', '2025-10-03 10:05:01'),
('JSK_1J6KOGEIJ372C0CBF', 'JOB_1J6KOGEI8AE6E86DE', 'SKL_1J5B83SAA67C5E1CA', '2025-10-03 10:05:01'),
('JSK_1J6KOGEIJ86542FE2', 'JOB_1J6KOGEI8AE6E86DE', 'SKL_1J5JDC38S834D3E3A', '2025-10-03 10:05:01'),
('JSK_1J6KOGEIK2A547320', 'JOB_1J6KOGEI8AE6E86DE', 'SKL_1J5JDC38UFB050C2D', '2025-10-03 10:05:01'),
('JSK_1J6KOGEIL46333A62', 'JOB_1J6KOGEI8AE6E86DE', 'SKL_1J5JDC38O90BB7B46', '2025-10-03 10:05:01'),
('JSK_1J6KU03VGCDCA9A15', 'JOB_1J6KU03VCCC93CAD3', 'SKL_1J5GAF24GAB7A1B8C', '2025-10-03 11:40:57'),
('JSK_1J6KU03VH0D49DFD4', 'JOB_1J6KU03VCCC93CAD3', 'SKL_1J5GAF24G009B7ED8', '2025-10-03 11:40:57'),
('JSK_1J6KU03VH38F7CDCE', 'JOB_1J6KU03VCCC93CAD3', 'SKL_1J5BOAHOR18D08123', '2025-10-03 11:40:57'),
('JSK_1J6KU03VIBE2DE4D1', 'JOB_1J6KU03VCCC93CAD3', 'SKL_1J5B1B2JJ33509B84', '2025-10-03 11:40:57'),
('JSK_1J6KU03VJ5503DAD9', 'JOB_1J6KU03VCCC93CAD3', 'SKL_1J5JDC38S834D3E3A', '2025-10-03 11:40:57'),
('JSK_1J6KU03VJ8C73C296', 'JOB_1J6KU03VCCC93CAD3', 'SKL_1J5B83SAA67C5E1CA', '2025-10-03 11:40:57'),
('JSK_1J6KU03VK406B09E9', 'JOB_1J6KU03VCCC93CAD3', 'SKL_1J5JDC38O90BB7B46', '2025-10-03 11:40:57'),
('JSK_1J6KU03VK440AA35D', 'JOB_1J6KU03VCCC93CAD3', 'SKL_1J5JDC38UFB050C2D', '2025-10-03 11:40:57'),
('JSK_1J745IBUL688500F6', 'JOB_1J745IBUEB8BF98EA', 'SKL_1J5GAF24G009B7ED8', '2025-10-09 09:41:52'),
('JSK_1J745IBUM301A1359', 'JOB_1J745IBUEB8BF98EA', 'SKL_1J6KDD7S949FAAE0D', '2025-10-09 09:41:52'),
('JSK_1J745IBUN9FBFCFD3', 'JOB_1J745IBUEB8BF98EA', 'SKL_1J745IBUK849A177E', '2025-10-09 09:41:52');

-- --------------------------------------------------------

--
-- Table structure for table `search_history`
--

CREATE TABLE `search_history` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` varchar(32) NOT NULL,
  `query` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `search_history`
--

INSERT INTO `search_history` (`id`, `user_id`, `query`, `created_at`) VALUES
(124, 'USR_1J6PJ6BSSEB2C4CDA', 'Graphic Designer', '2025-10-05 07:11:04');

-- --------------------------------------------------------

--
-- Table structure for table `skills`
--

CREATE TABLE `skills` (
  `skill_id` varchar(40) NOT NULL,
  `name` varchar(120) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `skills`
--

INSERT INTO `skills` (`skill_id`, `name`, `is_active`) VALUES
('SKL_1J56TV17J49F91DA1', 'none', 1),
('SKL_1J56U59082963C9AB', '50 wpm', 1),
('SKL_1J56U5909C3D51800', 'Friendly', 1),
('SKL_1J5B1B2JH147C43F1', 'HTML', 1),
('SKL_1J5B1B2JI2BF5B8B9', 'CSS', 1),
('SKL_1J5B1B2JJ33509B84', 'JavaScript', 1),
('SKL_1J5B83SA90FE0FEE7', 'Teamwork', 1),
('SKL_1J5B83SA93F55C7F9', 'Communication', 1),
('SKL_1J5B83SAA16AA05D2', 'Bootstrap', 1),
('SKL_1J5B83SAA5491B4B8', 'Time Management', 1),
('SKL_1J5B83SAA67C5E1CA', 'PHP', 1),
('SKL_1J5B83SAA769B70BC', 'Accessibility', 1),
('SKL_1J5BOAHOR18D08123', 'Customer Support', 1),
('SKL_1J5DKU5CT019E9663', 'Python', 1),
('SKL_1J5DKU5CUC9E48B63', 'C#', 1),
('SKL_1J5DKU5CV4E041A8A', 'SQL', 1),
('SKL_1J5DKU5CV979368C1', 'Java', 1),
('SKL_1J5DKU5D0A19BAEBD', 'HTML/CSS', 1),
('SKL_1J5DKU5D16AA94CA6', 'React', 1),
('SKL_1J5DKUUN3EDF5DCF0', 'Laravel', 1),
('SKL_1J5DKUUN41B422A0E', 'Django', 1),
('SKL_1J5DKUUN4839D1F73', 'Git', 1),
('SKL_1J5GADQVE2C60B6AA', 'Strong', 1),
('SKL_1J5GAF24G009B7ED8', 'Flexible Schedule', 1),
('SKL_1J5GAF24GAB7A1B8C', '70+ WPM Typing', 1),
('SKL_1J5JDC38O90BB7B46', 'Team Player', 1),
('SKL_1J5JDC38S834D3E3A', 'Professional Attitude', 1),
('SKL_1J5JDC38UFB050C2D', 'Strong Communication', 1),
('SKL_1J5JDC391C569BD8D', 'Hotdog', 1),
('SKL_1J5JDC3945E06F9AC', 'Itlog', 1),
('SKL_1J5JDC3967C8D33E5', 'Sabaw', 1),
('SKL_1J5OD19IA42500B94', 'Adaptable / Quick Learner', 1),
('SKL_1J5OD19IADC27B215', 'Cisco', 1),
('SKL_1J5OD19IB0BD226D1', 'Database', 1),
('SKL_1J5OD19IBF569BFBA', 'Math God', 1),
('SKL_1J5SU29CB4D297EA6', 'Mabigat kamay', 1),
('SKL_1J5SU29CC09F182CE', '6 footer', 1),
('SKL_1J5SU29CCD93FBE25', 'makunat mukha', 1),
('SKL_1J6KAMDI3ADD79CB4', 'Malupet', 1),
('SKL_1J6KAMDI42CB3D785', 'Siraulo', 1),
('SKL_1J6KDD7S764D4113F', '70+WPM Typing', 1),
('SKL_1J6KDD7S88B0655A1', 'Adaptability', 1),
('SKL_1J6KDD7S949FAAE0D', 'Professional', 1),
('SKL_1J6KDD7S99C20B33D', 'Flexible', 1),
('SKL_1J745IBUK849A177E', 'Singer', 1);

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
('TCK-436d5c16bd', 'USR_1J5OE9KHHF861E1E6', 'Ranuel Glenn Biray', 'dviray223@gmail.com', 'Account Suspension', 'BAKIT POH', 'Open', '2025-09-22 10:08:25', NULL),
('TCK-921da8c037', 'USR_1J5OKLRDK1853EBB9', 'Btan', 'tanbenedict48@gmail.com', 'Job Posting Issue', 'WALA KANG BITAW YA', 'Open', '2025-09-22 12:00:41', '2025-10-03 03:57:40'),
('TCK-a47a29c667', NULL, 'Joey Janine Lejarde', 'joeyjaninel@gmail.com', 'Password Reset Problem', 'pasend po pass', 'Open', '2025-09-22 10:14:54', NULL),
('TCK-dd523ac1d9', NULL, 'Job Seeker', 'jobseeker1@gmail.com', 'Account Suspension', 'Bakit po nasuspend account ko boss', 'Open', '2025-09-19 11:29:48', '2025-09-22 09:20:44'),
('TCK-df7daa595b', NULL, 'Kristian Diether Alcantara', 'mingchancutie@gmail.com', 'Job Posting Issue', 'scammer po yong employer nyo', 'Closed', '2025-09-22 09:53:02', '2025-10-04 12:49:03');

--
-- Triggers `support_tickets`
--
DELIMITER $$
CREATE TRIGGER `trg_support_tickets_update` BEFORE UPDATE ON `support_tickets` FOR EACH ROW SET NEW.updated_at = CURRENT_TIMESTAMP
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `used_password_tokens`
--

CREATE TABLE `used_password_tokens` (
  `token` char(64) NOT NULL,
  `user_id` varchar(40) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `used_password_tokens`
--

INSERT INTO `used_password_tokens` (`token`, `user_id`, `created_at`) VALUES
('5d2cc746dfa6b4f3cbf513841a27264c1aa92061e248d031793a4ac37b40361c', 'USR_1J5SSAARPCF2B618C', '2025-10-06 15:07:26'),
('63c6577d769d6ef5c349e5694e11c6ac669154da82b579d60fddb32bd6d3b1f8', 'USR_1J5STL1HNF2D40513', '2025-09-24 03:54:40'),
('9c31e9daa8bb313b22a86dc1cbda809b9cdd17ae801c4a3334069279dbc382e0', 'USR_1J6PKB2LB61FB0101', '2025-10-05 07:29:12'),
('a43eae440051abbb70f36dab79b2c49a637eadef6fda562720d36d21b5328d30', 'USR_1J5SSJ78OD24BDF34', '2025-09-24 03:45:16'),
('e1a4476a7e33451d694b54d41be368d478326ae9464aade5c19a3441bfa6abaf', 'USR_1J71RFCV5FFBD0014', '2025-10-08 12:07:15'),
('f8e58737a644f7ebd5a37769650fafdb0a632f0389096524d3eae2ecf70fb48b', 'USR_1J71O501R8F75B29A', '2025-10-08 11:08:53');

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
  `password` varchar(255) DEFAULT NULL,
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
  `job_seeker_status` enum('Active','Suspended') NOT NULL DEFAULT 'Active',
  `pwd_id_review_note` text DEFAULT NULL,
  `pwd_id_reviewed_at` datetime DEFAULT NULL,
  `pwd_id_reviewed_by` varchar(40) DEFAULT NULL,
  `resume` varchar(255) DEFAULT NULL,
  `video_intro` varchar(255) DEFAULT NULL,
  `expected_salary_currency` varchar(8) DEFAULT 'PHP',
  `expected_salary_min` int(11) DEFAULT NULL,
  `expected_salary_max` int(11) DEFAULT NULL,
  `expected_salary_period` enum('monthly','yearly','hourly') DEFAULT 'monthly',
  `interests` text DEFAULT NULL,
  `accessibility_preferences` varchar(255) DEFAULT NULL,
  `preferred_location` varchar(255) DEFAULT NULL,
  `preferred_work_setup` enum('On-site','Hybrid','Remote') DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `company_name` varchar(255) DEFAULT '',
  `business_email` varchar(255) DEFAULT '',
  `company_website` varchar(255) DEFAULT NULL,
  `company_phone` varchar(64) DEFAULT NULL,
  `business_permit_number` varchar(100) DEFAULT NULL,
  `employer_status` enum('Pending','Approved','Suspended','Rejected') DEFAULT 'Pending',
  `suspension_reason` text DEFAULT NULL,
  `employer_doc` varchar(255) DEFAULT '',
  `company_owner_name` varchar(150) DEFAULT NULL,
  `contact_person_position` varchar(120) DEFAULT NULL,
  `contact_person_phone` varchar(40) DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `name`, `date_of_birth`, `gender`, `phone`, `region`, `province`, `city`, `full_address`, `email`, `password`, `role`, `experience`, `education`, `education_level`, `primary_skill_summary`, `profile_completeness`, `profile_last_calculated`, `disability`, `disability_type`, `disability_severity`, `assistive_devices`, `pwd_id_number`, `pwd_id_last4`, `pwd_id_status`, `job_seeker_status`, `pwd_id_review_note`, `pwd_id_reviewed_at`, `pwd_id_reviewed_by`, `resume`, `video_intro`, `expected_salary_currency`, `expected_salary_min`, `expected_salary_max`, `expected_salary_period`, `interests`, `accessibility_preferences`, `preferred_location`, `preferred_work_setup`, `created_at`, `company_name`, `business_email`, `company_website`, `company_phone`, `business_permit_number`, `employer_status`, `suspension_reason`, `employer_doc`, `company_owner_name`, `contact_person_position`, `contact_person_phone`, `profile_picture`, `deleted_at`) VALUES
('USR_1J56TV15M1F2FE280', 'Admin User', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'adminuser@admin.com', '$2y$10$URsuz1WYWjlxA2j8aykupuzkBtbhG3MCxA5W7HbX8lBkmLayhibG2', 'admin', 2, 'College', NULL, NULL, 0, NULL, '', NULL, NULL, NULL, NULL, NULL, 'None', 'Active', NULL, NULL, NULL, NULL, NULL, 'PHP', NULL, NULL, 'monthly', NULL, NULL, NULL, NULL, '2025-09-15 14:55:18', '', '', NULL, NULL, NULL, 'Pending', NULL, '', NULL, NULL, NULL, NULL, NULL),
('USR_1J56U7Q2426AF4170', 'Job Seeker', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'jobseeker@gmail.com', '$2y$10$hFzZ8GnI.RU3ZurxILgvIOxoX33RLVi0E79DTWIS1ieaIc9e1w0U.', 'job_seeker', 2, 'College', NULL, NULL, 0, NULL, 'amputated legs', NULL, NULL, NULL, NULL, NULL, 'None', 'Active', NULL, NULL, NULL, NULL, NULL, 'PHP', NULL, NULL, 'monthly', NULL, NULL, NULL, NULL, '2025-09-15 15:00:05', '', '', NULL, NULL, NULL, 'Pending', NULL, '', NULL, NULL, NULL, NULL, NULL),
('USR_1J5B0PRRK7965472D', 'Stephen Hawkings', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'shh123@gmail.com', '$2y$10$5xgH1ZSay6sZUnMTm3LKc.mOUl7TVRdG1aktqphzlrLR3ueJgxgPW', 'job_seeker', 0, 'Bachelor', NULL, NULL, 0, NULL, 'cant walk', NULL, NULL, NULL, NULL, NULL, 'None', 'Active', NULL, NULL, NULL, NULL, NULL, 'PHP', NULL, NULL, 'monthly', NULL, NULL, NULL, NULL, '2025-09-17 05:01:52', '', '', NULL, NULL, NULL, 'Pending', NULL, '', NULL, NULL, NULL, NULL, NULL),
('USR_1J5B1GD9TC6DE8AEB', 'Malupiton', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'malopit@gmail.com', '$2y$10$Z8UQOYZsC7g0VeU.d5nt7uJ5UDP6Q7eGMWgkd3Bz4V1qS6opYY/Nu', 'job_seeker', 0, 'High School Diploma', NULL, NULL, 0, NULL, '', NULL, NULL, NULL, NULL, NULL, 'None', 'Active', NULL, NULL, NULL, NULL, NULL, 'PHP', NULL, NULL, 'monthly', NULL, NULL, NULL, NULL, '2025-09-17 05:14:11', '', '', NULL, NULL, NULL, 'Pending', NULL, '', NULL, NULL, NULL, NULL, NULL),
('USR_1J5B2BNJN20DE0A2C', 'haha', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'haha@gmail.com', '$2y$10$wy4pI0GNixrWvy/Lgn61S.dPfPVk7uyf593qQvgho.iQ6WMzgYn3S', 'job_seeker', 0, '', NULL, NULL, 0, NULL, '', NULL, NULL, NULL, NULL, NULL, 'None', 'Active', NULL, NULL, NULL, NULL, NULL, 'PHP', NULL, NULL, 'monthly', NULL, NULL, NULL, NULL, '2025-09-17 05:29:06', '', '', NULL, NULL, NULL, 'Pending', NULL, '', NULL, NULL, NULL, NULL, NULL),
('USR_1J5B2FRH3F148E76C', 'hehe', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'hehe@gmail.com', '$2y$10$Xt64xNHCrF1H04PdPkUVT.rb0seAl.5xnVeS60ZywzVzNLXWcxyg2', 'job_seeker', 0, '', NULL, NULL, 0, NULL, '', NULL, NULL, NULL, NULL, NULL, 'None', 'Active', NULL, NULL, NULL, NULL, NULL, 'PHP', NULL, NULL, 'monthly', NULL, NULL, NULL, NULL, '2025-09-17 05:31:21', '', '', NULL, NULL, NULL, 'Pending', NULL, '', NULL, NULL, NULL, NULL, NULL),
('USR_1J5B2M5T83B7D8173', 'ranuel glenn biray', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'biray@gmail.com', '$2y$10$yFMXORSd5wL2dDuIj4I64Oce4.4Vu3QWTFDvxrbA3MfBMcv8iNpwq', 'job_seeker', 0, '', NULL, NULL, 0, NULL, '', NULL, NULL, NULL, NULL, NULL, 'None', 'Active', NULL, NULL, NULL, NULL, NULL, 'PHP', NULL, NULL, 'monthly', NULL, NULL, NULL, NULL, '2025-09-17 05:34:48', '', '', NULL, NULL, NULL, 'Pending', NULL, '', NULL, NULL, NULL, NULL, NULL),
('USR_1J5B68JTBEBF5DAEB', 'Fishbook', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'fishbook@gmail.com', '$2y$10$5G3U0I7pPr4jRbhqJJqpp.uD0b.dHiw1rGb6nBTMQX7A0mb9vjRvm', 'employer', 0, '', NULL, NULL, 0, NULL, '', NULL, NULL, NULL, NULL, NULL, 'None', 'Active', NULL, NULL, NULL, NULL, NULL, 'PHP', NULL, NULL, 'monthly', NULL, NULL, NULL, NULL, '2025-09-17 06:37:18', 'Pesbook', 'Pesbook@pesbook.com', NULL, NULL, '123JaSD9', 'Approved', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
('USR_1J5BVJSAO53BB41D8', 'Lebron James', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'lebronjames@gmail.com', '$2y$10$RZ5iiJJmlUgMT8DVyTzhuOv6uYvvR74qmm9PyAz8BhTIM/YFpreee', 'employer', 0, '', NULL, NULL, 0, NULL, 'amputated legs', NULL, NULL, NULL, NULL, NULL, 'None', 'Active', NULL, NULL, NULL, NULL, NULL, 'PHP', NULL, NULL, 'monthly', NULL, NULL, NULL, NULL, '2025-09-17 14:00:22', 'NBA', 'nba@2k.com', NULL, NULL, 'MA2i93S', 'Approved', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
('USR_1J5EE25HV5A06B07B', 'Dayet A. Alcantara', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'dayet@gmail.com', '$2y$10$enKkSufnM71IlFz79jRpDuAJMZ819B4dVaYcX.VZRDUx6mKSdsH/a', 'employer', 0, '', NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'None', 'Active', NULL, NULL, NULL, NULL, NULL, 'PHP', NULL, NULL, 'monthly', NULL, NULL, NULL, NULL, '2025-09-18 12:51:19', 'NU', '', NULL, NULL, 'KSZ291', 'Approved', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
('USR_1J5EE439NF8AC4B2A', 'Pusakal A. Aso', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'bago@gmail.com', '$2y$10$lGzHl/s/pXPW9ZAgGbNDLeUW7ttQ3bQza3uOcleuAHLOoIItFH.Mm', 'job_seeker', 0, '', NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'None', 'Active', NULL, NULL, NULL, NULL, NULL, 'PHP', NULL, NULL, 'monthly', NULL, NULL, NULL, NULL, '2025-09-18 12:52:22', '', '', NULL, NULL, NULL, 'Pending', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
('USR_1J5G4NT5ED833F9DB', 'Jobseeker 1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'jobseeker1@gmail.com', '$2y$10$EYt/npX0Q4WSDTbyUhErTeBTLTKg0lYHWRqy1ZJ9ModR1RGgsUnu.', 'job_seeker', 0, '', NULL, NULL, 25, '2025-09-21 13:04:16', '', NULL, NULL, NULL, NULL, NULL, 'Verified', 'Active', NULL, NULL, NULL, 'uploads/resumes/res_68ce952d65999.pdf', 'uploads/videos/vid_68ce952d6633f.mp4', 'PHP', NULL, NULL, 'monthly', NULL, NULL, NULL, NULL, '2025-09-19 04:46:54', '', '', NULL, NULL, NULL, 'Pending', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
('USR_1J5G4VMMM25284FA7', 'Employer 1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'employer1@gmail.com', '$2y$10$F4JHMFHVWq4QTRHTZv7mruxLmh78lruIwjL0NKtwPe/fjJrmxvJAK', 'employer', 0, '', NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'None', 'Active', NULL, NULL, NULL, NULL, NULL, 'PHP', NULL, NULL, 'monthly', NULL, NULL, NULL, NULL, '2025-09-19 04:51:10', 'Employerz', 'employer1@employer.com', NULL, NULL, '9jASDHq', 'Suspended', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
('USR_1J5J4N17RBD36BED7', 'Job S. 2', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'jobseeker2@gmail.com', '$2y$10$ntvPqcpGHmf5BbK51VoZtudiSq3yEbYl9fT86z.N3VSW6gNafrRf6', 'job_seeker', 0, '', NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Verified', 'Active', NULL, NULL, NULL, NULL, NULL, 'PHP', NULL, NULL, 'monthly', NULL, NULL, NULL, NULL, '2025-09-20 08:44:09', '', '', NULL, NULL, NULL, 'Pending', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
('USR_1J5J4O6NF7F3E6B7B', 'Employer 2', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'employer2@gmail.com', '$2y$10$5emJJXIbvSF9RK72ooHZKO0QrIrpMKWCRbn6iUW4aOXifqGk4R2ja', 'employer', 0, '', NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'None', 'Active', NULL, NULL, NULL, NULL, NULL, 'PHP', NULL, NULL, 'monthly', NULL, NULL, NULL, NULL, '2025-09-20 08:44:47', 'Tigreal', 'tigreal@gmail.com', NULL, NULL, 'MA2i95S', 'Suspended', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
('USR_1J5J6RC3C4EB2EC4A', 'Asdasd Asd', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'asdasd@gmail.com', '$2y$10$xb3HYP6uMy3JE.nz8hJpZ.nXl3Bde94iOKxn5eZEI1Rfsj170rMji', 'job_seeker', 0, '', NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Verified', 'Active', NULL, NULL, NULL, NULL, NULL, 'PHP', NULL, NULL, 'monthly', NULL, NULL, NULL, NULL, '2025-09-20 09:21:28', '', '', NULL, NULL, NULL, 'Pending', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
('USR_1J5J6SHHO92741CCB', 'Asd Asd', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'asdsssd@gmail.com', '$2y$10$y3RFVSMX5yFXBTbAcgAcz.gXj1GUrl7yeaj/pwNLYRetFO5XYvs9C', 'employer', 0, '', NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'None', 'Active', NULL, NULL, NULL, NULL, NULL, 'PHP', NULL, NULL, 'monthly', NULL, NULL, NULL, NULL, '2025-09-20 09:22:07', 'NBAasd', '', NULL, NULL, 'MA2i95SSASD', 'Approved', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
('USR_1J5JCBS0N9F9EABA0', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'jobseeker3@gmail.com', '$2y$10$85r9JaBwmMZrnK9LYeA8EOfmjMnuDvAlxMsme.n4Hx5iKjygFHOiu', 'job_seeker', 0, '', NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Verified', 'Active', NULL, NULL, NULL, NULL, NULL, 'PHP', NULL, NULL, 'monthly', NULL, NULL, NULL, NULL, '2025-09-20 10:57:52', '', '', NULL, NULL, NULL, 'Pending', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
('USR_1J5JCE1NFE1D671C3', 'Employer', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'employer3@gmail.com', '$2y$10$gcY9rOxXwHGdIf84XbEpXuIwZoMh8qgDvfZV324cY2URRm23E5/dS', 'employer', 0, '', NULL, NULL, 0, NULL, 'hahahaha', NULL, NULL, NULL, NULL, NULL, 'None', 'Active', NULL, NULL, NULL, NULL, NULL, 'PHP', NULL, NULL, 'monthly', NULL, NULL, NULL, NULL, '2025-09-20 10:59:03', 'NBA', 'nba@2k.com', 'https://facebook.com', '09291192929', 'MA2i95SAS', 'Approved', NULL, 'uploads/employers/empdoc_68cfcf546bd19.png', NULL, NULL, NULL, NULL, NULL),
('USR_1J5LLAQD65A58E26F', 'Job Seeker', NULL, NULL, '09556302474', '4a', 'batangasd', 'manila', 'saas dasd qwd qwd asd', 'jobseeker4@gmail.com', '$2y$10$r97qYG35iPOC6hcaGPZ9H.1Aggm75ccJY5txBuyK/Xdmt8MiNYHN.', 'job_seeker', 0, '', 'High School', 'asdasd', 72, '2025-09-21 17:09:58', 'amputated legs', 'Visual', 'Mild', 'Wheelchair', 0x434b412b6278347430662f3579374f4b4a5347562f374832444358654e58637779736e4a69625a4c7775413d, '3123', 'Verified', 'Active', NULL, NULL, NULL, NULL, NULL, 'PHP', NULL, NULL, 'monthly', NULL, NULL, NULL, NULL, '2025-09-21 08:13:03', '', '', NULL, NULL, NULL, 'Pending', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
('USR_1J5M1Q37H8E143F78', 'Kristian Diether Alcantara', NULL, 'Male', '09556302474', '4a', 'batangasd', 'lipa', 'saas dasd qwd qwd asd', 'jobseeker5@gmail.com', '$2y$10$X6E2dO09/GhFQZNCNFVj9.P/iInkQhkW19e9EZuOGXQfzv546JRjC', 'job_seeker', 0, '', 'Bachelor', 'hahaha', 95, '2025-09-21 20:20:33', 'Intellectual', 'Visual', 'Moderate', 'Wheelchair', 0x415344415330313233313233313233, '3123', 'Verified', 'Active', NULL, NULL, NULL, 'uploads/resumes/res_68cfed917a407.pdf', 'uploads/videos/vid_68cfed917a94e.mp4', 'PHP', NULL, NULL, 'monthly', NULL, NULL, NULL, NULL, '2025-09-21 11:51:07', '', '', NULL, NULL, NULL, 'Pending', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
('USR_1J5MDGBQ7248C27C5', 'Employer', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'employer4@gmail.com', '$2y$10$un7SJx.yNDo17t5J9aA5pO9xGKt8Y.yYUesaQVi6gcJUhBHqVhbX.', 'employer', 0, '', NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'None', 'Active', NULL, NULL, NULL, NULL, NULL, 'PHP', NULL, NULL, 'monthly', NULL, NULL, NULL, NULL, '2025-09-21 15:15:31', 'NBA', 'mingchancutie@gmail.com', NULL, NULL, 'MA2i94S', 'Approved', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
('USR_1J5MDNGP793240340', 'Lebron James', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'lebronjame1s@gmail.com', '$2y$10$hlR8Gld0camUIHeg3lyyaOsYOWsLn2Qtnlqb06WJsPj6U5ebr98uS', 'job_seeker', 0, '', NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, 0x617364617364617364323232, 'd222', 'Verified', 'Active', NULL, NULL, NULL, NULL, NULL, 'PHP', NULL, NULL, 'monthly', NULL, NULL, NULL, NULL, '2025-09-21 15:19:25', '', '', NULL, NULL, NULL, 'Pending', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
('USR_1J5OE9KHHF861E1E6', 'Ranuel Glenn Biray', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'dviray223@gmail.com', '$2y$10$fN7gTKzRdMdLDPrJrsKBTulbfFjzbqXUt2kShiqiwQd0v8CdCVMWa', 'job_seeker', 0, '', NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, 0x61736461736471776571773132, 'qw12', 'Verified', 'Active', NULL, NULL, NULL, NULL, NULL, 'PHP', NULL, NULL, 'monthly', NULL, NULL, NULL, NULL, '2025-09-22 10:07:48', '', '', NULL, NULL, NULL, 'Pending', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
('USR_1J5OKLRDK1853EBB9', 'Btan', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'tanbenedict48@gmail.com', '$2y$10$dIb5RLHqsHow2iDu/T9G5eF9sLQ1kCvYeEvWszJxt568vAnGlhWfK', 'job_seeker', 0, '', NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, 0x6b6173646a61736431323033, '1203', 'Rejected', 'Active', NULL, NULL, NULL, NULL, NULL, 'PHP', NULL, NULL, 'monthly', NULL, NULL, NULL, NULL, '2025-09-22 11:59:20', '', '', NULL, NULL, NULL, 'Pending', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
('USR_1J5SSAARPCF2B618C', 'Asd', NULL, NULL, '09556302474', NULL, NULL, NULL, NULL, 'asd@asd.com', '$2y$10$fQB4lOOSnRld141YzD5cO.ojm7gPRf9eoazrU1dolRlCG8eqU16Sm', 'job_seeker', 0, '', NULL, NULL, 0, NULL, 'Hearing', NULL, NULL, NULL, 0x48414841313233, 'A123', 'Verified', 'Active', NULL, NULL, NULL, NULL, NULL, 'PHP', NULL, NULL, 'monthly', NULL, NULL, NULL, NULL, '2025-09-24 03:29:48', '', '', NULL, NULL, NULL, 'Pending', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
('USR_1J5SSJ78OD24BDF34', 'Kristian Diether Alcantara', '2005-06-24', 'Male', '09556302464', '4A', 'Batangas', 'lipa', 'saas dasd qwd qwd asd', 'mingchancutie@gmail.com', '$2y$10$ehZxeAUHS4gY.3w0/cwS0.6e.LfyEamp3lyeRLGkeybiy6QIOnTwG', 'job_seeker', 0, '', 'Masteral', 'Pogi lang', 100, '2025-10-13 16:23:32', 'Speech', 'Physical disability', 'Mild', 'Wheelchair', 0x61617764617364313233313234, '3124', 'Verified', 'Active', NULL, NULL, NULL, 'uploads/resumes/res_68df6be6892b8.pdf', NULL, 'PHP', 50000, 80000, 'monthly', 'Customer Support', 'Flexible Hours,Night Shift Option,Training Provided,Internet Allowance,Equipment Provided', 'Lipa', 'Remote', '2025-09-24 03:34:40', '', '', NULL, NULL, NULL, 'Pending', NULL, NULL, NULL, NULL, NULL, 'uploads/profile/pf_68d38e8e3a4fb.png', NULL),
('USR_1J5STL1HNF2D40513', 'Dayet Na Employer', NULL, NULL, '09556302464', NULL, NULL, NULL, NULL, 'mingqt3143@gmail.com', '$2y$10$uuxQplOkZ89F3evtHMbfkOSPIoW2JVFH5L7JvptCTucF6vU8XfIRK', 'employer', 0, '', NULL, NULL, 0, NULL, 'Visual', NULL, NULL, NULL, NULL, NULL, 'None', 'Active', NULL, NULL, NULL, NULL, NULL, 'PHP', NULL, NULL, 'monthly', NULL, NULL, NULL, NULL, '2025-09-24 03:53:08', 'Pusa', 'mingqt3143@gmail.com', NULL, '09291192929', 'KSZ291d', 'Approved', NULL, 'uploads/employers/empdoc_68df680745b24.jpg', NULL, NULL, NULL, 'uploads/profile/pf_68df680745e5a.png', NULL),
('USR_1J6PKB2LB61FB0101', 'Joey Janine Lejarde', NULL, NULL, '09556302464', NULL, NULL, NULL, NULL, 'joeyjaninel@gmail.com', '$2y$10$CjxQmyPaUIfA6qfOzn3C3.gSEYekYWC2SSRtWH1v6TRP3boVeGBGC', 'employer', 0, '', NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'None', 'Active', NULL, NULL, NULL, NULL, NULL, 'PHP', NULL, NULL, 'monthly', NULL, NULL, NULL, NULL, '2025-10-05 07:28:23', 'Aso', '', NULL, NULL, '213213123asdasd', 'Approved', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
('USR_1J71O501R8F75B29A', 'Juwey', NULL, NULL, '09291192929', NULL, NULL, NULL, NULL, 'heyitssiyam@gmail.com', '$2y$10$Bi2jsC.uTEc87zz8VEZr.u5nlCheiOVATo8rYxoltEPtWYSdkMhj6', 'employer', 0, '', NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'None', 'Active', NULL, NULL, NULL, NULL, NULL, 'PHP', NULL, NULL, 'monthly', NULL, NULL, NULL, NULL, '2025-10-08 11:08:53', 'Pusa', 'nba@2k.com', NULL, NULL, 'KSZ2912123551', 'Approved', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
('USR_1J71RFCV5FFBD0014', 'May Bitaw To Ya', NULL, NULL, '09556302464', NULL, NULL, NULL, NULL, 'maybitaw1@gmail.com', '$2y$10$Finr349DEqfpiLqaUfhhTOg2Kh.HmdJ.dpYo2f2ZLSvEinhCMGYS6', 'employer', 0, '', NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'None', 'Active', NULL, NULL, NULL, NULL, NULL, 'PHP', NULL, NULL, 'monthly', NULL, NULL, NULL, NULL, '2025-10-08 12:07:00', 'Pusa', 'mingqt3143@gmail.com', NULL, NULL, 'KSZ291ASDASDQWE', 'Approved', NULL, NULL, 'Wakabg bitaw', 'CEO', '123123123123', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_accessibility_prefs`
--

CREATE TABLE `user_accessibility_prefs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` varchar(40) NOT NULL,
  `tag` varchar(120) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_accessibility_prefs`
--

INSERT INTO `user_accessibility_prefs` (`id`, `user_id`, `tag`, `created_at`) VALUES
(43, 'USR_1J5SSJ78OD24BDF34', 'Flexible Hours', '2025-10-04 14:26:10'),
(44, 'USR_1J5SSJ78OD24BDF34', 'Night Shift Option', '2025-10-04 14:26:10'),
(45, 'USR_1J5SSJ78OD24BDF34', 'Training Provided', '2025-10-04 14:26:10'),
(46, 'USR_1J5SSJ78OD24BDF34', 'Internet Allowance', '2025-10-04 14:26:10'),
(47, 'USR_1J5SSJ78OD24BDF34', 'Equipment Provided', '2025-10-04 14:26:10');

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
(1, 'USR_1J5LLAQD65A58E26F', 'cisco', 'cisco', '2025-08-31', '2025-12-04', '123123', NULL, '2025-09-21 09:09:41'),
(2, 'USR_1J5M1Q37H8E143F78', 'cisco', 'cisco', '2025-09-29', '2025-10-02', '123123', 'uploads/certs/cert_68cfed452f136.png', '2025-09-21 12:19:17'),
(3, 'USR_1J5SSJ78OD24BDF34', 'cisco', 'cisco', '2025-11-06', '2025-10-20', '123123', NULL, '2025-10-03 06:14:38');

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
(1, 'USR_1J5LLAQD65A58E26F', 'alorica', 'malupet', '2025-09-03', NULL, 1, 'wala', '2025-09-21 08:20:19'),
(2, 'USR_1J5M1Q37H8E143F78', 'alorica', 'malupet', '2025-09-30', NULL, 1, 'wala', '2025-09-21 12:19:37'),
(3, 'USR_1J5SSJ78OD24BDF34', 'alorica', 'malupet', '2025-10-09', '2025-10-20', 0, 'wala', '2025-10-03 06:14:54');

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
('USK_1J56U8JAB1F84B21C', 'USR_1J56U7Q2426AF4170', 'SKL_1J56U59082963C9AB', '2025-09-15 15:00:31'),
('USK_1J6KDD7SCEB10A99B', 'USR_1J5SSJ78OD24BDF34', 'SKL_1J5BOAHOR18D08123', '2025-10-03 06:51:01'),
('USK_1J6KDD7SG2FD4E90A', 'USR_1J5SSJ78OD24BDF34', 'SKL_1J5B1B2JJ33509B84', '2025-10-03 06:51:01'),
('USK_1J6KDD7SH4F7ADFD0', 'USR_1J5SSJ78OD24BDF34', 'SKL_1J5B83SAA67C5E1CA', '2025-10-03 06:51:01'),
('USK_1J6KDD7SI2AA03ACA', 'USR_1J5SSJ78OD24BDF34', 'SKL_1J6KDD7S764D4113F', '2025-10-03 06:51:01'),
('USK_1J6KDD7SJFE5A9207', 'USR_1J5SSJ78OD24BDF34', 'SKL_1J6KDD7S88B0655A1', '2025-10-03 06:51:01'),
('USK_1J6KDD7SL6615E3E2', 'USR_1J5SSJ78OD24BDF34', 'SKL_1J6KDD7S99C20B33D', '2025-10-03 06:51:01'),
('USK_1J6KDD7SOF6191879', 'USR_1J5SSJ78OD24BDF34', 'SKL_1J6KDD7S949FAAE0D', '2025-10-03 06:51:01'),
('USK_1J6L7VDCC05E34DAF', 'USR_1J5SSJ78OD24BDF34', 'SKL_1J5GAF24GAB7A1B8C', '2025-10-03 14:35:20'),
('USK_1J6L7VDCDDE494873', 'USR_1J5SSJ78OD24BDF34', 'SKL_1J5OD19IA42500B94', '2025-10-03 14:35:20'),
('USK_1J6L7VDCE5406008F', 'USR_1J5SSJ78OD24BDF34', 'SKL_1J5JDC38S834D3E3A', '2025-10-03 14:35:20'),
('USK_1J6L7VDCE8670B68C', 'USR_1J5SSJ78OD24BDF34', 'SKL_1J5GAF24G009B7ED8', '2025-10-03 14:35:20'),
('USK_1J6L7VDCFF02D6E9E', 'USR_1J5SSJ78OD24BDF34', 'SKL_1J5JDC38UFB050C2D', '2025-10-03 14:35:20'),
('USK_1J6L7VDCG72A73D26', 'USR_1J5SSJ78OD24BDF34', 'SKL_1J5JDC38O90BB7B46', '2025-10-03 14:35:20');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_tasks_log`
--
ALTER TABLE `admin_tasks_log`
  ADD PRIMARY KEY (`id`);

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
-- Indexes for table `job_applicable_pwd_types`
--
ALTER TABLE `job_applicable_pwd_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_job_type` (`job_id`,`pwd_type`),
  ADD KEY `idx_type` (`pwd_type`);

--
-- Indexes for table `job_reports`
--
ALTER TABLE `job_reports`
  ADD PRIMARY KEY (`report_id`),
  ADD KEY `job_id` (`job_id`),
  ADD KEY `status` (`status`),
  ADD KEY `fk_job_reports_reporter_user` (`reporter_user_id`);

--
-- Indexes for table `job_skills`
--
ALTER TABLE `job_skills`
  ADD PRIMARY KEY (`job_skill_id`),
  ADD UNIQUE KEY `uniq_job_skill` (`job_id`,`skill_id`),
  ADD KEY `skill_id` (`skill_id`);

--
-- Indexes for table `search_history`
--
ALTER TABLE `search_history`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_user_query` (`user_id`,`query`),
  ADD KEY `idx_user_created` (`user_id`,`created_at`);

--
-- Indexes for table `skills`
--
ALTER TABLE `skills`
  ADD PRIMARY KEY (`skill_id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD KEY `idx_skills_active` (`is_active`);

--
-- Indexes for table `support_tickets`
--
ALTER TABLE `support_tickets`
  ADD PRIMARY KEY (`ticket_id`),
  ADD KEY `idx_support_status_created` (`status`,`created_at`),
  ADD KEY `fk_support_user` (`user_id`);

--
-- Indexes for table `used_password_tokens`
--
ALTER TABLE `used_password_tokens`
  ADD PRIMARY KEY (`token`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `uniq_business_permit` (`business_permit_number`),
  ADD KEY `idx_users_employer_status` (`employer_status`),
  ADD KEY `idx_pwd_status` (`pwd_id_status`),
  ADD KEY `idx_pwd_reviewed_at` (`pwd_id_reviewed_at`),
  ADD KEY `idx_expected_salary` (`expected_salary_min`,`expected_salary_max`,`expected_salary_period`);

--
-- Indexes for table `user_accessibility_prefs`
--
ALTER TABLE `user_accessibility_prefs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_user_tag` (`user_id`,`tag`),
  ADD KEY `idx_tag` (`tag`);

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
-- AUTO_INCREMENT for table `admin_tasks_log`
--
ALTER TABLE `admin_tasks_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `job_applicable_pwd_types`
--
ALTER TABLE `job_applicable_pwd_types`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `search_history`
--
ALTER TABLE `search_history`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=125;

--
-- AUTO_INCREMENT for table `user_accessibility_prefs`
--
ALTER TABLE `user_accessibility_prefs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=48;

--
-- AUTO_INCREMENT for table `user_certifications`
--
ALTER TABLE `user_certifications`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `user_experiences`
--
ALTER TABLE `user_experiences`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

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
-- Constraints for table `job_applicable_pwd_types`
--
ALTER TABLE `job_applicable_pwd_types`
  ADD CONSTRAINT `fk_job_pwd_type_job` FOREIGN KEY (`job_id`) REFERENCES `jobs` (`job_id`) ON DELETE CASCADE;

--
-- Constraints for table `job_reports`
--
ALTER TABLE `job_reports`
  ADD CONSTRAINT `fk_job_reports_reporter_user` FOREIGN KEY (`reporter_user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `job_reports_ibfk_1` FOREIGN KEY (`job_id`) REFERENCES `jobs` (`job_id`) ON DELETE CASCADE;

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
-- Constraints for table `used_password_tokens`
--
ALTER TABLE `used_password_tokens`
  ADD CONSTRAINT `fk_used_password_tokens_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `user_accessibility_prefs`
--
ALTER TABLE `user_accessibility_prefs`
  ADD CONSTRAINT `fk_uap_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

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
