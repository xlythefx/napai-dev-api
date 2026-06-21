-- Tier (plus/pro/max) and sell channel (retail/bulk) for license_keys.
-- Stored as VARCHAR rather than ENUM so we can extend later without another
-- migration (e.g. add 'enterprise'). App-level validation enforces values.
ALTER TABLE license_keys
  ADD COLUMN type VARCHAR(8) NOT NULL DEFAULT 'pro' AFTER kind,
  ADD COLUMN sell_type VARCHAR(8) NOT NULL DEFAULT 'retail' AFTER type;
