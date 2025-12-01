-- Migration: add 'roles_edit' access right and map it to role_id = 3
-- Created: 2025-11-27

START TRANSACTION;

-- Insert the access right if it does not already exist
INSERT INTO access_rights (access_key, access_name, description)
SELECT 'roles_edit', 'Edit Roles', 'Allows editing roles and their access rights'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM access_rights WHERE access_key = 'roles_edit');

-- Fetch the access_id (whether newly inserted or existing)
SET @roles_edit_aid = (SELECT access_id FROM access_rights WHERE access_key = 'roles_edit' LIMIT 1);

-- Map the access right to role_id = 3 if mapping doesn't already exist
INSERT INTO role_access_rights (role_id, access_id)
SELECT 3, @roles_edit_aid
FROM DUAL
WHERE @roles_edit_aid IS NOT NULL AND NOT EXISTS (
  SELECT 1 FROM role_access_rights WHERE role_id = 3 AND access_id = @roles_edit_aid
);

COMMIT;
