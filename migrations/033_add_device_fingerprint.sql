-- Composite hardware fingerprint reported by the Flutter app at activation
-- time. Used to refuse a fresh trial when the same physical device has
-- already burned its one-trial-per-device quota — defeats the
-- factory-reset-to-re-trial attack that android_id alone can't see through.
-- Nullable for backwards-compat with older APKs that don't report it.
ALTER TABLE license_keys
  ADD COLUMN device_fingerprint CHAR(64) NULL AFTER android_id;
CREATE INDEX idx_device_fingerprint ON license_keys (device_fingerprint);
