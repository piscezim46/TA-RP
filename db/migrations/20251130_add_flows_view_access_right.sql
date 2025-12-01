-- Migration: add 'flows_view' access right and map it to 'admin' and 'hr' roles if present
-- Created: 2025-11-30

START TRANSACTION;

-- Insert the access right if it does not already exist
INSERT INTO access_rights (access_key, access_name, description)
SELECT 'flows_view', 'View Flows', 'Allows viewing and interacting with the Position Ticket Flows page'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM access_rights WHERE access_key = 'flows_view');

-- Fetch the access_id
SET @flows_view_aid = (SELECT access_id FROM access_rights WHERE access_key = 'flows_view' LIMIT 1);

-- Map the access right to any roles named 'admin' or 'hr' (if such roles exist) and mapping doesn't already exist
INSERT INTO role_access_rights (role_id, access_id)
SELECT r.role_id, @flows_view_aid
FROM roles r
WHERE r.role_name IN ('admin','hr')
  AND @flows_view_aid IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM role_access_rights WHERE role_id = r.role_id AND access_id = @flows_view_aid);

COMMIT;
