-- Migration: SWO Timeline Timestamp Columns
-- Ensures all timeline timestamp columns exist and adds started_at

ALTER TABLE swo_list ADD COLUMN IF NOT EXISTS started_at TIMESTAMP NULL DEFAULT NULL;

-- Create indexes for faster timeline queries
CREATE INDEX IF NOT EXISTS idx_swo_dates ON swo_list(created_at, assigned_at, submitted_at);
