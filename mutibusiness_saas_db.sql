-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Aug 19, 2026 at 11:31 AM
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
(1, 'Loan Management System', 'owner@gmail.com', '', '', 'active', '2026-08-14 23:48:06', '2026-08-17 10:21:08'),
(2, 'Inventory Management System', 'inventory@gmail.com', NULL, NULL, 'active', '2026-08-17 10:22:18', NULL),
(3, 'POS Management', 'POS@gmail.com', NULL, NULL, 'active', '2026-08-17 10:27:37', NULL);

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
(3, 1, 4, 'admin', 'active', '2026-08-15 01:04:14'),
(4, 1, 5, 'admin', 'active', '2026-08-16 21:17:06'),
(6, 2, 4, 'admin', 'active', '2026-08-17 10:27:46'),
(7, 3, 4, 'admin', 'active', '2026-08-17 10:27:46'),
(8, 2, 5, 'admin', 'active', '2026-08-17 14:53:17');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_adjustments`
--

CREATE TABLE `inventory_adjustments` (
  `id` int(11) NOT NULL,
  `business_id` int(11) NOT NULL,
  `adjustment_number` varchar(100) NOT NULL,
  `adjustment_date` date NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `status` enum('draft','completed','cancelled') NOT NULL DEFAULT 'draft',
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inventory_adjustment_items`
--

CREATE TABLE `inventory_adjustment_items` (
  `id` int(11) NOT NULL,
  `adjustment_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `system_quantity` decimal(12,2) NOT NULL DEFAULT 0.00,
  `actual_quantity` decimal(12,2) NOT NULL DEFAULT 0.00,
  `difference` decimal(12,2) NOT NULL DEFAULT 0.00,
  `reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inventory_brands`
--

CREATE TABLE `inventory_brands` (
  `id` int(11) NOT NULL,
  `business_id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inventory_categories`
--

CREATE TABLE `inventory_categories` (
  `id` int(11) NOT NULL,
  `business_id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory_categories`
--

INSERT INTO `inventory_categories` (`id`, `business_id`, `name`, `description`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 2, 'Vape', 'Vape', 'active', 1, '2026-08-17 19:22:09', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `inventory_products`
--

CREATE TABLE `inventory_products` (
  `id` int(11) NOT NULL,
  `business_id` int(11) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `brand_id` int(11) DEFAULT NULL,
  `unit_id` int(11) DEFAULT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `name` varchar(200) NOT NULL,
  `sku` varchar(100) DEFAULT NULL,
  `barcode` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `cost_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `selling_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `wholesale_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `minimum_stock` decimal(12,2) NOT NULL DEFAULT 0.00,
  `maximum_stock` decimal(12,2) DEFAULT NULL,
  `current_stock` decimal(12,2) NOT NULL DEFAULT 0.00,
  `image` varchar(500) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory_products`
--

INSERT INTO `inventory_products` (`id`, `business_id`, `category_id`, `brand_id`, `unit_id`, `supplier_id`, `name`, `sku`, `barcode`, `description`, `cost_price`, `selling_price`, `wholesale_price`, `minimum_stock`, `maximum_stock`, `current_stock`, `image`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 2, NULL, NULL, NULL, NULL, 'Flava', NULL, NULL, NULL, 0.00, 0.00, 0.00, 0.00, NULL, 0.00, NULL, 'active', 1, '2026-08-17 18:22:06', NULL),
(2, 2, NULL, NULL, NULL, NULL, 'Flava', NULL, NULL, NULL, 0.00, 0.00, 0.00, 0.00, NULL, 0.00, NULL, 'active', 1, '2026-08-17 18:24:27', NULL),
(3, 2, NULL, NULL, NULL, NULL, 'Flava', NULL, NULL, NULL, 0.00, 0.00, 0.00, 0.00, NULL, 0.00, NULL, 'active', 1, '2026-08-17 18:25:02', NULL),
(4, 2, NULL, NULL, NULL, NULL, 'Flava', NULL, NULL, NULL, 0.00, 0.00, 0.00, 0.00, NULL, 0.00, NULL, 'active', 1, '2026-08-17 18:25:13', NULL),
(5, 2, NULL, NULL, NULL, NULL, 'Chillaxsssss', NULL, NULL, NULL, 750.00, 1000.00, 800.00, 2.00, 10.00, 1.00, NULL, 'active', 1, '2026-08-17 18:53:44', '2026-08-18 05:04:07'),
(6, 2, NULL, NULL, NULL, NULL, 'Chillaxs - Copy', NULL, NULL, NULL, 750.00, 1000.00, 800.00, 2.00, 10.00, 0.00, NULL, 'active', 1, '2026-08-17 19:10:46', NULL),
(7, 2, NULL, NULL, NULL, NULL, 'Chillaxs (Copy)', NULL, NULL, NULL, 750.00, 1000.00, 800.00, 2.00, 10.00, 0.00, NULL, 'active', 1, '2026-08-17 19:15:47', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `inventory_purchase_items`
--

CREATE TABLE `inventory_purchase_items` (
  `id` int(11) NOT NULL,
  `purchase_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` decimal(12,2) NOT NULL DEFAULT 0.00,
  `received_quantity` decimal(12,2) NOT NULL DEFAULT 0.00,
  `unit_cost` decimal(12,2) NOT NULL DEFAULT 0.00,
  `discount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inventory_purchase_orders`
--

CREATE TABLE `inventory_purchase_orders` (
  `id` int(11) NOT NULL,
  `business_id` int(11) NOT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `purchase_number` varchar(100) NOT NULL,
  `purchase_date` date NOT NULL,
  `subtotal` decimal(12,2) NOT NULL DEFAULT 0.00,
  `discount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `tax` decimal(12,2) NOT NULL DEFAULT 0.00,
  `shipping_cost` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `payment_status` enum('unpaid','partial','paid') NOT NULL DEFAULT 'unpaid',
  `status` enum('draft','ordered','received','cancelled') NOT NULL DEFAULT 'draft',
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inventory_stock_movements`
--

CREATE TABLE `inventory_stock_movements` (
  `id` int(11) NOT NULL,
  `business_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `movement_type` enum('opening_stock','purchase','sale','return_in','return_out','adjustment_in','adjustment_out','transfer_in','transfer_out') NOT NULL,
  `quantity` decimal(12,2) NOT NULL DEFAULT 0.00,
  `unit_cost` decimal(12,2) NOT NULL DEFAULT 0.00,
  `previous_stock` decimal(12,2) NOT NULL DEFAULT 0.00,
  `new_stock` decimal(12,2) NOT NULL DEFAULT 0.00,
  `reference_type` varchar(50) DEFAULT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory_stock_movements`
--

INSERT INTO `inventory_stock_movements` (`id`, `business_id`, `product_id`, `movement_type`, `quantity`, `unit_cost`, `previous_stock`, `new_stock`, `reference_type`, `reference_id`, `notes`, `created_by`, `created_at`) VALUES
(1, 2, 5, '', 10.00, 750.00, 0.00, 10.00, 'manual_adjustment', 5, 'Additional', 1, '2026-08-17 19:10:13'),
(2, 2, 5, '', 6.00, 750.00, 10.00, 4.00, 'manual_adjustment', 5, 'Customer Return', 1, '2026-08-18 05:03:28'),
(3, 2, 5, '', 3.00, 750.00, 4.00, 1.00, 'manual_adjustment', 5, 'Customer Return', 1, '2026-08-18 05:04:07');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_suppliers`
--

CREATE TABLE `inventory_suppliers` (
  `id` int(11) NOT NULL,
  `business_id` int(11) NOT NULL,
  `name` varchar(200) NOT NULL,
  `contact_person` varchar(150) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `tax_number` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inventory_units`
--

CREATE TABLE `inventory_units` (
  `id` int(11) NOT NULL,
  `business_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `abbreviation` varchar(20) NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `penalty_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `penalty_type` enum('fixed','percentage') NOT NULL DEFAULT 'fixed',
  `penalty_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `penalty_frequency` enum('one_time','daily') NOT NULL DEFAULT 'one_time',
  `penalty_grace_period` int(11) NOT NULL DEFAULT 0,
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

INSERT INTO `loans` (`id`, `business_id`, `created_by`, `account_id`, `reference_number`, `borrower_id`, `principal_amount`, `interest_rate`, `total_payable`, `loan_date`, `due_date`, `term_days`, `term_unit`, `payment_type`, `penalty_enabled`, `penalty_type`, `penalty_amount`, `penalty_frequency`, `penalty_grace_period`, `payment_frequency`, `number_of_payments`, `fixed_payment_amount`, `status`, `created_at`, `updated_at`) VALUES
(2, 1, 1, 1, '123123', 1, 1000.00, 0.00, 1000.00, '2026-08-15', '2026-08-30', 15, 'days', 'installment', 0, 'fixed', 0.00, 'one_time', 0, 'monthly', NULL, 1000.00, 'completed', '2026-08-14 23:59:04', '2026-08-15 00:03:03'),
(3, 1, 1, 1, NULL, 1, 1000.00, 0.00, 1000.00, '2026-08-15', '2026-12-15', 4, 'months', 'installment', 0, 'fixed', 0.00, 'one_time', 0, 'monthly', NULL, 250.00, 'active', '2026-08-15 00:00:52', NULL),
(4, 1, 1, 1, NULL, 1, 500.00, 0.00, 500.00, '2026-08-15', '2026-12-15', 4, 'months', 'installment', 0, 'fixed', 0.00, 'one_time', 0, 'monthly', NULL, 125.00, 'active', '2026-08-15 00:01:01', NULL),
(5, 1, 1, 1, NULL, 1, 500.00, 0.00, 500.00, '2026-08-15', '2026-12-15', 4, 'months', 'installment', 0, 'fixed', 0.00, 'one_time', 0, 'monthly', NULL, 125.00, 'active', '2026-08-15 00:06:09', NULL),
(6, 1, 1, 1, NULL, 1, 500.00, 100.00, 1000.00, '2026-08-17', '2026-08-20', 3, 'days', 'lump_sum', 0, 'fixed', 0.00, 'one_time', 0, 'daily', NULL, 0.00, 'active', '2026-08-15 00:21:50', '2026-08-17 00:07:10'),
(7, 1, 1, 1, NULL, 1, 100.00, 0.00, 130.00, '2026-08-14', '2026-08-15', 1, 'days', 'installment', 0, 'fixed', 0.00, 'one_time', 0, 'monthly', NULL, 100.00, 'overdue', '2026-08-16 23:30:34', '2026-08-17 07:49:19'),
(8, 1, 1, 1, NULL, 1, 1000.00, 10.00, 1100.00, '2026-08-17', '2026-08-24', 7, 'days', 'lump_sum', 0, 'fixed', 0.00, 'one_time', 0, 'monthly', NULL, 1100.00, 'active', '2026-08-17 00:14:51', NULL),
(9, 1, 3, 7, '-', 5, 42677.00, 0.00, 42677.00, '2026-08-19', '2859-11-19', 9999, 'months', 'lump_sum', 0, 'fixed', 0.00, 'one_time', 0, 'monthly', NULL, 42677.00, 'active', '2026-08-18 23:37:41', NULL),
(10, 1, 3, 7, NULL, 3, 5090.00, 15.00, 5853.50, '2026-08-15', '2026-08-30', 15, 'days', 'lump_sum', 0, 'fixed', 0.00, 'one_time', 0, 'monthly', NULL, 5853.50, 'active', '2026-08-18 23:38:51', NULL),
(11, 1, 3, 7, NULL, 3, 3000.00, 15.00, 3450.00, '2026-08-15', '2026-08-30', 15, 'days', 'lump_sum', 0, 'fixed', 0.00, 'one_time', 0, 'monthly', NULL, 3450.00, 'active', '2026-08-18 23:39:42', NULL),
(12, 1, 3, 7, NULL, 4, 10000.00, 0.00, 10000.00, '2026-08-15', '2026-10-15', 2, 'months', 'lump_sum', 0, 'fixed', 0.00, 'one_time', 0, 'monthly', NULL, 10000.00, 'active', '2026-08-18 23:41:27', NULL),
(13, 1, 3, 7, NULL, 6, 13036.00, 0.00, 13036.00, '2026-08-19', '2859-11-19', 9999, 'months', 'lump_sum', 0, 'fixed', 0.00, 'one_time', 0, 'monthly', NULL, 13036.00, 'active', '2026-08-18 23:42:00', NULL),
(14, 1, 3, 7, NULL, 7, 5100.00, 15.00, 5865.00, '2026-08-15', '2026-08-30', 15, 'days', 'lump_sum', 0, 'fixed', 0.00, 'one_time', 0, 'monthly', NULL, 5865.00, 'active', '2026-08-18 23:44:13', NULL),
(15, 1, 3, 7, NULL, 8, 50000.00, 15.00, 57500.00, '2026-08-15', '2026-08-30', 15, 'days', 'lump_sum', 0, 'fixed', 0.00, 'one_time', 0, 'monthly', NULL, 57500.00, 'active', '2026-08-18 23:59:43', '2026-08-18 23:59:54'),
(16, 1, 3, 7, NULL, 9, 10000.00, 12.00, 11200.00, '2026-08-15', '2026-08-30', 15, 'days', 'lump_sum', 0, 'fixed', 0.00, 'one_time', 0, 'monthly', NULL, 10000.00, 'active', '2026-08-19 00:01:09', '2026-08-19 00:01:41'),
(17, 1, 3, 7, NULL, 9, 500.00, 12.00, 560.00, '2026-08-15', '2026-08-30', 15, 'days', 'lump_sum', 0, 'fixed', 0.00, 'one_time', 0, 'monthly', NULL, 560.00, 'active', '2026-08-19 00:01:34', NULL),
(18, 1, 3, 7, NULL, 5, 6247.00, 0.00, 6247.00, '2026-08-19', '2859-11-19', 9999, 'months', 'lump_sum', 0, 'fixed', 0.00, 'one_time', 0, 'monthly', NULL, 6247.00, 'active', '2026-08-19 00:04:12', NULL);

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
(1, 1, 1, 'Gcash', 'cash', 6500.00, 'active', '2026-08-14 23:54:23', '2026-08-17 00:19:04'),
(2, 1, 3, 'Gcash', 'cash', 5156.00, 'active', '2026-08-18 23:34:05', NULL),
(3, 1, 3, 'Maribank', 'cash', 30260.00, 'active', '2026-08-18 23:34:35', NULL),
(4, 1, 3, 'Maribank Time Deposit', 'cash', 500.00, 'active', '2026-08-18 23:34:50', NULL),
(5, 1, 3, 'BPI', 'cash', 393.00, 'active', '2026-08-18 23:34:58', NULL),
(6, 1, 3, 'Cash Box', 'cash', 11900.00, 'active', '2026-08-18 23:35:08', NULL),
(7, 1, 3, 'Initial Loans', 'cash', 0.00, 'active', '2026-08-18 23:36:17', '2026-08-19 00:09:46');

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
(6, 1, NULL, 1, NULL, NULL, 'DEBIT', 500.00, 'Loan #6 disbursement', '2026-08-15 00:21:50'),
(7, 1, NULL, 1, NULL, NULL, 'DEBIT', 100.00, 'Loan #7 disbursement', '2026-08-16 23:30:34'),
(8, 1, NULL, 1, NULL, NULL, 'DEBIT', 1000.00, 'Loan #8 disbursement', '2026-08-17 00:14:51'),
(9, 1, NULL, 1, NULL, NULL, 'CREDIT', 100.00, 'Payment received for Loan #8', '2026-08-17 00:19:04'),
(10, 1, NULL, 7, NULL, NULL, 'DEBIT', 42677.00, 'Loan #9 disbursement', '2026-08-18 23:37:41'),
(11, 1, NULL, 7, NULL, NULL, 'DEBIT', 5090.00, 'Loan #10 disbursement', '2026-08-18 23:38:51'),
(12, 1, NULL, 7, NULL, NULL, 'DEBIT', 3000.00, 'Loan #11 disbursement', '2026-08-18 23:39:42'),
(13, 1, NULL, 7, NULL, NULL, 'DEBIT', 10000.00, 'Loan #12 disbursement', '2026-08-18 23:41:27'),
(14, 1, NULL, 7, NULL, NULL, 'DEBIT', 13036.00, 'Loan #13 disbursement', '2026-08-18 23:42:00'),
(15, 1, NULL, 7, NULL, NULL, 'DEBIT', 5100.00, 'Loan #14 disbursement', '2026-08-18 23:44:13'),
(16, 1, NULL, 7, NULL, NULL, 'DEBIT', 50000.00, 'Loan #15 disbursement', '2026-08-18 23:59:43'),
(17, 1, NULL, 7, NULL, NULL, 'DEBIT', 10000.00, 'Loan #16 disbursement', '2026-08-19 00:01:09'),
(18, 1, NULL, 7, NULL, NULL, 'DEBIT', 500.00, 'Loan #17 disbursement', '2026-08-19 00:01:34'),
(19, 1, NULL, 7, NULL, NULL, 'DEBIT', 6247.00, 'Loan #18 disbursement', '2026-08-19 00:04:12');

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
(1, 1, 1, 'Marldohn', NULL, 'Rubinos', NULL, '09061941138', 'Jakosalem Street', NULL, NULL, 'active', '2026-08-14 23:54:09', NULL),
(2, 1, 1, 'Dondi', NULL, 'Rubinos', NULL, '12321312', 'fwq', NULL, NULL, 'active', '2026-08-17 08:01:32', NULL),
(3, 1, 3, 'Jerryniel', NULL, 'Lauronal', NULL, '', 'Sikatuna Street Cebu City', NULL, NULL, 'active', '2026-08-18 23:01:55', NULL),
(4, 1, 3, 'Anne Hildred', NULL, 'Olan-Olan', NULL, '', 'Sikatuna Street Cebu City', NULL, NULL, 'active', '2026-08-18 23:02:14', NULL),
(5, 1, 3, 'Marldohn', NULL, 'Rubinos', NULL, '09061941138', 'Jakosalem Street Cebu City', NULL, NULL, 'active', '2026-08-18 23:02:30', '2026-08-18 23:05:50'),
(6, 1, 3, 'March Shelou', NULL, 'Ardillo', NULL, '09059626063', 'Balamban Cebu', NULL, NULL, 'active', '2026-08-18 23:06:12', NULL),
(7, 1, 3, 'Allen', NULL, 'Jayme', NULL, '', '', NULL, 'Call Center (BPO)', 'active', '2026-08-18 23:43:49', NULL),
(8, 1, 3, 'Janice', NULL, 'Olan-Olan', NULL, '', 'Leyte, Philippines', NULL, 'V.A', 'active', '2026-08-18 23:59:21', NULL),
(9, 1, 3, 'Myles', NULL, 'Batayola', NULL, '', 'Sikatuna Street Cebu City', NULL, 'Call Center', 'active', '2026-08-19 00:00:47', NULL);

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
(2, 1, NULL, 2, NULL, 1, 1000.00, '2026-08-15', '', '2026-08-15 00:03:03'),
(3, 1, NULL, 8, NULL, 1, 100.00, '2026-08-17', '', '2026-08-17 00:19:04');

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

--
-- Dumping data for table `loan_penalties`
--

INSERT INTO `loan_penalties` (`id`, `business_id`, `loan_id`, `schedule_id`, `amount`, `reason`, `penalty_date`, `status`, `created_by`, `created_at`) VALUES
(1, 1, 7, NULL, 15.00, 'Late Payment', '2026-08-17', 'unpaid', 1, '2026-08-16 23:31:38'),
(2, 1, 7, NULL, 15.00, 'Late', '2026-08-17', 'unpaid', 1, '2026-08-17 00:29:15');

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
  `penalty_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_due` decimal(15,2) NOT NULL DEFAULT 0.00,
  `penalty_updated_at` datetime DEFAULT NULL,
  `amount_paid` decimal(12,2) NOT NULL DEFAULT 0.00,
  `balance_due` decimal(12,2) NOT NULL DEFAULT 0.00,
  `status` enum('unpaid','partially_paid','paid','overdue') NOT NULL DEFAULT 'unpaid',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `loan_schedules`
--

INSERT INTO `loan_schedules` (`id`, `business_id`, `loan_id`, `installment_number`, `due_date`, `amount_due`, `penalty_amount`, `total_due`, `penalty_updated_at`, `amount_paid`, `balance_due`, `status`, `created_at`, `updated_at`) VALUES
(2, 1, 2, 0, '2026-09-15', 1000.00, 0.00, 1000.00, NULL, 0.00, 0.00, 'paid', '2026-08-14 23:59:04', '2026-08-16 21:20:21'),
(3, 1, 3, 0, '2026-09-15', 250.00, 0.00, 250.00, NULL, 0.00, 0.00, 'unpaid', '2026-08-15 00:00:52', '2026-08-16 21:20:21'),
(4, 1, 3, 0, '2026-10-15', 250.00, 0.00, 250.00, NULL, 0.00, 0.00, 'unpaid', '2026-08-15 00:00:52', '2026-08-16 21:20:21'),
(5, 1, 3, 0, '2026-11-15', 250.00, 0.00, 250.00, NULL, 0.00, 0.00, 'unpaid', '2026-08-15 00:00:52', '2026-08-16 21:20:21'),
(6, 1, 3, 0, '2026-12-15', 250.00, 0.00, 250.00, NULL, 0.00, 0.00, 'unpaid', '2026-08-15 00:00:52', '2026-08-16 21:20:21'),
(7, 1, 4, 0, '2026-09-15', 125.00, 0.00, 125.00, NULL, 0.00, 0.00, 'unpaid', '2026-08-15 00:01:01', '2026-08-16 21:20:21'),
(8, 1, 4, 0, '2026-10-15', 125.00, 0.00, 125.00, NULL, 0.00, 0.00, 'unpaid', '2026-08-15 00:01:01', '2026-08-16 21:20:21'),
(9, 1, 4, 0, '2026-11-15', 125.00, 0.00, 125.00, NULL, 0.00, 0.00, 'unpaid', '2026-08-15 00:01:01', '2026-08-16 21:20:21'),
(10, 1, 4, 0, '2026-12-15', 125.00, 0.00, 125.00, NULL, 0.00, 0.00, 'unpaid', '2026-08-15 00:01:01', '2026-08-16 21:20:21'),
(11, 1, 5, 0, '2026-09-15', 125.00, 0.00, 125.00, NULL, 0.00, 0.00, 'unpaid', '2026-08-15 00:06:09', '2026-08-16 21:20:21'),
(12, 1, 5, 0, '2026-10-15', 125.00, 0.00, 125.00, NULL, 0.00, 0.00, 'unpaid', '2026-08-15 00:06:09', '2026-08-16 21:20:21'),
(13, 1, 5, 0, '2026-11-15', 125.00, 0.00, 125.00, NULL, 0.00, 0.00, 'unpaid', '2026-08-15 00:06:09', '2026-08-16 21:20:21'),
(14, 1, 5, 0, '2026-12-15', 125.00, 0.00, 125.00, NULL, 0.00, 0.00, 'unpaid', '2026-08-15 00:06:09', '2026-08-16 21:20:21'),
(29, 1, 6, 1, '2026-08-20', 1000.00, 0.00, 1000.00, NULL, 0.00, 1000.00, 'unpaid', '2026-08-17 00:07:10', '2026-08-17 00:07:10'),
(30, 1, 8, 0, '2026-08-24', 1100.00, 0.00, 0.00, NULL, 0.00, 0.00, 'partially_paid', '2026-08-17 00:14:51', '2026-08-17 00:19:04'),
(32, 1, 7, 1, '2026-08-15', 130.00, 0.00, 130.00, NULL, 0.00, 130.00, 'overdue', '2026-08-17 07:49:19', '2026-08-17 07:49:19'),
(33, 1, 9, 0, '2859-11-19', 42677.00, 0.00, 0.00, NULL, 0.00, 0.00, 'unpaid', '2026-08-18 23:37:41', NULL),
(34, 1, 10, 0, '2026-08-30', 5853.50, 0.00, 0.00, NULL, 0.00, 0.00, 'unpaid', '2026-08-18 23:38:51', NULL),
(35, 1, 11, 0, '2026-08-30', 3450.00, 0.00, 0.00, NULL, 0.00, 0.00, 'unpaid', '2026-08-18 23:39:42', NULL),
(36, 1, 12, 0, '2026-10-15', 10000.00, 0.00, 0.00, NULL, 0.00, 0.00, 'unpaid', '2026-08-18 23:41:27', NULL),
(37, 1, 13, 0, '2859-11-19', 13036.00, 0.00, 0.00, NULL, 0.00, 0.00, 'unpaid', '2026-08-18 23:42:00', NULL),
(38, 1, 14, 0, '2026-08-30', 5865.00, 0.00, 0.00, NULL, 0.00, 0.00, 'unpaid', '2026-08-18 23:44:13', NULL),
(40, 1, 15, 1, '2026-08-30', 57500.00, 0.00, 57500.00, NULL, 0.00, 57500.00, 'unpaid', '2026-08-18 23:59:54', '2026-08-18 23:59:54'),
(43, 1, 17, 0, '2026-08-30', 560.00, 0.00, 0.00, NULL, 0.00, 0.00, 'unpaid', '2026-08-19 00:01:34', NULL),
(44, 1, 16, 1, '2026-08-30', 11200.00, 0.00, 11200.00, NULL, 0.00, 11200.00, 'unpaid', '2026-08-19 00:01:41', '2026-08-19 00:01:41'),
(45, 1, 18, 0, '2859-11-19', 6247.00, 0.00, 0.00, NULL, 0.00, 0.00, 'unpaid', '2026-08-19 00:04:12', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `loan_settings`
--

CREATE TABLE `loan_settings` (
  `id` int(10) UNSIGNED NOT NULL,
  `business_id` int(10) UNSIGNED NOT NULL,
  `currency` varchar(10) NOT NULL DEFAULT 'PHP',
  `currency_symbol` varchar(10) NOT NULL DEFAULT '₱',
  `default_interest_type` enum('flat','reducing_balance') NOT NULL DEFAULT 'flat',
  `default_interest_rate` decimal(10,2) NOT NULL DEFAULT 0.00,
  `default_payment_frequency` enum('daily','weekly','biweekly','monthly') NOT NULL DEFAULT 'monthly',
  `default_payment_type` enum('installment','lump_sum') NOT NULL DEFAULT 'installment',
  `default_loan_term` int(11) NOT NULL DEFAULT 12,
  `grace_period` int(11) NOT NULL DEFAULT 0,
  `late_fee_type` enum('fixed','percentage') NOT NULL DEFAULT 'fixed',
  `late_fee_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `business_address` text DEFAULT NULL,
  `business_phone` varchar(50) DEFAULT NULL,
  `business_email` varchar(150) DEFAULT NULL,
  `date_format` varchar(30) NOT NULL DEFAULT 'M d, Y',
  `timezone` varchar(100) NOT NULL DEFAULT 'Asia/Manila',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `loan_settings`
--

INSERT INTO `loan_settings` (`id`, `business_id`, `currency`, `currency_symbol`, `default_interest_type`, `default_interest_rate`, `default_payment_frequency`, `default_payment_type`, `default_loan_term`, `grace_period`, `late_fee_type`, `late_fee_amount`, `business_address`, `business_phone`, `business_email`, `date_format`, `timezone`, `created_at`, `updated_at`) VALUES
(1, 1, 'PHP', '₱', 'flat', 0.00, 'monthly', 'installment', 12, 0, 'fixed', 0.00, NULL, NULL, NULL, 'M d, Y', 'Asia/Manila', '2026-08-19 00:33:12', '2026-08-19 00:47:24');

-- --------------------------------------------------------

--
-- Table structure for table `pos_brands`
--

CREATE TABLE `pos_brands` (
  `id` int(11) NOT NULL,
  `business_id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pos_categories`
--

CREATE TABLE `pos_categories` (
  `id` int(11) NOT NULL,
  `business_id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pos_customers`
--

CREATE TABLE `pos_customers` (
  `id` int(11) NOT NULL,
  `business_id` int(11) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pos_expenses`
--

CREATE TABLE `pos_expenses` (
  `id` int(11) NOT NULL,
  `business_id` int(11) NOT NULL,
  `description` varchar(255) NOT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `expense_date` date NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pos_payments`
--

CREATE TABLE `pos_payments` (
  `id` int(11) NOT NULL,
  `business_id` int(11) NOT NULL,
  `sale_id` int(11) NOT NULL,
  `payment_method` enum('cash','card','gcash','bank_transfer','other') NOT NULL DEFAULT 'cash',
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `reference_number` varchar(150) DEFAULT NULL,
  `payment_date` datetime NOT NULL DEFAULT current_timestamp(),
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pos_products`
--

CREATE TABLE `pos_products` (
  `id` int(11) NOT NULL,
  `business_id` int(11) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `brand_id` int(11) DEFAULT NULL,
  `unit_id` int(11) DEFAULT NULL,
  `name` varchar(200) NOT NULL,
  `sku` varchar(100) DEFAULT NULL,
  `barcode` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `cost_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `selling_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `wholesale_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `minimum_stock` decimal(12,2) NOT NULL DEFAULT 0.00,
  `maximum_stock` decimal(12,2) DEFAULT NULL,
  `current_stock` decimal(12,2) NOT NULL DEFAULT 0.00,
  `image` varchar(500) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pos_sales`
--

CREATE TABLE `pos_sales` (
  `id` int(11) NOT NULL,
  `business_id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `invoice_number` varchar(100) NOT NULL,
  `sale_date` datetime NOT NULL DEFAULT current_timestamp(),
  `subtotal` decimal(12,2) NOT NULL DEFAULT 0.00,
  `discount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `tax` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `amount_paid` decimal(12,2) NOT NULL DEFAULT 0.00,
  `change_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `payment_status` enum('unpaid','partial','paid') NOT NULL DEFAULT 'unpaid',
  `sale_status` enum('completed','voided','refunded') NOT NULL DEFAULT 'completed',
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pos_sale_items`
--

CREATE TABLE `pos_sale_items` (
  `id` int(11) NOT NULL,
  `sale_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `product_name` varchar(200) NOT NULL,
  `sku` varchar(100) DEFAULT NULL,
  `quantity` decimal(12,2) NOT NULL DEFAULT 0.00,
  `unit_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `discount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pos_stock_adjustments`
--

CREATE TABLE `pos_stock_adjustments` (
  `id` int(11) NOT NULL,
  `business_id` int(11) NOT NULL,
  `adjustment_number` varchar(100) NOT NULL,
  `adjustment_date` date NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `status` enum('draft','completed','cancelled') NOT NULL DEFAULT 'draft',
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pos_stock_adjustment_items`
--

CREATE TABLE `pos_stock_adjustment_items` (
  `id` int(11) NOT NULL,
  `adjustment_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `system_quantity` decimal(12,2) NOT NULL DEFAULT 0.00,
  `actual_quantity` decimal(12,2) NOT NULL DEFAULT 0.00,
  `difference` decimal(12,2) NOT NULL DEFAULT 0.00,
  `reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pos_stock_movements`
--

CREATE TABLE `pos_stock_movements` (
  `id` int(11) NOT NULL,
  `business_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `movement_type` enum('opening_stock','purchase','sale','return_in','return_out','adjustment_in','adjustment_out') NOT NULL,
  `quantity` decimal(12,2) NOT NULL DEFAULT 0.00,
  `unit_cost` decimal(12,2) NOT NULL DEFAULT 0.00,
  `previous_stock` decimal(12,2) NOT NULL DEFAULT 0.00,
  `new_stock` decimal(12,2) NOT NULL DEFAULT 0.00,
  `reference_type` varchar(50) DEFAULT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pos_units`
--

CREATE TABLE `pos_units` (
  `id` int(11) NOT NULL,
  `business_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `abbreviation` varchar(20) NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
(4, 'March Shelou', 'mardillo@azpired.net', '$2y$10$m0Vm1Al2Gxo87D9Ev.o89OAYbejOa1lcw55j1ODa4pl2uk1.vAC1K', 'admin', 'active', '2026-08-15 01:02:08', '2026-08-15 01:04:11'),
(5, 'Marldohn Rubinos', 'admin2@gmail.com', '$2y$10$C/TF4gbrdfkK7ZJs1Ir1rO6pbkfzGMUMsuwXe/hhnc.EkvY.0/BbC', 'admin', 'active', '2026-08-16 21:16:24', '2026-08-16 21:17:01');

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
-- Indexes for table `inventory_adjustments`
--
ALTER TABLE `inventory_adjustments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_inventory_adjustment_business_number` (`business_id`,`adjustment_number`),
  ADD KEY `idx_inventory_adjustments_business` (`business_id`),
  ADD KEY `idx_inventory_adjustments_created_by` (`created_by`);

--
-- Indexes for table `inventory_adjustment_items`
--
ALTER TABLE `inventory_adjustment_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_inventory_adjustment_items_adjustment` (`adjustment_id`),
  ADD KEY `idx_inventory_adjustment_items_product` (`product_id`);

--
-- Indexes for table `inventory_brands`
--
ALTER TABLE `inventory_brands`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_inventory_brand_business_name` (`business_id`,`name`),
  ADD KEY `idx_inventory_brands_business` (`business_id`),
  ADD KEY `idx_inventory_brands_created_by` (`created_by`);

--
-- Indexes for table `inventory_categories`
--
ALTER TABLE `inventory_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_inventory_category_business_name` (`business_id`,`name`),
  ADD KEY `idx_inventory_categories_business` (`business_id`),
  ADD KEY `idx_inventory_categories_created_by` (`created_by`);

--
-- Indexes for table `inventory_products`
--
ALTER TABLE `inventory_products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_inventory_product_business_sku` (`business_id`,`sku`),
  ADD UNIQUE KEY `uq_inventory_product_business_barcode` (`business_id`,`barcode`),
  ADD KEY `idx_inventory_products_business` (`business_id`),
  ADD KEY `idx_inventory_products_category` (`category_id`),
  ADD KEY `idx_inventory_products_brand` (`brand_id`),
  ADD KEY `idx_inventory_products_unit` (`unit_id`),
  ADD KEY `idx_inventory_products_supplier` (`supplier_id`),
  ADD KEY `idx_inventory_products_created_by` (`created_by`);

--
-- Indexes for table `inventory_purchase_items`
--
ALTER TABLE `inventory_purchase_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_inventory_purchase_items_purchase` (`purchase_id`),
  ADD KEY `idx_inventory_purchase_items_product` (`product_id`);

--
-- Indexes for table `inventory_purchase_orders`
--
ALTER TABLE `inventory_purchase_orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_inventory_purchase_business_number` (`business_id`,`purchase_number`),
  ADD KEY `idx_inventory_purchase_business` (`business_id`),
  ADD KEY `idx_inventory_purchase_supplier` (`supplier_id`),
  ADD KEY `idx_inventory_purchase_created_by` (`created_by`);

--
-- Indexes for table `inventory_stock_movements`
--
ALTER TABLE `inventory_stock_movements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_inventory_movements_business` (`business_id`),
  ADD KEY `idx_inventory_movements_product` (`product_id`),
  ADD KEY `idx_inventory_movements_type` (`movement_type`),
  ADD KEY `idx_inventory_movements_reference` (`reference_type`,`reference_id`),
  ADD KEY `idx_inventory_movements_created_by` (`created_by`);

--
-- Indexes for table `inventory_suppliers`
--
ALTER TABLE `inventory_suppliers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_inventory_suppliers_business` (`business_id`),
  ADD KEY `idx_inventory_suppliers_created_by` (`created_by`);

--
-- Indexes for table `inventory_units`
--
ALTER TABLE `inventory_units`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_inventory_unit_business_name` (`business_id`,`name`),
  ADD KEY `idx_inventory_units_business` (`business_id`),
  ADD KEY `idx_inventory_units_created_by` (`created_by`);

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
-- Indexes for table `loan_settings`
--
ALTER TABLE `loan_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_business_settings` (`business_id`);

--
-- Indexes for table `pos_brands`
--
ALTER TABLE `pos_brands`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_pos_brands_business_name` (`business_id`,`name`),
  ADD KEY `idx_pos_brands_business` (`business_id`),
  ADD KEY `idx_pos_brands_created_by` (`created_by`);

--
-- Indexes for table `pos_categories`
--
ALTER TABLE `pos_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_pos_categories_business_name` (`business_id`,`name`),
  ADD KEY `idx_pos_categories_business` (`business_id`),
  ADD KEY `idx_pos_categories_created_by` (`created_by`);

--
-- Indexes for table `pos_customers`
--
ALTER TABLE `pos_customers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pos_customers_business` (`business_id`),
  ADD KEY `idx_pos_customers_created_by` (`created_by`);

--
-- Indexes for table `pos_expenses`
--
ALTER TABLE `pos_expenses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pos_expenses_business` (`business_id`),
  ADD KEY `idx_pos_expenses_date` (`expense_date`),
  ADD KEY `idx_pos_expenses_created_by` (`created_by`);

--
-- Indexes for table `pos_payments`
--
ALTER TABLE `pos_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pos_payments_business` (`business_id`),
  ADD KEY `idx_pos_payments_sale` (`sale_id`),
  ADD KEY `idx_pos_payments_created_by` (`created_by`);

--
-- Indexes for table `pos_products`
--
ALTER TABLE `pos_products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_pos_products_business_sku` (`business_id`,`sku`),
  ADD UNIQUE KEY `uq_pos_products_business_barcode` (`business_id`,`barcode`),
  ADD KEY `idx_pos_products_business` (`business_id`),
  ADD KEY `idx_pos_products_category` (`category_id`),
  ADD KEY `idx_pos_products_brand` (`brand_id`),
  ADD KEY `idx_pos_products_unit` (`unit_id`),
  ADD KEY `idx_pos_products_created_by` (`created_by`);

--
-- Indexes for table `pos_sales`
--
ALTER TABLE `pos_sales`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_pos_sales_business_invoice` (`business_id`,`invoice_number`),
  ADD KEY `idx_pos_sales_business` (`business_id`),
  ADD KEY `idx_pos_sales_customer` (`customer_id`),
  ADD KEY `idx_pos_sales_date` (`sale_date`),
  ADD KEY `idx_pos_sales_created_by` (`created_by`);

--
-- Indexes for table `pos_sale_items`
--
ALTER TABLE `pos_sale_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pos_sale_items_sale` (`sale_id`),
  ADD KEY `idx_pos_sale_items_product` (`product_id`);

--
-- Indexes for table `pos_stock_adjustments`
--
ALTER TABLE `pos_stock_adjustments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_pos_adjustments_business_number` (`business_id`,`adjustment_number`),
  ADD KEY `idx_pos_adjustments_business` (`business_id`),
  ADD KEY `idx_pos_adjustments_created_by` (`created_by`);

--
-- Indexes for table `pos_stock_adjustment_items`
--
ALTER TABLE `pos_stock_adjustment_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pos_adjustment_items_adjustment` (`adjustment_id`),
  ADD KEY `idx_pos_adjustment_items_product` (`product_id`);

--
-- Indexes for table `pos_stock_movements`
--
ALTER TABLE `pos_stock_movements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pos_stock_business` (`business_id`),
  ADD KEY `idx_pos_stock_product` (`product_id`),
  ADD KEY `idx_pos_stock_type` (`movement_type`),
  ADD KEY `idx_pos_stock_reference` (`reference_type`,`reference_id`),
  ADD KEY `idx_pos_stock_created_by` (`created_by`);

--
-- Indexes for table `pos_units`
--
ALTER TABLE `pos_units`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_pos_units_business_name` (`business_id`,`name`),
  ADD KEY `idx_pos_units_business` (`business_id`),
  ADD KEY `idx_pos_units_created_by` (`created_by`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `business_users`
--
ALTER TABLE `business_users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `inventory_adjustments`
--
ALTER TABLE `inventory_adjustments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inventory_adjustment_items`
--
ALTER TABLE `inventory_adjustment_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inventory_brands`
--
ALTER TABLE `inventory_brands`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inventory_categories`
--
ALTER TABLE `inventory_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `inventory_products`
--
ALTER TABLE `inventory_products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `inventory_purchase_items`
--
ALTER TABLE `inventory_purchase_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inventory_purchase_orders`
--
ALTER TABLE `inventory_purchase_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inventory_stock_movements`
--
ALTER TABLE `inventory_stock_movements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `inventory_suppliers`
--
ALTER TABLE `inventory_suppliers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inventory_units`
--
ALTER TABLE `inventory_units`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `loans`
--
ALTER TABLE `loans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `loan_accounts`
--
ALTER TABLE `loan_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `loan_account_transactions`
--
ALTER TABLE `loan_account_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `loan_account_transfers`
--
ALTER TABLE `loan_account_transfers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `loan_borrowers`
--
ALTER TABLE `loan_borrowers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `loan_penalties`
--
ALTER TABLE `loan_penalties`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `loan_schedules`
--
ALTER TABLE `loan_schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=46;

--
-- AUTO_INCREMENT for table `loan_settings`
--
ALTER TABLE `loan_settings`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `pos_brands`
--
ALTER TABLE `pos_brands`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pos_categories`
--
ALTER TABLE `pos_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pos_customers`
--
ALTER TABLE `pos_customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pos_expenses`
--
ALTER TABLE `pos_expenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pos_payments`
--
ALTER TABLE `pos_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pos_products`
--
ALTER TABLE `pos_products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pos_sales`
--
ALTER TABLE `pos_sales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pos_sale_items`
--
ALTER TABLE `pos_sale_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pos_stock_adjustments`
--
ALTER TABLE `pos_stock_adjustments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pos_stock_adjustment_items`
--
ALTER TABLE `pos_stock_adjustment_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pos_stock_movements`
--
ALTER TABLE `pos_stock_movements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pos_units`
--
ALTER TABLE `pos_units`
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
-- Constraints for table `business_users`
--
ALTER TABLE `business_users`
  ADD CONSTRAINT `fk_business_users_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_business_users_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `inventory_adjustments`
--
ALTER TABLE `inventory_adjustments`
  ADD CONSTRAINT `fk_inventory_adjustments_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_inventory_adjustments_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `inventory_adjustment_items`
--
ALTER TABLE `inventory_adjustment_items`
  ADD CONSTRAINT `fk_inventory_adjustment_items_adjustment` FOREIGN KEY (`adjustment_id`) REFERENCES `inventory_adjustments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_inventory_adjustment_items_product` FOREIGN KEY (`product_id`) REFERENCES `inventory_products` (`id`);

--
-- Constraints for table `inventory_brands`
--
ALTER TABLE `inventory_brands`
  ADD CONSTRAINT `fk_inventory_brands_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_inventory_brands_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `inventory_categories`
--
ALTER TABLE `inventory_categories`
  ADD CONSTRAINT `fk_inventory_categories_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_inventory_categories_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `inventory_products`
--
ALTER TABLE `inventory_products`
  ADD CONSTRAINT `fk_inventory_products_brand` FOREIGN KEY (`brand_id`) REFERENCES `inventory_brands` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_inventory_products_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_inventory_products_category` FOREIGN KEY (`category_id`) REFERENCES `inventory_categories` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_inventory_products_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_inventory_products_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `inventory_suppliers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_inventory_products_unit` FOREIGN KEY (`unit_id`) REFERENCES `inventory_units` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `inventory_purchase_items`
--
ALTER TABLE `inventory_purchase_items`
  ADD CONSTRAINT `fk_inventory_purchase_items_product` FOREIGN KEY (`product_id`) REFERENCES `inventory_products` (`id`),
  ADD CONSTRAINT `fk_inventory_purchase_items_purchase` FOREIGN KEY (`purchase_id`) REFERENCES `inventory_purchase_orders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `inventory_purchase_orders`
--
ALTER TABLE `inventory_purchase_orders`
  ADD CONSTRAINT `fk_inventory_purchase_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_inventory_purchase_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_inventory_purchase_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `inventory_suppliers` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `inventory_stock_movements`
--
ALTER TABLE `inventory_stock_movements`
  ADD CONSTRAINT `fk_inventory_movements_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_inventory_movements_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_inventory_movements_product` FOREIGN KEY (`product_id`) REFERENCES `inventory_products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `inventory_suppliers`
--
ALTER TABLE `inventory_suppliers`
  ADD CONSTRAINT `fk_inventory_suppliers_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_inventory_suppliers_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `inventory_units`
--
ALTER TABLE `inventory_units`
  ADD CONSTRAINT `fk_inventory_units_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_inventory_units_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

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

--
-- Constraints for table `pos_brands`
--
ALTER TABLE `pos_brands`
  ADD CONSTRAINT `fk_pos_brands_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_pos_brands_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `pos_categories`
--
ALTER TABLE `pos_categories`
  ADD CONSTRAINT `fk_pos_categories_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_pos_categories_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `pos_customers`
--
ALTER TABLE `pos_customers`
  ADD CONSTRAINT `fk_pos_customers_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_pos_customers_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `pos_expenses`
--
ALTER TABLE `pos_expenses`
  ADD CONSTRAINT `fk_pos_expenses_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_pos_expenses_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `pos_payments`
--
ALTER TABLE `pos_payments`
  ADD CONSTRAINT `fk_pos_payments_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_pos_payments_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_pos_payments_sale` FOREIGN KEY (`sale_id`) REFERENCES `pos_sales` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `pos_products`
--
ALTER TABLE `pos_products`
  ADD CONSTRAINT `fk_pos_products_brand` FOREIGN KEY (`brand_id`) REFERENCES `pos_brands` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_pos_products_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_pos_products_category` FOREIGN KEY (`category_id`) REFERENCES `pos_categories` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_pos_products_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_pos_products_unit` FOREIGN KEY (`unit_id`) REFERENCES `pos_units` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `pos_sales`
--
ALTER TABLE `pos_sales`
  ADD CONSTRAINT `fk_pos_sales_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_pos_sales_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_pos_sales_customer` FOREIGN KEY (`customer_id`) REFERENCES `pos_customers` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `pos_sale_items`
--
ALTER TABLE `pos_sale_items`
  ADD CONSTRAINT `fk_pos_sale_items_product` FOREIGN KEY (`product_id`) REFERENCES `pos_products` (`id`),
  ADD CONSTRAINT `fk_pos_sale_items_sale` FOREIGN KEY (`sale_id`) REFERENCES `pos_sales` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `pos_stock_adjustments`
--
ALTER TABLE `pos_stock_adjustments`
  ADD CONSTRAINT `fk_pos_adjustments_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_pos_adjustments_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `pos_stock_adjustment_items`
--
ALTER TABLE `pos_stock_adjustment_items`
  ADD CONSTRAINT `fk_pos_adjustment_items_adjustment` FOREIGN KEY (`adjustment_id`) REFERENCES `pos_stock_adjustments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_pos_adjustment_items_product` FOREIGN KEY (`product_id`) REFERENCES `pos_products` (`id`);

--
-- Constraints for table `pos_stock_movements`
--
ALTER TABLE `pos_stock_movements`
  ADD CONSTRAINT `fk_pos_stock_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_pos_stock_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_pos_stock_product` FOREIGN KEY (`product_id`) REFERENCES `pos_products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `pos_units`
--
ALTER TABLE `pos_units`
  ADD CONSTRAINT `fk_pos_units_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_pos_units_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
