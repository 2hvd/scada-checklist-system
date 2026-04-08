-- Migration: Multi-Level SWO Review System
-- Adds support/control review workflow

-- 1. Update users role to include 'control'
ALTER TABLE users MODIFY COLUMN role ENUM('admin','support','control','user') NOT NULL;

-- 2. Update swo_list status to include new statuses and add reviewer columns
ALTER TABLE swo_list MODIFY COLUMN status VARCHAR(50) DEFAULT 'Draft';
ALTER TABLE swo_list ADD COLUMN IF NOT EXISTS support_reviewer_id INT DEFAULT NULL;
ALTER TABLE swo_list ADD COLUMN IF NOT EXISTS control_reviewer_id INT DEFAULT NULL;
ALTER TABLE swo_list ADD COLUMN IF NOT EXISTS rejection_reason TEXT DEFAULT NULL;
ALTER TABLE swo_list ADD COLUMN IF NOT EXISTS support_reviewed_at TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE swo_list ADD COLUMN IF NOT EXISTS control_reviewed_at TIMESTAMP NULL DEFAULT NULL;

-- 3. Create support_reviews table
CREATE TABLE IF NOT EXISTS support_reviews (
    id INT PRIMARY KEY AUTO_INCREMENT,
    swo_id INT NOT NULL,
    reviewed_by INT NOT NULL,
    decision VARCHAR(20) NOT NULL,
    comments TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (swo_id) REFERENCES swo_list(id),
    FOREIGN KEY (reviewed_by) REFERENCES users(id),
    UNIQUE KEY unique_support_review (swo_id, reviewed_by)
);

-- 4. Create support_item_reviews table
CREATE TABLE IF NOT EXISTS support_item_reviews (
    id INT PRIMARY KEY AUTO_INCREMENT,
    swo_id INT NOT NULL,
    item_key VARCHAR(50) NOT NULL,
    support_decision VARCHAR(20),
    support_comment TEXT,
    reviewed_by INT,
    reviewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (swo_id) REFERENCES swo_list(id),
    FOREIGN KEY (reviewed_by) REFERENCES users(id),
    UNIQUE KEY unique_support_item_review (swo_id, item_key)
);

-- 5. Create control_reviews table
CREATE TABLE IF NOT EXISTS control_reviews (
    id INT PRIMARY KEY AUTO_INCREMENT,
    swo_id INT NOT NULL,
    reviewed_by INT NOT NULL,
    decision VARCHAR(20) NOT NULL,
    comments TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (swo_id) REFERENCES swo_list(id),
    FOREIGN KEY (reviewed_by) REFERENCES users(id),
    UNIQUE KEY unique_control_review (swo_id, reviewed_by)
);

-- 6. Create control_item_reviews table
CREATE TABLE IF NOT EXISTS control_item_reviews (
    id INT PRIMARY KEY AUTO_INCREMENT,
    swo_id INT NOT NULL,
    item_key VARCHAR(50) NOT NULL,
    control_decision VARCHAR(20),
    control_comment TEXT,
    reviewed_by INT,
    reviewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (swo_id) REFERENCES swo_list(id),
    FOREIGN KEY (reviewed_by) REFERENCES users(id),
    UNIQUE KEY unique_control_item_review (swo_id, item_key)
);

-- 7. Migrate existing 'Submitted' SWOs to 'Pending Support Review'
UPDATE swo_list SET status = 'Pending Support Review' WHERE status = 'Submitted';
