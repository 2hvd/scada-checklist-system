-- Migration: User Item Comments
-- Allows users to add personal notes/comments on each checklist item

CREATE TABLE IF NOT EXISTS user_item_comments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    swo_id INT NOT NULL,
    item_key VARCHAR(50) NOT NULL,
    comment TEXT,
    user_id INT NOT NULL,
    saved_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (swo_id) REFERENCES swo_list(id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    UNIQUE KEY unique_user_comment (swo_id, item_key, user_id)
);
