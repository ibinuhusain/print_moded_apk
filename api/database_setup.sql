-- Database setup for receipt management system
-- Run this SQL to create the necessary tables

CREATE DATABASE IF NOT EXISTS agent_dashboard_db;

USE agent_dashboard_db;

CREATE TABLE IF NOT EXISTS receipts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_name VARCHAR(255) NOT NULL,
    items JSON NOT NULL,
    total DECIMAL(10, 2) NOT NULL,
    transaction_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    receipt_image_path VARCHAR(500),
    agent_id INT,
    INDEX idx_agent_id (agent_id),
    INDEX idx_transaction_date (transaction_date)
);

-- Optional: Create an agents table if needed
CREATE TABLE IF NOT EXISTS agents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Insert sample agent (for testing purposes)
INSERT INTO agents (username, email, password_hash) VALUES 
('test_agent', 'test@agent.com', '$2y$10$example_hash_for_demo_purposes_only');