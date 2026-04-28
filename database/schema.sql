CREATE DATABASE IF NOT EXISTS qpay;
USE qpay;

-- Users Table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    phone VARCHAR(20) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    pin_hash VARCHAR(255) NOT NULL,
    
    -- Advanced KYC Fields
    full_name_ar VARCHAR(255) NOT NULL,
    full_name_en VARCHAR(255) NOT NULL,
    id_number VARCHAR(20) UNIQUE NOT NULL,
    dob DATE NOT NULL,
    email VARCHAR(100) NULL,
    whatsapp_phone VARCHAR(20) NULL,
    address TEXT NOT NULL,
    profession VARCHAR(100) NOT NULL,
    account_type ENUM('personal', 'merchant', 'small_business') DEFAULT 'personal',
    
    -- Files
    id_card_path VARCHAR(255) NULL,
    selfie_path VARCHAR(255) NULL,
    
    kyc_status ENUM('pending', 'verified', 'rejected') DEFAULT 'pending',
    is_admin TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- System Settings
CREATE TABLE IF NOT EXISTS settings (
    setting_key VARCHAR(50) PRIMARY KEY,
    setting_value VARCHAR(255)
);

INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('whatsapp_notifications', '0');

-- Wallets Table
CREATE TABLE IF NOT EXISTS wallets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNIQUE NOT NULL,
    balance DECIMAL(15, 2) DEFAULT 0.00,
    currency VARCHAR(10) DEFAULT 'IQD',
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Transactions Table
CREATE TABLE IF NOT EXISTS transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_wallet_id INT NULL,
    receiver_wallet_id INT NULL,
    amount DECIMAL(15, 2) NOT NULL,
    type ENUM('transfer', 'deposit', 'withdrawal') NOT NULL,
    description TEXT,
    status ENUM('completed', 'reversed') DEFAULT 'completed',
    sender_balance_after DECIMAL(15, 2) NULL,
    receiver_balance_after DECIMAL(15, 2) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_wallet_id) REFERENCES wallets(id),
    FOREIGN KEY (receiver_wallet_id) REFERENCES wallets(id)
);

-- OTP Codes Table
CREATE TABLE IF NOT EXISTS otp_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    phone VARCHAR(20) NOT NULL,
    code VARCHAR(10) NOT NULL,
    expires_at DATETIME NOT NULL,
    is_used TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
