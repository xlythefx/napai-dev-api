-- Track when a bulk batch was emailed and to whom. Set together via
-- bulk_email.php for every key in the batch. NULL means never emailed.
ALTER TABLE license_keys
  ADD COLUMN bulk_emailed_at DATETIME NULL AFTER sell_type,
  ADD COLUMN bulk_emailed_to VARCHAR(255) NULL AFTER bulk_emailed_at;
CREATE INDEX idx_bulk_emailed ON license_keys (bulk_emailed_to, bulk_emailed_at);
