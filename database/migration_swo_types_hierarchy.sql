-- Migration: SWO Types management, Items hierarchy (Parent/Sub), Type-based filtering
-- Run this script once to add SWO types and parent/sub-item hierarchy.

USE scada_checklist;

-- 1. Create swo_types table
CREATE TABLE IF NOT EXISTS swo_types (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- 2. Seed default SWO types
INSERT IGNORE INTO swo_types (name, description, is_active, created_by) VALUES
('S/S', 'Substation', 1, NULL),
('Power Plant', 'Power Generation Plant', 1, NULL),
('Line', 'Transmission/Distribution Line', 1, NULL);

-- 3. Add swo_type_id and parent_item_id to checklist_items
ALTER TABLE checklist_items
    ADD COLUMN swo_type_id INT NULL AFTER item_key,
    ADD COLUMN parent_item_id INT NULL AFTER swo_type_id,
    ADD FOREIGN KEY (swo_type_id) REFERENCES swo_types(id),
    ADD FOREIGN KEY (parent_item_id) REFERENCES checklist_items(id);

-- 4. Update swo_list.swo_type from VARCHAR to INT (referencing swo_types)
-- Note: This requires migrating existing data. For existing SWOs with string types,
-- we keep the original swo_type column as-is and add a new swo_type_id column.
ALTER TABLE swo_list
    ADD COLUMN swo_type_id INT NULL AFTER swo_type,
    ADD FOREIGN KEY (swo_type_id) REFERENCES swo_types(id);
