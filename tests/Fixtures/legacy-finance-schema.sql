-- Sanitized schema-only reference derived from the user-provided finance_manager.sql dump.
-- Source metadata: MariaDB 10.4.32; phpMyAdmin export generated 2026-11-23.
-- No INSERT statements, user records, financial records, password hashes, or tokens are included.
-- This is a captured schema baseline, not proof that the live legacy database has not changed.

CREATE TABLE `achievements` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `title` varchar(100) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `points` int(11) DEFAULT 0,
  `unlocked_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE `bill_reminders` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `amount` decimal(12,2) DEFAULT 0.00,
  `due_day` tinyint(4) NOT NULL,
  `category` varchar(60) DEFAULT 'Bills',
  `recurrence` enum('monthly','weekly','yearly') DEFAULT 'monthly',
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE `budget` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `category` varchar(50) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `limit_amount` decimal(10,2) DEFAULT NULL,
  `budget_month` varchar(7) NOT NULL,
  `date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE `debts` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `type` enum('owed','borrowed') NOT NULL,
  `due_date` date DEFAULT NULL,
  `status` enum('pending','paid') DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE `expenses` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `category` varchar(50) DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `date` date DEFAULT NULL,
  `expense_month` varchar(7) DEFAULT NULL,
  `tag` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE `financial_notes` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `note_month` varchar(7) NOT NULL,
  `content` text DEFAULT NULL,
  `mood` enum('great','good','okay','bad') DEFAULT 'okay',
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE `income` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `source` varchar(100) DEFAULT NULL,
  `date` date DEFAULT NULL,
  `income_month` varchar(7) DEFAULT NULL,
  `tag` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `status` enum('unread','read') DEFAULT 'unread',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `type` varchar(50) DEFAULT 'general',
  `read_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE `quiz_attempts` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `question_id` varchar(20) NOT NULL,
  `correct` tinyint(1) DEFAULT 0,
  `attempted_at` date DEFAULT curdate()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE `quiz_streaks` (
  `user_id` int(11) NOT NULL,
  `current_streak` int(11) DEFAULT 0,
  `longest_streak` int(11) DEFAULT 0,
  `last_quiz_date` date DEFAULT NULL,
  `total_correct` int(11) DEFAULT 0,
  `total_attempted` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE `recurring_log` (
  `id` int(11) NOT NULL,
  `recurring_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `applied_month` varchar(7) NOT NULL,
  `applied_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
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
CREATE TABLE `savings` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `goal` varchar(100) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `date` date NOT NULL,
  `savings_month` varchar(7) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
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

-- Indexes, AUTO_INCREMENT definitions, and source foreign keys.
ALTER TABLE `achievements`
  ADD PRIMARY KEY (`id`);
ALTER TABLE `bill_reminders`
  ADD PRIMARY KEY (`id`);
ALTER TABLE `budget`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);
ALTER TABLE `debts`
  ADD PRIMARY KEY (`id`);
ALTER TABLE `expenses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);
ALTER TABLE `financial_notes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_month` (`user_id`,`note_month`);
ALTER TABLE `income`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);
ALTER TABLE `quiz_attempts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_attempt` (`user_id`,`question_id`,`attempted_at`);
ALTER TABLE `quiz_streaks`
  ADD PRIMARY KEY (`user_id`);
ALTER TABLE `recurring_log`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_apply` (`recurring_id`,`applied_month`),
  ADD KEY `user_id` (`user_id`);
ALTER TABLE `recurring_transactions`
  ADD PRIMARY KEY (`id`);
ALTER TABLE `savings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);
ALTER TABLE `savings_goals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);
ALTER TABLE `achievements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;
ALTER TABLE `bill_reminders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;
ALTER TABLE `budget`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;
ALTER TABLE `debts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;
ALTER TABLE `expenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;
ALTER TABLE `financial_notes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;
ALTER TABLE `income`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;
ALTER TABLE `quiz_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `recurring_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;
ALTER TABLE `recurring_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;
ALTER TABLE `savings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;
ALTER TABLE `savings_goals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;
ALTER TABLE `budget`
  ADD CONSTRAINT `budget_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);
ALTER TABLE `expenses`
  ADD CONSTRAINT `expenses_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);
ALTER TABLE `income`
  ADD CONSTRAINT `income_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);
ALTER TABLE `savings`
  ADD CONSTRAINT `savings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);
