USE scada_checklist;

-- Insert users (passwords are bcrypt hashed)
-- admin123 -> hashed
-- support123 -> hashed
-- user123 -> hashed

INSERT INTO users (username, password, email, role) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@scada.local', 'admin'),
('support', '$2y$10$TKh8H1.PFbuSpgvguXe/vuH0r/jJiDkFxJuVRL56VlFkPCi3Ot8cO', 'support@scada.local', 'support'),
('user1', '$2y$10$T3N8OmkFtKJiYtKZpq6vFuH5tAq4BF0rHQ.fOcSYM4ld1UEvW0qXi', 'user1@scada.local', 'user');

-- Note: Use setup_passwords.php to regenerate hashes, or run the following in PHP:
-- admin: password_hash('admin123', PASSWORD_DEFAULT)
-- support: password_hash('support123', PASSWORD_DEFAULT)
-- user1: password_hash('user123', PASSWORD_DEFAULT)

-- Insert sample SWOs
INSERT INTO swo_list (swo_number, station_name, swo_type, kcor, description, status, created_by, approved_by, assigned_to, approved_at, assigned_at)
VALUES
('SWO-2024-001', 'Substation Alpha', 'Configuration', 'KCOR-001', 'Full SCADA configuration for Substation Alpha including PLC setup and HMI screens.', 'In Progress', 2, 1, 3, NOW(), NOW()),
('SWO-2024-002', 'Pumping Station Beta', 'Commissioning', 'KCOR-002', 'Commissioning activities for Beta pumping station water treatment SCADA.', 'Registered', 2, 1, NULL, NOW(), NULL),
('SWO-2024-003', 'Control Room Gamma', 'Configuration', 'KCOR-003', 'New control room SCADA deployment with full redundancy setup.', 'Pending', 2, NULL, NULL, NULL, NULL);

-- Initialize checklist items for SWO-2024-001 (In Progress, assigned to user1)
INSERT INTO checklist_status (user_id, swo_id, item_key, status) VALUES
(3, 1, 'during_config_1', 'done'),
(3, 1, 'during_config_2', 'done'),
(3, 1, 'during_config_3', 'still'),
(3, 1, 'during_config_4', 'empty'),
(3, 1, 'during_config_5', 'empty'),
(3, 1, 'during_config_6', 'empty'),
(3, 1, 'during_config_7', 'empty'),
(3, 1, 'during_config_8', 'empty'),
(3, 1, 'during_commissioning_1', 'empty'),
(3, 1, 'during_commissioning_2', 'empty'),
(3, 1, 'during_commissioning_3', 'empty'),
(3, 1, 'during_commissioning_4', 'empty'),
(3, 1, 'during_commissioning_5', 'empty'),
(3, 1, 'during_commissioning_6', 'empty'),
(3, 1, 'during_commissioning_7', 'empty'),
(3, 1, 'during_commissioning_8', 'empty'),
(3, 1, 'after_commissioning_1', 'empty'),
(3, 1, 'after_commissioning_2', 'empty'),
(3, 1, 'after_commissioning_3', 'empty'),
(3, 1, 'after_commissioning_4', 'empty'),
(3, 1, 'after_commissioning_5', 'empty'),
(3, 1, 'after_commissioning_6', 'empty'),
(3, 1, 'after_commissioning_7', 'empty'),
(3, 1, 'after_commissioning_8', 'empty');

-- Sample comments
INSERT INTO comments (user_id, swo_id, item_key, comment_text) VALUES
(3, 1, 'during_config_1', 'PLC communication verified with Modbus TCP. All registers mapped correctly.'),
(1, 1, NULL, 'Please ensure historian settings are configured before commissioning.');
