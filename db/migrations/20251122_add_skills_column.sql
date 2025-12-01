-- Migration: add skills column to applicants
-- Run this SQL against your database to add the `skills` column if it doesn't exist.

ALTER TABLE `applicants` 
  ADD COLUMN IF NOT EXISTS `skills` TEXT NULL DEFAULT NULL;

-- If your MySQL version doesn't support `IF NOT EXISTS` for ADD COLUMN,
-- run the following instead (uncomment and execute manually):
--
-- SET @cnt = (
--   SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
--   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'applicants' AND COLUMN_NAME = 'skills'
-- );
-- SELECT @cnt;
--
-- IF @cnt = 0 THEN
--   ALTER TABLE `applicants` ADD COLUMN `skills` TEXT NULL DEFAULT NULL;
-- END IF;
