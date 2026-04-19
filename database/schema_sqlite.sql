PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE NOT NULL,
    password TEXT NOT NULL,
    email TEXT,
    role TEXT NOT NULL CHECK(role IN ('admin','support','control','user')),
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    active INTEGER DEFAULT 1
);

CREATE TABLE IF NOT EXISTS swo_types (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    description TEXT,
    is_active INTEGER DEFAULT 1,
    created_by INTEGER,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS swo_list (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    swo_number TEXT UNIQUE NOT NULL,
    station_name TEXT NOT NULL,
    swo_type TEXT NOT NULL,
    swo_type_id INTEGER,
    kcor TEXT,
    description TEXT,
    status TEXT DEFAULT 'Draft',
    created_by INTEGER,
    assigned_to INTEGER,
    approved_by INTEGER,
    support_reviewer_id INTEGER,
    control_reviewer_id INTEGER,
    rejection_reason TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    approved_at TEXT,
    assigned_at TEXT,
    started_at TEXT,
    submitted_at TEXT,
    support_reviewed_at TEXT,
    control_reviewed_at TEXT,
    closed_at TEXT,
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (assigned_to) REFERENCES users(id),
    FOREIGN KEY (approved_by) REFERENCES users(id),
    FOREIGN KEY (swo_type_id) REFERENCES swo_types(id)
);

CREATE TABLE IF NOT EXISTS checklist_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    section TEXT NOT NULL,
    section_number INTEGER NOT NULL,
    description TEXT NOT NULL,
    item_key TEXT UNIQUE NOT NULL,
    swo_type_id INTEGER,
    parent_item_id INTEGER,
    visible_user INTEGER NOT NULL DEFAULT 1,
    visible_support INTEGER NOT NULL DEFAULT 1,
    visible_control INTEGER NOT NULL DEFAULT 1,
    user_parent_item_id INTEGER,
    support_parent_item_id INTEGER,
    control_parent_item_id INTEGER,
    is_active INTEGER DEFAULT 1,
    is_deleted INTEGER DEFAULT 0,
    created_by INTEGER,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (swo_type_id) REFERENCES swo_types(id),
    FOREIGN KEY (parent_item_id) REFERENCES checklist_items(id),
    FOREIGN KEY (user_parent_item_id) REFERENCES checklist_items(id) ON DELETE SET NULL,
    FOREIGN KEY (support_parent_item_id) REFERENCES checklist_items(id) ON DELETE SET NULL,
    FOREIGN KEY (control_parent_item_id) REFERENCES checklist_items(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS checklist_status (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    swo_id INTEGER NOT NULL,
    item_key TEXT NOT NULL,
    status TEXT DEFAULT 'empty',
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (user_id, swo_id, item_key),
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (swo_id) REFERENCES swo_list(id)
);

CREATE TABLE IF NOT EXISTS comments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    swo_id INTEGER NOT NULL,
    item_key TEXT,
    comment_text TEXT NOT NULL,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (swo_id) REFERENCES swo_list(id)
);

CREATE TABLE IF NOT EXISTS user_item_comments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    swo_id INTEGER NOT NULL,
    item_key TEXT NOT NULL,
    comment TEXT,
    user_id INTEGER NOT NULL,
    saved_at TEXT DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (swo_id, item_key, user_id),
    FOREIGN KEY (swo_id) REFERENCES swo_list(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS support_reviews (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    swo_id INTEGER NOT NULL,
    reviewed_by INTEGER NOT NULL,
    decision TEXT NOT NULL,
    comments TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (swo_id, reviewed_by),
    FOREIGN KEY (swo_id) REFERENCES swo_list(id),
    FOREIGN KEY (reviewed_by) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS control_reviews (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    swo_id INTEGER NOT NULL,
    reviewed_by INTEGER NOT NULL,
    decision TEXT NOT NULL,
    comments TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (swo_id, reviewed_by),
    FOREIGN KEY (swo_id) REFERENCES swo_list(id),
    FOREIGN KEY (reviewed_by) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS support_item_reviews (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    swo_id INTEGER NOT NULL,
    item_key TEXT NOT NULL,
    support_decision TEXT,
    support_comment TEXT,
    reviewed_by INTEGER,
    reviewed_at TEXT DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (swo_id, item_key),
    FOREIGN KEY (swo_id) REFERENCES swo_list(id),
    FOREIGN KEY (reviewed_by) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS control_item_reviews (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    swo_id INTEGER NOT NULL,
    item_key TEXT NOT NULL,
    control_decision TEXT,
    control_comment TEXT,
    reviewed_by INTEGER,
    reviewed_at TEXT DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (swo_id, item_key),
    FOREIGN KEY (swo_id) REFERENCES swo_list(id),
    FOREIGN KEY (reviewed_by) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS audit_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    swo_id INTEGER,
    action TEXT NOT NULL,
    old_status TEXT,
    new_status TEXT,
    timestamp TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (swo_id) REFERENCES swo_list(id)
);

CREATE TABLE IF NOT EXISTS submissions_history (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    swo_id INTEGER NOT NULL,
    action TEXT NOT NULL,
    notes TEXT,
    timestamp TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (swo_id) REFERENCES swo_list(id)
);

CREATE INDEX IF NOT EXISTS idx_swo_dates ON swo_list(created_at, assigned_at, submitted_at);

INSERT OR IGNORE INTO swo_types (name, description, is_active, created_by) VALUES
('S/S', 'Substation', 1, NULL),
('Power Plant', 'Power Generation Plant', 1, NULL),
('Line', 'Transmission/Distribution Line', 1, NULL);

INSERT OR IGNORE INTO checklist_items (section, section_number, description, item_key, is_active, created_by) VALUES
('during_config', 1, 'Verify PLC communication settings',  'during_config_1', 1, NULL),
('during_config', 2, 'Configure I/O mapping',              'during_config_2', 1, NULL),
('during_config', 3, 'Set up HMI screens',                 'during_config_3', 1, NULL),
('during_config', 4, 'Configure alarm setpoints',          'during_config_4', 1, NULL),
('during_config', 5, 'Test network connectivity',          'during_config_5', 1, NULL),
('during_config', 6, 'Configure historian settings',       'during_config_6', 1, NULL),
('during_config', 7, 'Set up redundancy parameters',       'during_config_7', 1, NULL),
('during_config', 8, 'Configure security settings',        'during_config_8', 1, NULL),
('during_commissioning', 1, 'Perform I/O loop checks',              'during_commissioning_1', 1, NULL),
('during_commissioning', 2, 'Test control logic',                   'during_commissioning_2', 1, NULL),
('during_commissioning', 3, 'Verify HMI functionality',             'during_commissioning_3', 1, NULL),
('during_commissioning', 4, 'Test alarm system',                    'during_commissioning_4', 1, NULL),
('during_commissioning', 5, 'Validate historian data logging',      'during_commissioning_5', 1, NULL),
('during_commissioning', 6, 'Test redundancy failover',             'during_commissioning_6', 1, NULL),
('during_commissioning', 7, 'Perform communication stress test',    'during_commissioning_7', 1, NULL),
('during_commissioning', 8, 'Document as-built configuration',      'during_commissioning_8', 1, NULL),
('after_commissioning', 1, 'Verify system performance under load', 'after_commissioning_1', 1, NULL),
('after_commissioning', 2, 'Complete operator training',           'after_commissioning_2', 1, NULL),
('after_commissioning', 3, 'Finalize documentation',               'after_commissioning_3', 1, NULL),
('after_commissioning', 4, 'Archive project files',                'after_commissioning_4', 1, NULL),
('after_commissioning', 5, 'Transfer system to operations',        'after_commissioning_5', 1, NULL),
('after_commissioning', 6, 'Perform 24-hour monitoring',           'after_commissioning_6', 1, NULL),
('after_commissioning', 7, 'Resolve punch list items',             'after_commissioning_7', 1, NULL),
('after_commissioning', 8, 'Obtain client sign-off',               'after_commissioning_8', 1, NULL);
