-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 18, 2026 at 06:46 AM
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
-- Database: `finance_manager`
--

-- --------------------------------------------------------

--
-- Table structure for table `achievements`
--

CREATE TABLE `achievements` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `title` varchar(100) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `points` int(11) DEFAULT 0,
  `unlocked_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `budget`
--

CREATE TABLE `budget` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `category` varchar(50) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `limit_amount` decimal(10,2) DEFAULT NULL,
  `budget_month` varchar(7) NOT NULL,
  `date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `budget`
--

INSERT INTO `budget` (`id`, `user_id`, `category`, `amount`, `limit_amount`, `budget_month`, `date`) VALUES
(9, 18, 'food', 400.00, NULL, '2026-02', '2026-02-25'),
(10, 18, 'fare', 7000.00, NULL, '2026-02', '2026-02-26'),
(11, 18, 'food', 5000.00, NULL, '2026-03', '2026-03-02'),
(12, 18, 'Data bundles', 1000.00, NULL, '2026-03', '2026-03-02');

-- --------------------------------------------------------

--
-- Table structure for table `debts`
--

CREATE TABLE `debts` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `type` enum('owed','borrowed') NOT NULL,
  `due_date` date DEFAULT NULL,
  `status` enum('pending','paid') DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `expenses`
--

CREATE TABLE `expenses` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `category` varchar(50) DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `date` date DEFAULT NULL,
  `expense_month` varchar(7) DEFAULT NULL,
  `tag` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `expenses`
--

INSERT INTO `expenses` (`id`, `user_id`, `category`, `amount`, `date`, `expense_month`, `tag`) VALUES
(18, 18, 'fare', 200.00, '2026-02-04', '2026-02', NULL),
(19, 18, 'fare', 200.00, '2026-02-11', '2026-02', NULL),
(20, 18, 'fare', 200.00, '2026-02-12', '2026-02', NULL),
(21, 18, 'fare', 200.00, '2026-02-17', '2026-02', NULL),
(22, 18, 'fare', 200.00, '2026-02-18', '2026-02', NULL),
(23, 18, 'fare', 220.00, '2026-02-19', '2026-02', NULL),
(24, 18, 'fare', 200.00, '2026-02-20', '2026-02', NULL),
(25, 18, 'snacks', 70.00, '2026-02-20', '2026-02', NULL),
(26, 18, 'food', 360.00, '2026-02-26', '2026-02', NULL),
(27, 18, 'Rent', 20000.00, '2026-02-05', '2026-02', NULL),
(28, 18, 'Data bundles', 100.00, '2026-03-03', '2026-03', NULL),
(29, 18, 'food', 670.00, '2026-03-04', '2026-03', NULL),
(30, 18, 'fare', 8090.00, '2026-03-05', '2026-03', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `income`
--

CREATE TABLE `income` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `source` varchar(100) DEFAULT NULL,
  `date` date DEFAULT NULL,
  `income_month` varchar(7) DEFAULT NULL,
  `tag` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `income`
--

INSERT INTO `income` (`id`, `user_id`, `amount`, `source`, `date`, `income_month`, `tag`) VALUES
(17, 18, 5500.00, 'Work', '2026-02-02', '2026-02', NULL),
(18, 18, 10000.00, 'Income', '2026-02-02', '2026-02', NULL),
(19, 18, 100000.00, 'Allowance', '2026-03-02', '2026-03', NULL),
(20, 18, 55000.00, 'Income', '2026-03-03', '2026-03', NULL),
(21, 18, 5009.00, 'Allowance', '2026-03-05', '2026-03', NULL),
(22, 18, 5000.00, 'income', '2026-03-04', '2026-03', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `status` enum('unread','read') DEFAULT 'unread',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `type` varchar(50) DEFAULT 'general'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `recurring_log`
--

CREATE TABLE `recurring_log` (
  `id` int(11) NOT NULL,
  `recurring_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `applied_month` varchar(7) NOT NULL,
  `applied_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `recurring_log`
--

INSERT INTO `recurring_log` (`id`, `recurring_id`, `user_id`, `applied_month`, `applied_at`) VALUES
(1, 3, 18, '2026-02', '2026-02-27 07:23:12');

-- --------------------------------------------------------

--
-- Table structure for table `recurring_transactions`
--

CREATE TABLE `recurring_transactions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `category` varchar(50) DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `type` enum('income','expense') NOT NULL,
  `frequency` enum('daily','weekly','monthly') NOT NULL,
  `next_due` date DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `label` varchar(150) DEFAULT NULL,
  `day_of_month` tinyint(4) DEFAULT 1,
  `active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `recurring_transactions`
--

INSERT INTO `recurring_transactions` (`id`, `user_id`, `category`, `amount`, `type`, `frequency`, `next_due`, `status`, `label`, `day_of_month`, `active`) VALUES
(3, 18, 'Rent', 20000.00, 'expense', 'daily', NULL, 'active', 'Rent', 5, 1),
(4, 18, 'Salary', 60000.00, 'income', 'daily', NULL, 'active', 'Salary', 2, 1);

-- --------------------------------------------------------

--
-- Table structure for table `savings`
--

CREATE TABLE `savings` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `goal` varchar(100) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `date` date NOT NULL,
  `savings_month` varchar(7) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `savings`
--

INSERT INTO `savings` (`id`, `user_id`, `goal`, `amount`, `date`, `savings_month`) VALUES
(8, 18, 'Savings', 300.00, '2026-02-23', '2026-02'),
(9, 18, 'Partying', 1000.00, '2026-02-23', '2026-02'),
(10, 18, 'Partying', 2000.00, '2026-02-25', '2026-02'),
(11, 18, 'car', 1000000.00, '2026-03-03', '2026-03'),
(12, 18, 'car', 50000.00, '2026-03-03', '2026-03'),
(13, 18, 'car', 4000000.00, '2026-03-04', '2026-03');

-- --------------------------------------------------------

--
-- Table structure for table `savings_goals`
--

CREATE TABLE `savings_goals` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  `target_amount` decimal(10,2) DEFAULT NULL,
  `current_amount` decimal(10,2) DEFAULT 0.00,
  `due_date` date DEFAULT NULL,
  `status` enum('active','completed') DEFAULT 'active',
  `notes` varchar(500) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `savings_goals`
--

INSERT INTO `savings_goals` (`id`, `user_id`, `title`, `target_amount`, `current_amount`, `due_date`, `status`, `notes`) VALUES
(5, 18, 'Savings', 300.00, 300.00, '2026-02-24', 'completed', ''),
(6, 18, 'Partying', 3000.00, 3000.00, '2026-03-01', 'completed', ''),
(7, 18, 'car', 99999999.99, 5050000.00, '2026-12-09', 'active', 'A must...');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `fullname` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `points` int(11) DEFAULT 0,
  `reset_token` varchar(255) DEFAULT NULL,
  `token_expiry` datetime DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `fullname`, `email`, `password`, `points`, `reset_token`, `token_expiry`, `avatar`) VALUES
(2, 'Ernest Kimanthi', 'ernest3@gmail.com', '$2y$10$OmJ1he0SqBjxRCSSCeJweeXf0Qx.5Rmax4D6xQkngmpT01mLeY9NW', 0, NULL, NULL, NULL),
(3, 'Wayne example', 'wayne@gmail.com', '$2y$10$fOFBk.u7OWtbKoytYcsxTeSRNun2mGoJ37hONF/U3zFR3BIYcQLWu', 0, NULL, NULL, NULL),
(4, 'Robin peter ', 'peter@gmail.com', '$2y$10$xf6luqcDVxZNwr/VHJuFHOlj2WJJTk14U3aB5GJBC5cSHqg1yuUey', 0, NULL, NULL, NULL),
(10, 'finance manager', 'finance@gmail.com', '$2y$10$ZgwdlV36D1S0q5KWS/dKwOwwnuXNAXMFq6H2gUNIcVTcLDnx7Opc2', 0, NULL, NULL, NULL),
(12, 'finance manager', 'manager@gmail.com', '$2y$10$La/SZA9ykzq/HsmQiqUamu0pQI0HvcxejsBXee.o1jlDRs2FieH8u', 0, NULL, NULL, NULL),
(18, 'Robin Mwaniki', 'robinmwaniki3@gmail.com', '$2y$10$/g/oDT8ZL2TyYptqDeqa..oNr/HI0WWe1zkqtyJtGpi360q0QOErq', 0, NULL, NULL, 'avatar_18_1771828828.jpg');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `achievements`
--
ALTER TABLE `achievements`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `budget`
--
ALTER TABLE `budget`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `debts`
--
ALTER TABLE `debts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `expenses`
--
ALTER TABLE `expenses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `income`
--
ALTER TABLE `income`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `recurring_log`
--
ALTER TABLE `recurring_log`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_apply` (`recurring_id`,`applied_month`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `recurring_transactions`
--
ALTER TABLE `recurring_transactions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `savings`
--
ALTER TABLE `savings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `savings_goals`
--
ALTER TABLE `savings_goals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

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
-- AUTO_INCREMENT for table `achievements`
--
ALTER TABLE `achievements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `budget`
--
ALTER TABLE `budget`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `debts`
--
ALTER TABLE `debts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `expenses`
--
ALTER TABLE `expenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `income`
--
ALTER TABLE `income`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `recurring_log`
--
ALTER TABLE `recurring_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `recurring_transactions`
--
ALTER TABLE `recurring_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `savings`
--
ALTER TABLE `savings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `savings_goals`
--
ALTER TABLE `savings_goals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `budget`
--
ALTER TABLE `budget`
  ADD CONSTRAINT `budget_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `expenses`
--
ALTER TABLE `expenses`
  ADD CONSTRAINT `expenses_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `income`
--
ALTER TABLE `income`
  ADD CONSTRAINT `income_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `savings`
--
ALTER TABLE `savings`
  ADD CONSTRAINT `savings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
