-- Simple key/value store for tablet-saas runtime toggles. Read by lib/tablet.php
-- helpers (tablet_get_setting / tablet_email_enabled / tablet_is_maintenance)
-- and written by controllers/tablet/settings_update.php.
CREATE TABLE IF NOT EXISTS tablet_settings (
  `key`   VARCHAR(64) NOT NULL PRIMARY KEY,
  `value` TEXT NOT NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed defaults: emailing enabled, maintenance off.
INSERT INTO tablet_settings (`key`, `value`) VALUES
  ('email_keys_enabled', '1'),
  ('is_maintenance',     '0')
ON DUPLICATE KEY UPDATE `value` = `value`;
