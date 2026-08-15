CREATE DATABASE IF NOT EXISTS multibusiness_saas
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE multibusiness_saas;

-- ============================================================
-- BUSINESSES
-- ============================================================

CREATE TABLE businesses (
    id INT(11) NOT NULL AUTO_INCREMENT,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NULL,
    phone VARCHAR(50) NULL,
    address TEXT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB;


-- ============================================================
-- USERS
-- ============================================================

CREATE TABLE users (
    id INT(11) NOT NULL AUTO_INCREMENT,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('super_admin','admin','staff') NOT NULL DEFAULT 'staff',
    status ENUM('active','inactive','pending') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB;


-- ============================================================
-- BUSINESS USERS
-- Connects users to businesses
-- ============================================================

CREATE TABLE business_users (
    id INT(11) NOT NULL AUTO_INCREMENT,
    business_id INT(11) NOT NULL,
    user_id INT(11) NOT NULL,
    role ENUM('owner','admin','staff') NOT NULL DEFAULT 'staff',
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_business_user (business_id,user_id),
    KEY idx_business_users_business (business_id),
    KEY idx_business_users_user (user_id),
    CONSTRAINT fk_business_users_business
        FOREIGN KEY (business_id) REFERENCES businesses(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_business_users_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE
) ENGINE=InnoDB;


-- ============================================================
-- LOAN BORROWERS
-- ============================================================

CREATE TABLE loan_borrowers (
    id INT(11) NOT NULL AUTO_INCREMENT,
    business_id INT(11) NOT NULL,
    created_by INT(11) NULL,
    first_name VARCHAR(100) NOT NULL,
    middle_name VARCHAR(100) NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NULL,
    phone VARCHAR(50) NULL,
    address TEXT NULL,
    date_of_birth DATE NULL,
    occupation VARCHAR(150) NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_loan_borrowers_business (business_id),
    KEY idx_loan_borrowers_created_by (created_by),
    CONSTRAINT fk_loan_borrowers_business
        FOREIGN KEY (business_id) REFERENCES businesses(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_loan_borrowers_created_by
        FOREIGN KEY (created_by) REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB;


-- ============================================================
-- LOAN ACCOUNTS
-- Money / funding accounts
-- ============================================================

CREATE TABLE loan_accounts (
    id INT(11) NOT NULL AUTO_INCREMENT,
    business_id INT(11) NOT NULL,
    created_by INT(11) NULL,
    account_name VARCHAR(150) NOT NULL,
    account_type ENUM('cash','bank','e_wallet','other') NOT NULL DEFAULT 'cash',
    balance DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_loan_accounts_business (business_id),
    KEY idx_loan_accounts_created_by (created_by),
    CONSTRAINT fk_loan_accounts_business
        FOREIGN KEY (business_id) REFERENCES businesses(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_loan_accounts_created_by
        FOREIGN KEY (created_by) REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB;


-- ============================================================
-- LOANS
-- ============================================================

CREATE TABLE loans (
    id INT(11) NOT NULL AUTO_INCREMENT,
    business_id INT(11) NOT NULL,
    created_by INT(11) NULL,
    account_id INT(11) NOT NULL,
    reference_number VARCHAR(100) NOT NULL,
    borrower_id INT(11) NOT NULL,
    principal_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    interest_rate DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    total_payable DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    loan_date DATE NOT NULL,
    due_date DATE NOT NULL,
    term_days INT(11) NULL,
    term_unit ENUM('days','weeks','months','years') NOT NULL DEFAULT 'months',
    payment_type ENUM('installment','lump_sum') NOT NULL DEFAULT 'installment',
    payment_frequency ENUM('daily','weekly','biweekly','monthly','quarterly','yearly') NULL,
    number_of_payments INT(11) NULL,
    fixed_payment_amount DECIMAL(12,2) NULL,
    status ENUM('pending','active','completed','overdue','cancelled') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_loans_business_reference (business_id,reference_number),
    KEY idx_loans_business (business_id),
    KEY idx_loans_created_by (created_by),
    KEY idx_loans_borrower (borrower_id),
    KEY idx_loans_account (account_id),
    CONSTRAINT fk_loans_business
        FOREIGN KEY (business_id) REFERENCES businesses(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_loans_created_by
        FOREIGN KEY (created_by) REFERENCES users(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_loans_borrower
        FOREIGN KEY (borrower_id) REFERENCES loan_borrowers(id)
        ON DELETE RESTRICT,
    CONSTRAINT fk_loans_account
        FOREIGN KEY (account_id) REFERENCES loan_accounts(id)
        ON DELETE RESTRICT
) ENGINE=InnoDB;


-- ============================================================
-- LOAN SCHEDULES
-- ============================================================

CREATE TABLE loan_schedules (
    id INT(11) NOT NULL AUTO_INCREMENT,
    business_id INT(11) NOT NULL,
    loan_id INT(11) NOT NULL,
    installment_number INT(11) NOT NULL,
    due_date DATE NOT NULL,
    amount_due DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    amount_paid DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    balance_due DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    status ENUM('unpaid','partially_paid','paid','overdue') NOT NULL DEFAULT 'unpaid',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_loan_schedules_business (business_id),
    KEY idx_loan_schedules_loan (loan_id),
    KEY idx_loan_schedules_due_date (due_date),
    CONSTRAINT fk_loan_schedules_business
        FOREIGN KEY (business_id) REFERENCES businesses(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_loan_schedules_loan
        FOREIGN KEY (loan_id) REFERENCES loans(id)
        ON DELETE CASCADE
) ENGINE=InnoDB;


-- ============================================================
-- LOAN PAYMENTS
-- ============================================================

CREATE TABLE loan_payments (
    id INT(11) NOT NULL AUTO_INCREMENT,
    business_id INT(11) NOT NULL,
    created_by INT(11) NULL,
    loan_id INT(11) NOT NULL,
    schedule_id INT(11) NULL,
    account_id INT(11) NOT NULL,
    payment_amount DECIMAL(12,2) NOT NULL,
    payment_date DATE NOT NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_loan_payments_business (business_id),
    KEY idx_loan_payments_created_by (created_by),
    KEY idx_loan_payments_loan (loan_id),
    KEY idx_loan_payments_schedule (schedule_id),
    KEY idx_loan_payments_account (account_id),
    CONSTRAINT fk_loan_payments_business
        FOREIGN KEY (business_id) REFERENCES businesses(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_loan_payments_created_by
        FOREIGN KEY (created_by) REFERENCES users(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_loan_payments_loan
        FOREIGN KEY (loan_id) REFERENCES loans(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_loan_payments_schedule
        FOREIGN KEY (schedule_id) REFERENCES loan_schedules(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_loan_payments_account
        FOREIGN KEY (account_id) REFERENCES loan_accounts(id)
        ON DELETE RESTRICT
) ENGINE=InnoDB;


-- ============================================================
-- LOAN ACCOUNT TRANSACTIONS
-- ============================================================

CREATE TABLE loan_account_transactions (
    id INT(11) NOT NULL AUTO_INCREMENT,
    business_id INT(11) NOT NULL,
    created_by INT(11) NULL,
    account_id INT(11) NOT NULL,
    loan_id INT(11) NULL,
    payment_id INT(11) NULL,
    type ENUM('CREDIT','DEBIT') NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    description VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_loan_account_transactions_business (business_id),
    KEY idx_loan_account_transactions_account (account_id),
    KEY idx_loan_account_transactions_loan (loan_id),
    KEY idx_loan_account_transactions_payment (payment_id),
    KEY idx_loan_account_transactions_created_by (created_by),
    CONSTRAINT fk_loan_account_transactions_business
        FOREIGN KEY (business_id) REFERENCES businesses(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_loan_account_transactions_created_by
        FOREIGN KEY (created_by) REFERENCES users(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_loan_account_transactions_account
        FOREIGN KEY (account_id) REFERENCES loan_accounts(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_loan_account_transactions_loan
        FOREIGN KEY (loan_id) REFERENCES loans(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_loan_account_transactions_payment
        FOREIGN KEY (payment_id) REFERENCES loan_payments(id)
        ON DELETE SET NULL
) ENGINE=InnoDB;


-- ============================================================
-- LOAN GUARANTORS
-- ============================================================

CREATE TABLE loan_guarantors (
    id INT(11) NOT NULL AUTO_INCREMENT,
    business_id INT(11) NOT NULL,
    loan_id INT(11) NOT NULL,
    name VARCHAR(150) NOT NULL,
    phone VARCHAR(50) NULL,
    email VARCHAR(150) NULL,
    address TEXT NULL,
    relationship VARCHAR(100) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_loan_guarantors_business (business_id),
    KEY idx_loan_guarantors_loan (loan_id),
    CONSTRAINT fk_loan_guarantors_business
        FOREIGN KEY (business_id) REFERENCES businesses(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_loan_guarantors_loan
        FOREIGN KEY (loan_id) REFERENCES loans(id)
        ON DELETE CASCADE
) ENGINE=InnoDB;


-- ============================================================
-- LOAN PENALTIES
-- ============================================================

CREATE TABLE loan_penalties (
    id INT(11) NOT NULL AUTO_INCREMENT,
    business_id INT(11) NOT NULL,
    loan_id INT(11) NOT NULL,
    schedule_id INT(11) NULL,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    reason VARCHAR(255) NULL,
    penalty_date DATE NOT NULL,
    status ENUM('unpaid','paid','waived') NOT NULL DEFAULT 'unpaid',
    created_by INT(11) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_loan_penalties_business (business_id),
    KEY idx_loan_penalties_loan (loan_id),
    KEY idx_loan_penalties_schedule (schedule_id),
    KEY idx_loan_penalties_created_by (created_by),
    CONSTRAINT fk_loan_penalties_business
        FOREIGN KEY (business_id) REFERENCES businesses(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_loan_penalties_loan
        FOREIGN KEY (loan_id) REFERENCES loans(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_loan_penalties_schedule
        FOREIGN KEY (schedule_id) REFERENCES loan_schedules(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_loan_penalties_created_by
        FOREIGN KEY (created_by) REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB;


-- ============================================================
-- LOAN EXPENSES
-- ============================================================

CREATE TABLE loan_expenses (
    id INT(11) NOT NULL AUTO_INCREMENT,
    business_id INT(11) NOT NULL,
    account_id INT(11) NULL,
    created_by INT(11) NULL,
    description VARCHAR(255) NOT NULL,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    expense_date DATE NOT NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_loan_expenses_business (business_id),
    KEY idx_loan_expenses_account (account_id),
    KEY idx_loan_expenses_created_by (created_by),
    CONSTRAINT fk_loan_expenses_business
        FOREIGN KEY (business_id) REFERENCES businesses(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_loan_expenses_account
        FOREIGN KEY (account_id) REFERENCES loan_accounts(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_loan_expenses_created_by
        FOREIGN KEY (created_by) REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB;


-- ============================================================
-- LOAN ACCOUNT TRANSFERS
-- ============================================================

CREATE TABLE loan_account_transfers (
    id INT(11) NOT NULL AUTO_INCREMENT,
    business_id INT(11) NOT NULL,
    created_by INT(11) NULL,
    from_account_id INT(11) NOT NULL,
    to_account_id INT(11) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    transfer_date DATE NOT NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_loan_account_transfers_business (business_id),
    KEY idx_loan_account_transfers_from (from_account_id),
    KEY idx_loan_account_transfers_to (to_account_id),
    KEY idx_loan_account_transfers_created_by (created_by),
    CONSTRAINT fk_loan_account_transfers_business
        FOREIGN KEY (business_id) REFERENCES businesses(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_loan_account_transfers_created_by
        FOREIGN KEY (created_by) REFERENCES users(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_loan_account_transfers_from
        FOREIGN KEY (from_account_id) REFERENCES loan_accounts(id)
        ON DELETE RESTRICT,
    CONSTRAINT fk_loan_account_transfers_to
        FOREIGN KEY (to_account_id) REFERENCES loan_accounts(id)
        ON DELETE RESTRICT
) ENGINE=InnoDB;