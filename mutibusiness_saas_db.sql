-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 14, 2026 at 11:57 PM
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
  `owner_id` int(11) NOT NULL,
  `business_name` varchar(150) NOT NULL,
  `slug` varchar(150) NOT NULL,
  `status` enum('active','suspended','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `businesses`
--

INSERT INTO `businesses` (`id`, `owner_id`, `business_name`, `slug`, `status`, `created_at`, `updated_at`) VALUES
(1, 2, 'Sheldonsss Financial', 'my-business-system', 'active', '2026-08-12 19:28:14', '2026-08-12 20:58:58');

-- --------------------------------------------------------

--
-- Table structure for table `business_modules`
--

CREATE TABLE `business_modules` (
  `id` int(11) NOT NULL,
  `business_id` int(11) NOT NULL,
  `module_name` varchar(50) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `business_users`
--

CREATE TABLE `business_users` (
  `id` int(11) NOT NULL,
  `business_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `role` varchar(50) DEFAULT 'cashier',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `business_users`
--

INSERT INTO `business_users` (`id`, `business_id`, `user_id`, `role`, `status`) VALUES
(1, 1, 5, 'business_owner', 'active'),
(3, 1, 4, 'business_owner', 'active'),
(4, 1, 3, 'staff', 'active');

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
  `principal_amount` decimal(10,2) NOT NULL,
  `interest_rate` decimal(5,2) NOT NULL,
  `total_payable` decimal(10,2) NOT NULL,
  `loan_date` date NOT NULL,
  `due_date` date NOT NULL,
  `term_days` int(11) NOT NULL,
  `term_unit` enum('days','months') NOT NULL DEFAULT 'days',
  `payment_type` enum('single','installment') NOT NULL DEFAULT 'installment',
  `payment_frequency` enum('daily','weekly','biweekly','monthly','custom') DEFAULT NULL,
  `number_of_payments` int(11) NOT NULL DEFAULT 1,
  `fixed_payment_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` enum('pending','active','completed','defaulted') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `loans`
--

INSERT INTO `loans` (`id`, `business_id`, `created_by`, `account_id`, `reference_number`, `borrower_id`, `principal_amount`, `interest_rate`, `total_payable`, `loan_date`, `due_date`, `term_days`, `term_unit`, `payment_type`, `payment_frequency`, `number_of_payments`, `fixed_payment_amount`, `status`, `created_at`) VALUES
(7, 1, 2, 3, NULL, 3, 100.00, 0.00, 100.00, '2026-08-14', '2026-12-14', 4, 'months', 'installment', 'monthly', 1, 25.00, 'active', '2026-08-14 21:36:04');

-- --------------------------------------------------------

--
-- Table structure for table `loan_accounts`
--

CREATE TABLE `loan_accounts` (
  `id` int(11) NOT NULL,
  `business_id` int(11) NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `account_name` varchar(100) NOT NULL,
  `balance` decimal(12,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `loan_accounts`
--

INSERT INTO `loan_accounts` (`id`, `business_id`, `created_by`, `user_id`, `account_name`, `balance`, `created_at`) VALUES
(3, 1, 2, NULL, 'Gcash', 950.00, '2026-08-14 21:35:54');

-- --------------------------------------------------------

--
-- Table structure for table `loan_account_transactions`
--

CREATE TABLE `loan_account_transactions` (
  `id` int(11) NOT NULL,
  `business_id` int(11) NOT NULL,
  `account_id` int(11) NOT NULL,
  `type` enum('DEBIT','CREDIT') NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `description` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `loan_account_transactions`
--

INSERT INTO `loan_account_transactions` (`id`, `business_id`, `account_id`, `type`, `amount`, `description`, `created_at`) VALUES
(13, 1, 3, 'DEBIT', 100.00, 'Loan #7 disbursement', '2026-08-14 21:36:04'),
(14, 1, 3, 'CREDIT', 25.00, 'Payment received for Loan #7', '2026-08-14 21:36:10'),
(15, 1, 3, 'CREDIT', 25.00, 'Payment received for Loan #7', '2026-08-14 21:38:52');

-- --------------------------------------------------------

--
-- Table structure for table `loan_borrowers`
--

CREATE TABLE `loan_borrowers` (
  `id` int(11) NOT NULL,
  `business_id` int(11) NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `contact_no` varchar(50) NOT NULL,
  `address` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `loan_borrowers`
--

INSERT INTO `loan_borrowers` (`id`, `business_id`, `created_by`, `first_name`, `last_name`, `contact_no`, `address`, `created_at`) VALUES
(3, 1, 2, 'Marldohn', 'Rubinos', '09061941138', 'Jakosalem', '2026-08-14 21:35:45');

-- --------------------------------------------------------

--
-- Table structure for table `loan_collaterals`
--

CREATE TABLE `loan_collaterals` (
  `id` int(11) NOT NULL,
  `business_id` int(11) NOT NULL,
  `loan_id` int(11) NOT NULL,
  `item_name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `estimated_value` decimal(12,2) DEFAULT 0.00,
  `image_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `loan_collections`
--

CREATE TABLE `loan_collections` (
  `id` int(11) NOT NULL,
  `loan_id` int(11) NOT NULL,
  `collector_id` int(11) NOT NULL,
  `amount_paid` decimal(10,2) NOT NULL,
  `collection_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `payment_amount` decimal(10,2) NOT NULL,
  `payment_date` date NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `loan_payments`
--

INSERT INTO `loan_payments` (`id`, `business_id`, `created_by`, `loan_id`, `schedule_id`, `payment_amount`, `payment_date`, `notes`, `created_at`) VALUES
(7, 1, 2, 7, NULL, 25.00, '2026-08-14', '', '2026-08-14 21:36:10'),
(8, 1, 2, 7, NULL, 25.00, '2026-08-14', '', '2026-08-14 21:38:52');

-- --------------------------------------------------------

--
-- Table structure for table `loan_schedules`
--

CREATE TABLE `loan_schedules` (
  `id` int(11) NOT NULL,
  `loan_id` int(11) NOT NULL,
  `due_date` date NOT NULL,
  `amount_due` decimal(10,2) NOT NULL,
  `status` enum('unpaid','partially_paid','paid') DEFAULT 'unpaid'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `loan_schedules`
--

INSERT INTO `loan_schedules` (`id`, `loan_id`, `due_date`, `amount_due`, `status`) VALUES
(5, 7, '2026-09-14', 25.00, 'paid'),
(6, 7, '2026-10-14', 25.00, 'paid'),
(7, 7, '2026-11-14', 25.00, 'unpaid'),
(8, 7, '2026-12-14', 25.00, 'unpaid');

-- --------------------------------------------------------

--
-- Table structure for table `plans`
--

CREATE TABLE `plans` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `max_businesses` int(11) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pos_categories`
--

CREATE TABLE `pos_categories` (
  `id` int(11) NOT NULL,
  `business_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pos_products`
--

CREATE TABLE `pos_products` (
  `id` int(11) NOT NULL,
  `business_id` int(11) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  `sku` varchar(100) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `cost` decimal(10,2) NOT NULL,
  `stock_qty` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pos_transactions`
--

CREATE TABLE `pos_transactions` (
  `id` int(11) NOT NULL,
  `business_id` int(11) NOT NULL,
  `cashier_id` int(11) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `payment_method` varchar(50) DEFAULT 'cash',
  `amount_tendered` decimal(10,2) NOT NULL,
  `change_amount` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pos_transaction_items`
--

CREATE TABLE `pos_transaction_items` (
  `id` int(11) NOT NULL,
  `transaction_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `subscriptions`
--

CREATE TABLE `subscriptions` (
  `id` int(11) NOT NULL,
  `business_id` int(11) NOT NULL,
  `plan_id` int(11) NOT NULL,
  `status` enum('active','trialing','canceled','expired') DEFAULT 'active',
  `current_period_end` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `subscription_payments`
--

CREATE TABLE `subscription_payments` (
  `id` int(11) NOT NULL,
  `business_id` int(11) NOT NULL,
  `subscription_id` int(11) NOT NULL,
  `plan_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_date` datetime NOT NULL,
  `period_start` datetime DEFAULT NULL,
  `period_end` datetime DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `reference_number` varchar(100) DEFAULT NULL,
  `status` enum('pending','paid','failed','refunded') DEFAULT 'paid',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('super_admin','business_owner','staff') DEFAULT 'business_owner',
  `is_approved` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `plan_id` int(11) DEFAULT 1,
  `subscription_status` enum('active','trialing','canceled','expired') DEFAULT 'active',
  `subscription_ends_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `is_approved`, `created_at`, `updated_at`, `plan_id`, `subscription_status`, `subscription_ends_at`) VALUES
(1, 'Admin User', 'mrubinos@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'business_owner', 0, '2026-08-12 18:57:13', '2026-08-14 18:21:51', 1, 'active', NULL),
(2, 'Admin User', 'admin@gmail.com', '$2y$10$KMw9ifujJT.cmU397ix/s.iqG0Gk9tJ1lhHt4RdsKWj2taPnTYlDi', 'super_admin', 1, '2026-08-12 19:19:20', '2026-08-14 18:16:20', 1, 'active', NULL),
(3, 'admin admin', 'admin2@gmail.com', '$2y$10$fkot.Epwmslgq5PcWuNWhOJ7yniXtSkTP81SS7kILcwnLk/WutUUi', '', 1, '2026-08-14 18:05:47', '2026-08-14 18:17:27', 1, 'active', NULL),
(4, 'admin admin', 'admin3@gmail.com', '$2y$10$UParFLWOPnqSfmnFspKKSuWMqrFAHwmp1.UiZF8Rlbw4f.rIF0e.C', 'business_owner', 1, '2026-08-14 18:11:35', '2026-08-14 18:55:23', 1, 'active', NULL),
(5, 'admin admin', 'admin4@gmail.com', '$2y$10$rEv7rJSB5UiHt.4oylFQRuwK0JOJm6cmww6ES0y0aruwaYwspEkma', 'business_owner', 1, '2026-08-14 18:15:38', '2026-08-14 18:54:38', 1, 'active', NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `businesses`
--
ALTER TABLE `businesses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `owner_id` (`owner_id`);

--
-- Indexes for table `business_modules`
--
ALTER TABLE `business_modules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `business_id` (`business_id`);

--
-- Indexes for table `business_users`
--
ALTER TABLE `business_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_business_user` (`business_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `loans`
--
ALTER TABLE `loans`
  ADD PRIMARY KEY (`id`),
  ADD KEY `business_id` (`business_id`),
  ADD KEY `borrower_id` (`borrower_id`),
  ADD KEY `idx_loans_created_by` (`created_by`);

--
-- Indexes for table `loan_accounts`
--
ALTER TABLE `loan_accounts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_loan_accounts_created_by` (`created_by`);

--
-- Indexes for table `loan_account_transactions`
--
ALTER TABLE `loan_account_transactions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `loan_borrowers`
--
ALTER TABLE `loan_borrowers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `business_id` (`business_id`);

--
-- Indexes for table `loan_collaterals`
--
ALTER TABLE `loan_collaterals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `loan_id` (`loan_id`);

--
-- Indexes for table `loan_collections`
--
ALTER TABLE `loan_collections`
  ADD PRIMARY KEY (`id`),
  ADD KEY `loan_id` (`loan_id`),
  ADD KEY `collector_id` (`collector_id`);

--
-- Indexes for table `loan_payments`
--
ALTER TABLE `loan_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `loan_id` (`loan_id`);

--
-- Indexes for table `loan_schedules`
--
ALTER TABLE `loan_schedules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `loan_id` (`loan_id`);

--
-- Indexes for table `plans`
--
ALTER TABLE `plans`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `pos_categories`
--
ALTER TABLE `pos_categories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `business_id` (`business_id`);

--
-- Indexes for table `pos_products`
--
ALTER TABLE `pos_products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `sku` (`sku`),
  ADD KEY `business_id` (`business_id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `pos_transactions`
--
ALTER TABLE `pos_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `business_id` (`business_id`),
  ADD KEY `cashier_id` (`cashier_id`);

--
-- Indexes for table `pos_transaction_items`
--
ALTER TABLE `pos_transaction_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `transaction_id` (`transaction_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `subscriptions`
--
ALTER TABLE `subscriptions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `business_id` (`business_id`),
  ADD KEY `plan_id` (`plan_id`);

--
-- Indexes for table `subscription_payments`
--
ALTER TABLE `subscription_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `business_id` (`business_id`),
  ADD KEY `subscription_id` (`subscription_id`),
  ADD KEY `plan_id` (`plan_id`);

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
-- AUTO_INCREMENT for table `businesses`
--
ALTER TABLE `businesses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `business_modules`
--
ALTER TABLE `business_modules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `business_users`
--
ALTER TABLE `business_users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `loans`
--
ALTER TABLE `loans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `loan_accounts`
--
ALTER TABLE `loan_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `loan_account_transactions`
--
ALTER TABLE `loan_account_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `loan_borrowers`
--
ALTER TABLE `loan_borrowers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `loan_collaterals`
--
ALTER TABLE `loan_collaterals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `loan_collections`
--
ALTER TABLE `loan_collections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `loan_payments`
--
ALTER TABLE `loan_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `loan_schedules`
--
ALTER TABLE `loan_schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `plans`
--
ALTER TABLE `plans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pos_categories`
--
ALTER TABLE `pos_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pos_products`
--
ALTER TABLE `pos_products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pos_transactions`
--
ALTER TABLE `pos_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pos_transaction_items`
--
ALTER TABLE `pos_transaction_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `subscriptions`
--
ALTER TABLE `subscriptions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `subscription_payments`
--
ALTER TABLE `subscription_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `businesses`
--
ALTER TABLE `businesses`
  ADD CONSTRAINT `businesses_ibfk_1` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `business_modules`
--
ALTER TABLE `business_modules`
  ADD CONSTRAINT `business_modules_ibfk_1` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `business_users`
--
ALTER TABLE `business_users`
  ADD CONSTRAINT `business_users_ibfk_1` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `business_users_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `loans`
--
ALTER TABLE `loans`
  ADD CONSTRAINT `loans_ibfk_1` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `loans_ibfk_2` FOREIGN KEY (`borrower_id`) REFERENCES `loan_borrowers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `loan_borrowers`
--
ALTER TABLE `loan_borrowers`
  ADD CONSTRAINT `loan_borrowers_ibfk_1` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `loan_collaterals`
--
ALTER TABLE `loan_collaterals`
  ADD CONSTRAINT `loan_collaterals_ibfk_1` FOREIGN KEY (`loan_id`) REFERENCES `loans` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `loan_collections`
--
ALTER TABLE `loan_collections`
  ADD CONSTRAINT `loan_collections_ibfk_1` FOREIGN KEY (`loan_id`) REFERENCES `loans` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `loan_collections_ibfk_2` FOREIGN KEY (`collector_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `loan_payments`
--
ALTER TABLE `loan_payments`
  ADD CONSTRAINT `loan_payments_ibfk_1` FOREIGN KEY (`loan_id`) REFERENCES `loans` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `loan_schedules`
--
ALTER TABLE `loan_schedules`
  ADD CONSTRAINT `loan_schedules_ibfk_1` FOREIGN KEY (`loan_id`) REFERENCES `loans` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `pos_categories`
--
ALTER TABLE `pos_categories`
  ADD CONSTRAINT `pos_categories_ibfk_1` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `pos_products`
--
ALTER TABLE `pos_products`
  ADD CONSTRAINT `pos_products_ibfk_1` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `pos_products_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `pos_categories` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `pos_transactions`
--
ALTER TABLE `pos_transactions`
  ADD CONSTRAINT `pos_transactions_ibfk_1` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `pos_transactions_ibfk_2` FOREIGN KEY (`cashier_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `pos_transaction_items`
--
ALTER TABLE `pos_transaction_items`
  ADD CONSTRAINT `pos_transaction_items_ibfk_1` FOREIGN KEY (`transaction_id`) REFERENCES `pos_transactions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `pos_transaction_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `pos_products` (`id`);

--
-- Constraints for table `subscriptions`
--
ALTER TABLE `subscriptions`
  ADD CONSTRAINT `subscriptions_ibfk_1` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `subscriptions_ibfk_2` FOREIGN KEY (`plan_id`) REFERENCES `plans` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
