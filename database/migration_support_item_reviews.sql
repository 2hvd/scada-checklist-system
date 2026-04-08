-- Migration: Add support_item_reviews table for per-item Support decisions and comments

CREATE TABLE IF NOT EXISTS support_item_reviews (
    id INT PRIMARY KEY AUTO_INCREMENT,
    swo_id INT NOT NULL,
    item_key VARCHAR(50) NOT NULL,
    support_decision VARCHAR(20) DEFAULT NULL,  -- done, na, still, not_yet, or NULL for empty
    support_comment TEXT,
    reviewed_by INT,  -- support user who saved the review
    reviewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (swo_id) REFERENCES swo_list(id),
    FOREIGN KEY (reviewed_by) REFERENCES users(id),
    UNIQUE KEY unique_support_review (swo_id, item_key)
);
