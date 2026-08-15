-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Aug 15, 2026 at 03:39 AM
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
-- Database: `multibusiness_saas`
--

-- --------------------------------------------------------

--
-- Table structure for table `businesses`
--

CREATE TABLE `businesses` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `businesses`
--

INSERT INTO `businesses` (`id`, `name`, `email`, `phone`, `address`, `status`, `created_at`, `updated_at`) VALUES
(1, 'My First Business', 'owner@gmail.com', '', '', 'active', '2026-08-14 23:48:06', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `business_users`
--

CREATE TABLE `business_users` (
  `id` int(11) NOT NULL,
  `business_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `role` enum('owner','admin','staff') NOT NULL DEFAULT 'staff',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `business_users`
--

INSERT INTO `business_users` (`id`, `business_id`, `user_id`, `role`, `status`, `created_at`) VALUES
(1, 1, 2, 'owner', 'active', '2026-08-14 23:48:15'),
(2, 1, 3, 'admin', 'active', '2026-08-15 01:02:36'),
(3, 1, 4, 'admin', 'active', '2026-08-15 01:04:14');

-- --------------------------------------------------------

--
-- Table structure for table `loans`
--

CREATE TABLE `loans` (
  `id` int(11) NOT NULL,
  `business_id` int(11) NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `account_id` int(11) NOT NULL,
  `reference_number` varchar(100) DEFAULT NULL,
  `borrower_id` int(11) NOT NULL,
  `principal_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `interest_rate` decimal(8,2) NOT NULL DEFAULT 0.00,
  `total_payable` decimal(12,2) NOT NULL DEFAULT 0.00,
  `loan_date` date NOT NULL,
  `due_date` date NOT NULL,
  `term_days` int(11) DEFAULT NULL,
  `term_unit` enum('days','weeks','months','years') NOT NULL DEFAULT 'months',
  `payment_type` enum('installment','lump_sum') NOT NULL DEFAULT 'installment',
  `payment_frequency` enum('daily','weekly','biweekly','monthly','quarterly','yearly') DEFAULT NULL,
  `number_of_payments` int(11) DEFAULT NULL,
  `fixed_payment_amount` decimal(12,2) DEFAULT NULL,
  `status` enum('pending','active','completed','overdue','cancelled') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `loans`
--

INSERT INTO `loans` (`id`, `business_id`, `created_by`, `account_id`, `reference_number`, `borrower_id`, `principal_amount`, `interest_rate`, `total_payable`, `loan_date`, `due_date`, `term_days`, `term_unit`, `payment_type`, `payment_frequency`, `number_of_payments`, `fixed_payment_amount`, `status`, `created_at`, `updated_at`) VALUES
(2, 1, 1, 1, '123123', 1, 1000.00, 0.00, 1000.00, '2026-08-15', '2026-08-30', 15, 'days', 'installment', 'monthly', NULL, 1000.00, 'completed', '2026-08-14 23:59:04', '2026-08-15 00:03:03'),
(3, 1, 1, 1, NULL, 1, 1000.00, 0.00, 1000.00, '2026-08-15', '2026-12-15', 4, 'months', 'installment', 'monthly', NULL, 250.00, 'active', '2026-08-15 00:00:52', NULL),
(4, 1, 1, 1, NULL, 1, 500.00, 0.00, 500.00, '2026-08-15', '2026-12-15', 4, 'months', 'installment', 'monthly', NULL, 125.00, 'active', '2026-08-15 00:01:01', NULL),
(5, 1, 1, 1, NULL, 1, 500.00, 0.00, 500.00, '2026-08-15', '2026-12-15', 4, 'months', 'installment', 'monthly', NULL, 125.00, 'active', '2026-08-15 00:06:09', NULL),
(6, 1, 1, 1, NULL, 1, 500.00, 0.00, 500.00, '2026-08-15', '2026-12-15', 4, 'months', 'installment', 'monthly', NULL, 125.00, 'active', '2026-08-15 00:21:50', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `loan_accounts`
--

CREATE TABLE `loan_accounts` (
  `id` int(11) NOT NULL,
  `business_id` int(11) NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `account_name` varchar(150) NOT NULL,
  `account_type` enum('cash','bank','e_wallet','other') NOT NULL DEFAULT 'cash',
  `balance` decimal(12,2) NOT NULL DEFAULT 0.00,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `loan_accounts`
--

INSERT INTO `loan_accounts` (`id`, `business_id`, `created_by`, `account_name`, `account_type`, `balance`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 'Gcash', 'cash', 7500.00, 'active', '2026-08-14 23:54:23', '2026-08-15 00:21:50');

-- --------------------------------------------------------

--
-- Table structure for table `loan_account_transactions`
--

CREATE TABLE `loan_account_transactions` (
  `id` int(11) NOT NULL,
  `business_id` int(11) NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `account_id` int(11) NOT NULL,
  `loan_id` int(11) DEFAULT NULL,
  `payment_id` int(11) DEFAULT NULL,
  `type` enum('CREDIT','DEBIT') NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `description` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `loan_account_transactions`
--

INSERT INTO `loan_account_transactions` (`id`, `business_id`, `created_by`, `account_id`, `loan_id`, `payment_id`, `type`, `amount`, `description`, `created_at`) VALUES
(1, 1, NULL, 1, NULL, NULL, 'DEBIT', 1000.00, 'Loan #2 disbursement', '2026-08-14 23:59:04'),
(2, 1, NULL, 1, NULL, NULL, 'DEBIT', 1000.00, 'Loan #3 disbursement', '2026-08-15 00:00:52'),
(3, 1, NULL, 1, NULL, NULL, 'DEBIT', 500.00, 'Loan #4 disbursement', '2026-08-15 00:01:01'),
(4, 1, NULL, 1, NULL, NULL, 'CREDIT', 1000.00, 'Payment received for Loan #2', '2026-08-15 00:03:03'),
(5, 1, NULL, 1, NULL, NULL, 'DEBIT', 500.00, 'Loan #5 disbursement', '2026-08-15 00:06:09'),
(6, 1, NULL, 1, NULL, NULL, 'DEBIT', 500.00, 'Loan #6 disbursement', '2026-08-15 00:21:50');

-- --------------------------------------------------------

--
-- Table structure for table `loan_account_transfers`
--

CREATE TABLE `loan_account_transfers` (
  `id` int(11) NOT NULL,
  `business_id` int(11) NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `from_account_id` int(11) NOT NULL,
  `to_account_id` int(11) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `transfer_date` date NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `loan_borrowers`
--

CREATE TABLE `loan_borrowers` (
  `id` int(11) NOT NULL,
  `business_id` int(11) NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `first_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `occupation` varchar(150) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `loan_borrowers`
--

INSERT INTO `loan_borrowers` (`id`, `business_id`, `created_by`, `first_name`, `middle_name`, `last_name`, `email`, `phone`, `address`, `date_of_birth`, `occupation`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 'Marldohn', NULL, 'Rubinos', NULL, '09061941138', 'Jakosalem Street', NULL, NULL, 'active', '2026-08-14 23:54:09', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `loan_collaterals`
--

CREATE TABLE `loan_collaterals` (
  `id` int(10) UNSIGNED NOT NULL,
  `business_id` int(10) UNSIGNED NOT NULL,
  `loan_id` int(10) UNSIGNED NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `estimated_value` decimal(15,2) NOT NULL DEFAULT 0.00,
  `image_path` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `loan_collaterals`
--

INSERT INTO `loan_collaterals` (`id`, `business_id`, `loan_id`, `item_name`, `description`, `estimated_value`, `image_path`, `created_at`, `updated_at`) VALUES
(1, 1, 6, 'Picture', '', 100.00, 'uploads/collaterals/col_6_1786753310.jpg', '2026-08-15 00:21:50', '2026-08-15 00:21:50');

-- --------------------------------------------------------

--
-- Table structure for table `loan_expenses`
--

CREATE TABLE `loan_expenses` (
  `id` int(11) NOT NULL,
  `business_id` int(11) NOT NULL,
  `account_id` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `description` varchar(255) NOT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `expense_date` date NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `loan_guarantors`
--

CREATE TABLE `loan_guarantors` (
  `id` int(11) NOT NULL,
  `business_id` int(11) NOT NULL,
  `loan_id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `relationship` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `loan_payments`
--

CREATE TABLE `loan_payments` (
  `id` int(11) NOT NULL,
  `business_id` int(11) NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `loan_id` int(11) NOT NULL,
  `schedule_id` int(11) DEFAULT NULL,
  `account_id` int(11) NOT NULL,
  `payment_amount` decimal(12,2) NOT NULL,
  `payment_date` date NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `loan_payments`
--

INSERT INTO `loan_payments` (`id`, `business_id`, `created_by`, `loan_id`, `schedule_id`, `account_id`, `payment_amount`, `payment_date`, `notes`, `created_at`) VALUES
(2, 1, NULL, 2, NULL, 1, 1000.00, '2026-08-15', '', '2026-08-15 00:03:03');

-- --------------------------------------------------------

--
-- Table structure for table `loan_penalties`
--

CREATE TABLE `loan_penalties` (
  `id` int(11) NOT NULL,
  `business_id` int(11) NOT NULL,
  `loan_id` int(11) NOT NULL,
  `schedule_id` int(11) DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `reason` varchar(255) DEFAULT NULL,
  `penalty_date` date NOT NULL,
  `status` enum('unpaid','paid','waived') NOT NULL DEFAULT 'unpaid',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `loan_schedules`
--

CREATE TABLE `loan_schedules` (
  `id` int(11) NOT NULL,
  `business_id` int(11) NOT NULL,
  `loan_id` int(11) NOT NULL,
  `installment_number` int(11) NOT NULL,
  `due_date` date NOT NULL,
  `amount_due` decimal(12,2) NOT NULL DEFAULT 0.00,
  `amount_paid` decimal(12,2) NOT NULL DEFAULT 0.00,
  `balance_due` decimal(12,2) NOT NULL DEFAULT 0.00,
  `status` enum('unpaid','partially_paid','paid','overdue') NOT NULL DEFAULT 'unpaid',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `loan_schedules`
--

INSERT INTO `loan_schedules` (`id`, `business_id`, `loan_id`, `installment_number`, `due_date`, `amount_due`, `amount_paid`, `balance_due`, `status`, `created_at`, `updated_at`) VALUES
(2, 1, 2, 0, '2026-09-15', 1000.00, 0.00, 0.00, 'paid', '2026-08-14 23:59:04', '2026-08-15 00:03:03'),
(3, 1, 3, 0, '2026-09-15', 250.00, 0.00, 0.00, 'unpaid', '2026-08-15 00:00:52', NULL),
(4, 1, 3, 0, '2026-10-15', 250.00, 0.00, 0.00, 'unpaid', '2026-08-15 00:00:52', NULL),
(5, 1, 3, 0, '2026-11-15', 250.00, 0.00, 0.00, 'unpaid', '2026-08-15 00:00:52', NULL),
(6, 1, 3, 0, '2026-12-15', 250.00, 0.00, 0.00, 'unpaid', '2026-08-15 00:00:52', NULL),
(7, 1, 4, 0, '2026-09-15', 125.00, 0.00, 0.00, 'unpaid', '2026-08-15 00:01:01', NULL),
(8, 1, 4, 0, '2026-10-15', 125.00, 0.00, 0.00, 'unpaid', '2026-08-15 00:01:01', NULL),
(9, 1, 4, 0, '2026-11-15', 125.00, 0.00, 0.00, 'unpaid', '2026-08-15 00:01:01', NULL),
(10, 1, 4, 0, '2026-12-15', 125.00, 0.00, 0.00, 'unpaid', '2026-08-15 00:01:01', NULL),
(11, 1, 5, 0, '2026-09-15', 125.00, 0.00, 0.00, 'unpaid', '2026-08-15 00:06:09', NULL),
(12, 1, 5, 0, '2026-10-15', 125.00, 0.00, 0.00, 'unpaid', '2026-08-15 00:06:09', NULL),
(13, 1, 5, 0, '2026-11-15', 125.00, 0.00, 0.00, 'unpaid', '2026-08-15 00:06:09', NULL),
(14, 1, 5, 0, '2026-12-15', 125.00, 0.00, 0.00, 'unpaid', '2026-08-15 00:06:09', NULL),
(15, 1, 6, 0, '2026-09-15', 125.00, 0.00, 0.00, 'unpaid', '2026-08-15 00:21:50', NULL),
(16, 1, 6, 0, '2026-10-15', 125.00, 0.00, 0.00, 'unpaid', '2026-08-15 00:21:50', NULL),
(17, 1, 6, 0, '2026-11-15', 125.00, 0.00, 0.00, 'unpaid', '2026-08-15 00:21:50', NULL),
(18, 1, 6, 0, '2026-12-15', 125.00, 0.00, 0.00, 'unpaid', '2026-08-15 00:21:50', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('super_admin','admin','staff') NOT NULL DEFAULT 'staff',
  `status` enum('active','inactive','pending') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `status`, `created_at`, `updated_at`) VALUES
(1, 'System Admin', 'admin@gmail.com', '$2y$10$KMw9ifujJT.cmU397ix/s.iqG0Gk9tJ1lhHt4RdsKWj2taPnTYlDi', 'super_admin', 'active', '2026-08-14 23:47:50', NULL),
(2, 'Business Owner', 'owner@gmail.com', '$2y$10$KMw9ifujJT.cmU397ix/s.iqG0Gk9tJ1lhHt4RdsKWj2taPnTYlDi', 'admin', 'active', '2026-08-14 23:47:58', '2026-08-14 23:56:13'),
(3, 'Marldohn Rubinos', 'mrubinos@azpired.net', '$2y$10$rS3TrrKiKBs4dbouYlmqWeQw3LsKeb0lT48k8BbFa/qvoJ9FsPr0G', 'staff', 'active', '2026-08-15 00:43:21', '2026-08-15 00:44:31'),
(4, 'March Shelou', 'mardillo@azpired.net', '$2y$10$m0Vm1Al2Gxo87D9Ev.o89OAYbejOa1lcw55j1ODa4pl2uk1.vAC1K', 'admin', 'active', '2026-08-15 01:02:08', '2026-08-15 01:04:11');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `businesses`
--
ALTER TABLE `businesses`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `business_users`
--
ALTER TABLE `business_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_business_user` (`business_id`,`user_id`),
  ADD KEY `idx_business_users_business` (`business_id`),
  ADD KEY `idx_business_users_user` (`user_id`);

--
-- Indexes for table `loans`
--
ALTER TABLE `loans`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_loans_business_reference` (`business_id`,`reference_number`),
  ADD KEY `idx_loans_business` (`business_id`),
  ADD KEY `idx_loans_created_by` (`created_by`),
  ADD KEY `idx_loans_borrower` (`borrower_id`),
  ADD KEY `idx_loans_account` (`account_id`);

--
-- Indexes for table `loan_accounts`
--
ALTER TABLE `loan_accounts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_loan_accounts_business` (`business_id`),
  ADD KEY `idx_loan_accounts_created_by` (`created_by`);

--
-- Indexes for table `loan_account_transactions`
--
ALTER TABLE `loan_account_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_loan_account_transactions_business` (`business_id`),
  ADD KEY `idx_loan_account_transactions_account` (`account_id`),
  ADD KEY `idx_loan_account_transactions_loan` (`loan_id`),
  ADD KEY `idx_loan_account_transactions_payment` (`payment_id`),
  ADD KEY `idx_loan_account_transactions_created_by` (`created_by`);

--
-- Indexes for table `loan_account_transfers`
--
ALTER TABLE `loan_account_transfers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_loan_account_transfers_business` (`business_id`),
  ADD KEY `idx_loan_account_transfers_from` (`from_account_id`),
  ADD KEY `idx_loan_account_transfers_to` (`to_account_id`),
  ADD KEY `idx_loan_account_transfers_created_by` (`created_by`);

--
-- Indexes for table `loan_borrowers`
--
ALTER TABLE `loan_borrowers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_loan_borrowers_business` (`business_id`),
  ADD KEY `idx_loan_borrowers_created_by` (`created_by`);

--
-- Indexes for table `loan_collaterals`
--
ALTER TABLE `loan_collaterals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_business_id` (`business_id`),
  ADD KEY `idx_loan_id` (`loan_id`);

--
-- Indexes for table `loan_expenses`
--
ALTER TABLE `loan_expenses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_loan_expenses_business` (`business_id`),
  ADD KEY `idx_loan_expenses_account` (`account_id`),
  ADD KEY `idx_loan_expenses_created_by` (`created_by`);

--
-- Indexes for table `loan_guarantors`
--
ALTER TABLE `loan_guarantors`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_loan_guarantors_business` (`business_id`),
  ADD KEY `idx_loan_guarantors_loan` (`loan_id`);

--
-- Indexes for table `loan_payments`
--
ALTER TABLE `loan_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_loan_payments_business` (`business_id`),
  ADD KEY `idx_loan_payments_created_by` (`created_by`),
  ADD KEY `idx_loan_payments_loan` (`loan_id`),
  ADD KEY `idx_loan_payments_schedule` (`schedule_id`),
  ADD KEY `idx_loan_payments_account` (`account_id`);

--
-- Indexes for table `loan_penalties`
--
ALTER TABLE `loan_penalties`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_loan_penalties_business` (`business_id`),
  ADD KEY `idx_loan_penalties_loan` (`loan_id`),
  ADD KEY `idx_loan_penalties_schedule` (`schedule_id`),
  ADD KEY `idx_loan_penalties_created_by` (`created_by`);

--
-- Indexes for table `loan_schedules`
--
ALTER TABLE `loan_schedules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_loan_schedules_business` (`business_id`),
  ADD KEY `idx_loan_schedules_loan` (`loan_id`),
  ADD KEY `idx_loan_schedules_due_date` (`due_date`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_users_email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `businesses`
--
ALTER TABLE `businesses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `business_users`
--
ALTER TABLE `business_users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `loans`
--
ALTER TABLE `loans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `loan_accounts`
--
ALTER TABLE `loan_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `loan_account_transactions`
--
ALTER TABLE `loan_account_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `loan_account_transfers`
--
ALTER TABLE `loan_account_transfers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `loan_borrowers`
--
ALTER TABLE `loan_borrowers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `loan_collaterals`
--
ALTER TABLE `loan_collaterals`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `loan_expenses`
--
ALTER TABLE `loan_expenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `loan_guarantors`
--
ALTER TABLE `loan_guarantors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `loan_payments`
--
ALTER TABLE `loan_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `loan_penalties`
--
ALTER TABLE `loan_penalties`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `loan_schedules`
--
ALTER TABLE `loan_schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `business_users`
--
ALTER TABLE `business_users`
  ADD CONSTRAINT `fk_business_users_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_business_users_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `loans`
--
ALTER TABLE `loans`
  ADD CONSTRAINT `fk_loans_account` FOREIGN KEY (`account_id`) REFERENCES `loan_accounts` (`id`),
  ADD CONSTRAINT `fk_loans_borrower` FOREIGN KEY (`borrower_id`) REFERENCES `loan_borrowers` (`id`),
  ADD CONSTRAINT `fk_loans_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_loans_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `loan_accounts`
--
ALTER TABLE `loan_accounts`
  ADD CONSTRAINT `fk_loan_accounts_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_loan_accounts_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `loan_account_transactions`
--
ALTER TABLE `loan_account_transactions`
  ADD CONSTRAINT `fk_loan_account_transactions_account` FOREIGN KEY (`account_id`) REFERENCES `loan_accounts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_loan_account_transactions_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_loan_account_transactions_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_loan_account_transactions_loan` FOREIGN KEY (`loan_id`) REFERENCES `loans` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_loan_account_transactions_payment` FOREIGN KEY (`payment_id`) REFERENCES `loan_payments` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `loan_account_transfers`
--
ALTER TABLE `loan_account_transfers`
  ADD CONSTRAINT `fk_loan_account_transfers_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_loan_account_transfers_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_loan_account_transfers_from` FOREIGN KEY (`from_account_id`) REFERENCES `loan_accounts` (`id`),
  ADD CONSTRAINT `fk_loan_account_transfers_to` FOREIGN KEY (`to_account_id`) REFERENCES `loan_accounts` (`id`);

--
-- Constraints for table `loan_borrowers`
--
ALTER TABLE `loan_borrowers`
  ADD CONSTRAINT `fk_loan_borrowers_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_loan_borrowers_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `loan_expenses`
--
ALTER TABLE `loan_expenses`
  ADD CONSTRAINT `fk_loan_expenses_account` FOREIGN KEY (`account_id`) REFERENCES `loan_accounts` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_loan_expenses_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_loan_expenses_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `loan_guarantors`
--
ALTER TABLE `loan_guarantors`
  ADD CONSTRAINT `fk_loan_guarantors_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_loan_guarantors_loan` FOREIGN KEY (`loan_id`) REFERENCES `loans` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `loan_payments`
--
ALTER TABLE `loan_payments`
  ADD CONSTRAINT `fk_loan_payments_account` FOREIGN KEY (`account_id`) REFERENCES `loan_accounts` (`id`),
  ADD CONSTRAINT `fk_loan_payments_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_loan_payments_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_loan_payments_loan` FOREIGN KEY (`loan_id`) REFERENCES `loans` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_loan_payments_schedule` FOREIGN KEY (`schedule_id`) REFERENCES `loan_schedules` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `loan_penalties`
--
ALTER TABLE `loan_penalties`
  ADD CONSTRAINT `fk_loan_penalties_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_loan_penalties_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_loan_penalties_loan` FOREIGN KEY (`loan_id`) REFERENCES `loans` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_loan_penalties_schedule` FOREIGN KEY (`schedule_id`) REFERENCES `loan_schedules` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `loan_schedules`
--
ALTER TABLE `loan_schedules`
  ADD CONSTRAINT `fk_loan_schedules_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_loan_schedules_loan` FOREIGN KEY (`loan_id`) REFERENCES `loans` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
