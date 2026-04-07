-- Create Database if it doesn't exist
CREATE DATABASE IF NOT EXISTS medicine_tracking;
USE medicine_tracking;

-- Create Users Table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'supervisor') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create Medicines Table
CREATE TABLE IF NOT EXISTS medicines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    category VARCHAR(50) DEFAULT 'General',
    stock INT NOT NULL DEFAULT 0,
    min_stock INT DEFAULT 10,
    expiry_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create Transactions Table
CREATE TABLE IF NOT EXISTS transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    medicine_id INT NOT NULL,
    type ENUM('addition', 'reduction', 'adjustment', 'created', 'deleted') NOT NULL,
    quantity INT NOT NULL,
    user_id INT,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Insert Default Users
-- admin / admin123
-- supervisor / super123
INSERT INTO users (username, password_hash, role) VALUES 
('admin', '$2y$10$eE2iM7hU7hQeR0/N12rAje.zEaFkKzD.M36d0w/pXWdK.dD2.D8Q2', 'admin'),
('supervisor', '$2y$10$U/9m4Uo12.pL9H6L./Z6Pum.hE9w5gE2J0s.A2P4Z5gXWdK.dD2.D8Q2', 'supervisor')
ON DUPLICATE KEY UPDATE id=id;
