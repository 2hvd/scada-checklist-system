-- Migration: Add role-based visibility + per-role child mapping for checklist items

ALTER TABLE checklist_items
    ADD COLUMN IF NOT EXISTS visible_user TINYINT(1) NOT NULL DEFAULT 1 AFTER parent_item_id,
    ADD COLUMN IF NOT EXISTS visible_support TINYINT(1) NOT NULL DEFAULT 1 AFTER visible_user,
    ADD COLUMN IF NOT EXISTS visible_control TINYINT(1) NOT NULL DEFAULT 1 AFTER visible_support,
    ADD COLUMN IF NOT EXISTS user_parent_item_id INT NULL AFTER visible_control,
    ADD COLUMN IF NOT EXISTS support_parent_item_id INT NULL AFTER user_parent_item_id,
    ADD COLUMN IF NOT EXISTS control_parent_item_id INT NULL AFTER support_parent_item_id;

ALTER TABLE checklist_items
    ADD CONSTRAINT fk_checklist_items_user_parent
        FOREIGN KEY (user_parent_item_id) REFERENCES checklist_items(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_checklist_items_support_parent
        FOREIGN KEY (support_parent_item_id) REFERENCES checklist_items(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_checklist_items_control_parent
        FOREIGN KEY (control_parent_item_id) REFERENCES checklist_items(id) ON DELETE SET NULL;
