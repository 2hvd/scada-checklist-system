CREATE DATABASE IF NOT EXISTS scada_checklist;
USE scada_checklist;

CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100),
    role ENUM('admin','support','user') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    active TINYINT(1) DEFAULT 1
);

CREATE TABLE IF NOT EXISTS swo_list (
    id INT PRIMARY KEY AUTO_INCREMENT,
    swo_number VARCHAR(50) UNIQUE NOT NULL,
    station_name VARCHAR(100) NOT NULL,
    swo_type VARCHAR(50) NOT NULL,
    kcor VARCHAR(50),
    description TEXT,
    status ENUM('Draft','Pending','Registered','In Progress','Submitted','Completed','Closed') DEFAULT 'Draft',
    created_by INT,
    assigned_to INT,
    approved_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    approved_at TIMESTAMP NULL,
    assigned_at TIMESTAMP NULL,
    submitted_at TIMESTAMP NULL,
    closed_at TIMESTAMP NULL,
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (assigned_to) REFERENCES users(id),
    FOREIGN KEY (approved_by) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS checklist_status (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    swo_id INT NOT NULL,
    item_key VARCHAR(50) NOT NULL,
    status ENUM('empty','done','na','not_yet','still') DEFAULT 'empty',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_checklist (user_id, swo_id, item_key),
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (swo_id) REFERENCES swo_list(id)
);

CREATE TABLE IF NOT EXISTS comments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    swo_id INT NOT NULL,
    item_key VARCHAR(50),
    comment_text TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (swo_id) REFERENCES swo_list(id)
);

CREATE TABLE IF NOT EXISTS audit_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    swo_id INT,
    action VARCHAR(100) NOT NULL,
    old_status VARCHAR(50),
    new_status VARCHAR(50),
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (swo_id) REFERENCES swo_list(id)
);

CREATE TABLE IF NOT EXISTS submissions_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    swo_id INT NOT NULL,
    action ENUM('submit','withdraw','approve','reject') NOT NULL,
    notes TEXT,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (swo_id) REFERENCES swo_list(id)
);

CREATE TABLE IF NOT EXISTS checklist_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    section VARCHAR(50) NOT NULL,
    section_number INT NOT NULL,
    description TEXT NOT NULL,
    item_key VARCHAR(50) UNIQUE NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    is_deleted TINYINT(1) DEFAULT 0,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
);
