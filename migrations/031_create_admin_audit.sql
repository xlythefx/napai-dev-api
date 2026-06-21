-- Append-only audit trail for admin-mutating tablet endpoints. Every call to
-- keys_generate / keys_revoke / keys_release / keys_convert / keys_note /
-- bulk_email / provisioning_email / settings_update / apk_* writes one row
-- via tablet_audit() in lib/tablet.php. payload_json holds endpoint-specific
-- context (counts, filters, fields changed) for post-incident forensics.
CREATE TABLE IF NOT EXISTS tablet_admin_audit (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  actor_email   VARCHAR(255) NOT NULL,
  action        VARCHAR(64)  NOT NULL,
  target_serial VARCHAR(32)  NULL,
  ip            VARCHAR(45)  NULL,
  payload_json  TEXT         NULL,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_actor_created  (actor_email, created_at),
  KEY idx_action_created (action, created_at),
  KEY idx_target         (target_serial, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
