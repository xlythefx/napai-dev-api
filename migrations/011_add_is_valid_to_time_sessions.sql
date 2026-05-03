ALTER TABLE time_sessions
  ADD COLUMN is_valid TINYINT(1) NOT NULL DEFAULT 1 AFTER idle_seconds,
  ADD COLUMN invalidated_reason VARCHAR(255) NULL AFTER is_valid;
