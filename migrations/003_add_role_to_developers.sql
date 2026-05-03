-- Add role column for admin access (developer | admin)
ALTER TABLE developers ADD COLUMN role VARCHAR(20) DEFAULT 'developer';
