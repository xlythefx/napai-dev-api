ALTER TABLE time_tracker_releases
  ADD COLUMN auto_download TINYINT(1) NOT NULL DEFAULT 0 AFTER is_current;
