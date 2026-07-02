-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 03, 2026 at 07:55 PM
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
-- Database: `it_helpdesk`
--

-- --------------------------------------------------------

--
-- Table structure for table `email_logs`
--

CREATE TABLE `email_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `recipient_email` varchar(255) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `status` enum('sent','failed') DEFAULT 'sent',
  `error_message` text DEFAULT NULL,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `email_logs`
--

INSERT INTO `email_logs` (`id`, `user_id`, `recipient_email`, `subject`, `status`, `error_message`, `sent_at`) VALUES
(1, 1, 'admin@company.com', 'Ticket #28 Purged', '', 'Record permanently scrubbed from master system grids.', '2026-06-03 17:06:01'),
(2, 1, 'admin@company.com', 'Ticket #28 Purged', '', 'Record permanently scrubbed from master system grids.', '2026-06-03 17:06:22'),
(3, 1, 'admin@company.com', 'Ticket #29 Status Updated', '', 'Tracking changed to [In Progress]', '2026-06-03 17:06:24'),
(4, 1, 'admin@company.com', 'Ticket #31 Status Updated', '', 'Tracking changed to [In Progress]', '2026-06-03 17:07:30'),
(5, 1, 'admin@company.com', 'Ticket #22 Status Updated', '', 'Tracking changed to [Resolved]', '2026-06-03 17:07:33'),
(6, 1, 'admin@company.com', 'System Update - Status Changed', '', 'Ticket #31 status mapping changed to [Resolved]', '2026-06-03 17:11:20'),
(7, 1, 'admin@company.com', 'System Update - Status Changed', '', 'Ticket #22 status mapping changed to [Open]', '2026-06-03 17:11:22'),
(8, 1, 'admin@company.com', 'System Update - Administrative Reassignment', '', 'Ticket #22 forcefully rerouted to Agent ID: 11', '2026-06-03 17:11:24'),
(9, 1, 'admin@company.com', 'System Update - Status Changed', '', 'Ticket #31 status mapping changed to [Closed]', '2026-06-03 17:12:02'),
(10, 1, 'admin@company.com', 'System Update - Status Changed', '', 'Ticket #31 status mapping changed to [Closed]', '2026-06-03 17:13:22'),
(11, 1, 'admin@company.com', 'System Update - New Ticket Created', '', 'Severity [High]: ffg - ggtg', '2026-06-03 17:13:29'),
(12, 1, 'admin@company.com', 'System Update - Status Changed', '', 'Ticket #32 status mapping changed to [Resolved]', '2026-06-03 17:17:53'),
(13, 1, 'admin@company.com', 'System Update - Status Changed', '', 'Ticket #32 status mapping changed to [In Progress]', '2026-06-03 17:17:54'),
(14, 2, 'admin@company.com', 'System Update - New Ticket Created', '', 'Severity [Critical]: vfvvfv - vfvfvfv', '2026-06-03 17:18:26'),
(15, 1, 'admin@company.com', 'System Update - Status Changed', '', 'Ticket #32 status mapping changed to [In Progress]', '2026-06-03 17:18:32'),
(16, 1, 'admin@company.com', 'System Update - Status Changed', '', 'Ticket #31 status mapping changed to [Open]', '2026-06-03 17:26:17'),
(17, 1, 'admin@company.com', 'System Update - Status Changed', '', 'Ticket #33 status mapping changed to [In Progress]', '2026-06-03 17:26:19'),
(18, 1, 'admin@company.com', 'System Update - Status Changed', '', 'Ticket #31 status mapping changed to [In Progress]', '2026-06-03 17:26:24'),
(19, 1, 'admin@company.com', 'System Update - Status Changed', '', 'Ticket #30 status mapping changed to [In Progress]', '2026-06-03 17:26:26'),
(20, 2, 'admin@company.com', 'System Update - New Ticket Created', '', 'Severity [Medium]: fvfvfv - vfvfv', '2026-06-03 17:26:45'),
(21, 1, 'admin@company.com', 'System Update - Status Changed', '', 'Ticket #30 status mapping changed to [In Progress]', '2026-06-03 17:26:59'),
(22, 1, 'admin@company.com', 'System Update - Ticket Purged', '', 'Ticket Data Record #22 permanently scrubbed from system operations storage grids.', '2026-06-03 17:27:01'),
(23, 1, 'admin@company.com', 'System Update - Ticket Purged', '', 'Ticket Data Record #31 permanently scrubbed from system operations storage grids.', '2026-06-03 17:27:06'),
(24, 1, 'admin@company.com', 'System Update - Administrative Reassignment', '', 'Ticket #33 forcefully routed to Agent ID: 1', '2026-06-03 17:27:08'),
(25, 1, 'admin@company.com', 'System Update - Administrative Reassignment', '', 'Ticket #32 forcefully routed to Agent ID: 1', '2026-06-03 17:27:10'),
(26, 11, 'admin@company.com', 'System Update - Ticket Claimed', '', 'Ticket #34 successfully claimed by Agent ID: 11', '2026-06-03 17:29:18'),
(27, 11, 'admin@company.com', 'System Update - New Ticket Created', '', 'Severity [Low]: iuilll - llilil', '2026-06-03 17:29:35'),
(28, 1, 'admin@company.com', 'System Update - Administrative Reassignment', '', 'Ticket #32 forcefully routed to Agent ID: 1', '2026-06-03 17:37:15'),
(29, 1, 'admin@company.com', 'System Update - Administrative Reassignment', '', 'Ticket #33 forcefully routed to Agent ID: 11', '2026-06-03 17:37:17'),
(30, 11, 'admin@company.com', 'System Update - Ticket Claimed', '', 'Ticket #29 successfully claimed by Agent ID: 11', '2026-06-03 17:37:40'),
(31, 11, 'admin@company.com', 'System Update - Status Changed', '', 'Ticket #30 status mapping changed to [Resolved]', '2026-06-03 17:37:42'),
(32, 2, 'admin@company.com', 'System Update - New Ticket Created', '', 'Severity [High]: fvfv - fvfv', '2026-06-03 17:38:15'),
(33, 2, 'admin@company.com', 'System Update - Status Changed', '', 'Ticket #33 status mapping changed to [Closed]', '2026-06-03 17:40:19'),
(34, 1, 'admin@gmail.com', 'System Update - Administrative Reassignment', '', 'Ticket #33 forcefully routed to Agent ID: 11', '2026-06-03 17:41:46'),
(35, 1, 'admin@gmail.com', 'System Update - Administrative Reassignment', '', 'Ticket #33 forcefully routed to Agent ID: 1', '2026-06-03 17:41:48'),
(36, 1, 'admin@gmail.com', 'System Update - Administrative Reassignment', '', 'Ticket #36 forcefully routed to Agent ID: 11', '2026-06-03 17:41:50'),
(37, 11, 'admin@gmail.com', 'System Update - Status Changed', '', 'Ticket #34 status mapping changed to [Resolved]', '2026-06-03 17:42:08'),
(38, 2, 'admin@gmail.com', 'System Update - Status Changed', '', 'Ticket #36 status mapping changed to [Closed]', '2026-06-03 17:42:12'),
(39, 1, 'lahiru@gmail.com', 'System Update - Status Changed', '', 'Ticket #33 status mapping changed to [Resolved]', '2026-06-03 17:44:33'),
(40, 2, 'nimal@gmail.com', 'System Update - Status Changed', '', 'Ticket #33 status mapping changed to [Closed]', '2026-06-03 17:45:02'),
(41, 2, 'nimal@gmail.com', 'System Update - Status Changed', '', 'Ticket #33 status mapping changed to [Closed]', '2026-06-03 17:45:08'),
(42, 11, 'kasun@gmail.com', 'System Update - Status Changed', '', 'Ticket #36 status mapping changed to [Resolved]', '2026-06-03 17:45:16'),
(43, 2, 'nimal@gmail.com', 'System Update - New Ticket Created', '', 'Severity [Low]: ffvfv - vfvvfv', '2026-06-03 17:52:40'),
(44, 11, 'kasun@gmail.com', 'System Update - Status Changed', '', 'Ticket #29 status mapping changed to [Resolved]', '2026-06-03 17:53:06'),
(45, 1, 'lahiru@gmail.com', 'System Update - Status Changed', '', 'Ticket #33 status mapping changed to [Resolved]', '2026-06-03 17:53:54'),
(46, 1, 'lahiru@gmail.com', 'System Update - Ticket Purged', '', 'Ticket Data Record #37 permanently scrubbed from system operations storage grids.', '2026-06-03 17:53:58'),
(47, 1, 'lahiru@gmail.com', 'System Update - Ticket Purged', '', 'Ticket Data Record #36 permanently scrubbed from system operations storage grids.', '2026-06-03 17:54:00'),
(48, 1, 'lahiru@gmail.com', 'System Update - Ticket Purged', '', 'Ticket Data Record #33 permanently scrubbed from system operations storage grids.', '2026-06-03 17:54:02'),
(49, 1, 'lahiru@gmail.com', 'System Update - Ticket Purged', '', 'Ticket Data Record #34 permanently scrubbed from system operations storage grids.', '2026-06-03 17:54:05'),
(50, 1, 'lahiru@gmail.com', 'System Update - Ticket Purged', '', 'Ticket Data Record #34 permanently scrubbed from system operations storage grids.', '2026-06-03 17:54:08');

-- --------------------------------------------------------

--
-- Table structure for table `tickets`
--

CREATE TABLE `tickets` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `attachment_path` varchar(255) DEFAULT NULL,
  `status` enum('Open','Assigned','In Progress','Resolved','Closed') DEFAULT 'Open',
  `priority` enum('Low','Medium','High','Critical') DEFAULT 'Low',
  `created_by` int(11) NOT NULL,
  `assigned_to` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tickets`
--

INSERT INTO `tickets` (`id`, `title`, `description`, `attachment_path`, `status`, `priority`, `created_by`, `assigned_to`, `created_at`, `updated_at`) VALUES
(29, 'zxzxzx', 'zxzxzx', NULL, 'Resolved', 'Medium', 1, 11, '2026-06-03 16:54:54', '2026-06-03 17:53:06'),
(30, 'cdsd', 'sdsdsd', NULL, 'Resolved', 'Critical', 1, 11, '2026-06-03 16:56:49', '2026-06-03 17:37:42'),
(32, 'ffg', 'ggtg', NULL, 'Assigned', 'High', 1, 1, '2026-06-03 17:13:29', '2026-06-03 17:27:10'),
(35, 'iuilll', 'llilil', NULL, 'Open', 'Low', 11, NULL, '2026-06-03 17:29:35', '2026-06-03 17:29:35');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `role` enum('Requester','Agent','Admin') DEFAULT 'Requester',
  `password` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `role`, `password`, `password_hash`, `created_at`) VALUES
(1, 'Lahiru Vimukthi', 'lahiru@gmail.com', 'Admin', '', '$2y$10$YgQMQFfQJ1LBPzWk9OI46e3cmQ61alnD7K1V2mhDrykFwJXGSbANG', '2026-06-02 17:11:52'),
(2, 'Nimal Perera', 'nimal@gmail.com', 'Requester', '', '$2y$10$vmuoRQWC7VRzOrHP1/U9Q.jOa.q95Vjw05o040Yq7HHCg1k9TQNWq', '2026-06-02 17:26:54'),
(11, 'Kasun Chamara', 'kasun@gmail.com', 'Agent', '', '$2y$10$PoPgm2HhnO0u0hqYgsyCeuRLmVGTkyE3VuYchOPzMOIdNyv.MukRi', '2026-06-02 17:28:51');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `email_logs`
--
ALTER TABLE `email_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `tickets`
--
ALTER TABLE `tickets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `assigned_to` (`assigned_to`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `email_logs`
--
ALTER TABLE `email_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=51;

--
-- AUTO_INCREMENT for table `tickets`
--
ALTER TABLE `tickets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `email_logs`
--
ALTER TABLE `email_logs`
  ADD CONSTRAINT `email_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `tickets`
--
ALTER TABLE `tickets`
  ADD CONSTRAINT `tickets_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tickets_ibfk_2` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
