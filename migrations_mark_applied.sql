-- Run ONCE on nap-ai.com to backfill the migrations tracking table with
-- everything that's already been applied by hand. After this, `php migrate.php`
-- will skip 001–033 and only run new files.

CREATE TABLE IF NOT EXISTS migrations (
  name VARCHAR(255) PRIMARY KEY,
  run_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT IGNORE INTO migrations (name) VALUES
  ('001_create_developers_table.sql'),
  ('002_create_rag_tables.sql'),
  ('003_add_role_to_developers.sql'),
  ('004_create_rag_chats.sql'),
  ('005_documents_chat_id.sql'),
  ('006_add_hourly_rate_to_developers.sql'),
  ('007_create_time_sessions_table.sql'),
  ('008_create_time_screenshots_table.sql'),
  ('009_create_projects_table.sql'),
  ('010_add_project_id_to_time_sessions.sql'),
  ('011_add_is_valid_to_time_sessions.sql'),
  ('012_create_developer_payments_table.sql'),
  ('013_create_finance_transactions_table.sql'),
  ('014_add_time_tracking_enabled_to_developers.sql'),
  ('014_create_time_tracker_releases_table.sql'),
  ('015_add_auto_download_to_time_tracker_releases.sql'),
  ('016_create_license_keys.sql'),
  ('017_create_activations_log.sql'),
  ('018_create_heartbeats.sql'),
  ('019_create_apk_versions.sql'),
  ('020_create_email_log.sql'),
  ('021_seed_admin_developer.sql'),
  ('022_seed_dev_license.sql'),
  ('023_add_trial_columns_to_license_keys.sql'),
  ('024_add_trial_indexes.sql'),
  ('025_add_tier_and_sell_type.sql'),
  ('026_add_tier_indexes.sql'),
  ('027_add_bulk_email_columns.sql'),
  ('028_add_custom_price.sql'),
  ('029_create_tablet_settings.sql'),
  ('030_create_trial_requests_log.sql'),
  ('031_create_admin_audit.sql'),
  ('032_create_trial_otp.sql'),
  ('033_add_device_fingerprint.sql');
