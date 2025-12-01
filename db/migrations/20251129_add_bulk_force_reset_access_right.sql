-- Migration: add 'users_bulk_force_reset' access right and map it to the 'admin' role if present
-- Created: 2025-11-29

START TRANSACTION;

-- Insert the access right if it does not already exist
INSERT INTO access_rights (access_key, access_name, description)
SELECT 'users_bulk_force_reset', 'Bulk Force Password Reset', 'Allows forcing a password reset for multiple users at once'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM access_rights WHERE access_key = 'users_bulk_force_reset');

-- Fetch the access_id
SET @bulk_reset_aid = (SELECT access_id FROM access_rights WHERE access_key = 'users_bulk_force_reset' LIMIT 1);

-- Map the access right to any role named 'admin' (if such a role exists) and mapping doesn't already exist
INSERT INTO role_access_rights (role_id, access_id)
SELECT r.role_id, @bulk_reset_aid
FROM roles r
WHERE r.role_name = 'admin'
  AND @bulk_reset_aid IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM role_access_rights WHERE role_id = r.role_id AND access_id = @bulk_reset_aid);

COMMIT;
