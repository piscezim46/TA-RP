-- Migration: Add `active` column to `roles` table
-- Date: 2025-11-27

ALTER TABLE roles
  ADD COLUMN `active` TINYINT(1) NOT NULL DEFAULT 1;

-- Ensure existing rows are marked active if null (defensive)
UPDATE roles SET active = 1 WHERE active IS NULL;
