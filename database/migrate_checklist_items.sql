-- Migration: Create checklist_items table and populate with existing hardcoded items
-- Run this script once to migrate the system to dynamic checklist management.

USE scada_checklist;

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

-- Populate with existing hardcoded items (during_config)
INSERT IGNORE INTO checklist_items (section, section_number, description, item_key, is_active, created_by) VALUES
('during_config', 1, 'Verify PLC communication settings',  'during_config_1', 1, NULL),
('during_config', 2, 'Configure I/O mapping',              'during_config_2', 1, NULL),
('during_config', 3, 'Set up HMI screens',                 'during_config_3', 1, NULL),
('during_config', 4, 'Configure alarm setpoints',          'during_config_4', 1, NULL),
('during_config', 5, 'Test network connectivity',          'during_config_5', 1, NULL),
('during_config', 6, 'Configure historian settings',       'during_config_6', 1, NULL),
('during_config', 7, 'Set up redundancy parameters',       'during_config_7', 1, NULL),
('during_config', 8, 'Configure security settings',        'during_config_8', 1, NULL),

-- Populate with existing hardcoded items (during_commissioning)
('during_commissioning', 1, 'Perform I/O loop checks',              'during_commissioning_1', 1, NULL),
('during_commissioning', 2, 'Test control logic',                   'during_commissioning_2', 1, NULL),
('during_commissioning', 3, 'Verify HMI functionality',             'during_commissioning_3', 1, NULL),
('during_commissioning', 4, 'Test alarm system',                    'during_commissioning_4', 1, NULL),
('during_commissioning', 5, 'Validate historian data logging',      'during_commissioning_5', 1, NULL),
('during_commissioning', 6, 'Test redundancy failover',             'during_commissioning_6', 1, NULL),
('during_commissioning', 7, 'Perform communication stress test',    'during_commissioning_7', 1, NULL),
('during_commissioning', 8, 'Document as-built configuration',      'during_commissioning_8', 1, NULL),

-- Populate with existing hardcoded items (after_commissioning)
('after_commissioning', 1, 'Verify system performance under load', 'after_commissioning_1', 1, NULL),
('after_commissioning', 2, 'Complete operator training',           'after_commissioning_2', 1, NULL),
('after_commissioning', 3, 'Finalize documentation',              'after_commissioning_3', 1, NULL),
('after_commissioning', 4, 'Archive project files',               'after_commissioning_4', 1, NULL),
('after_commissioning', 5, 'Transfer system to operations',        'after_commissioning_5', 1, NULL),
('after_commissioning', 6, 'Perform 24-hour monitoring',           'after_commissioning_6', 1, NULL),
('after_commissioning', 7, 'Resolve punch list items',             'after_commissioning_7', 1, NULL),
('after_commissioning', 8, 'Obtain client sign-off',               'after_commissioning_8', 1, NULL);
