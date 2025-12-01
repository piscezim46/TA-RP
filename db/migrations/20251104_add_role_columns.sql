-- Migration: add role responsibilities and expectations to positions
-- Run this against your database (e.g. in phpMyAdmin or mysql CLI)

ALTER TABLE `positions`
  ADD COLUMN `role_responsibilities` MEDIUMTEXT NULL AFTER `description`,
  ADD COLUMN `role_expectations` MEDIUMTEXT NULL AFTER `role_responsibilities`;

-- End
