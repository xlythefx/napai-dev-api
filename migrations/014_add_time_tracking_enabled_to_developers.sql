ALTER TABLE developers
    ADD COLUMN time_tracking_enabled TINYINT(1) NOT NULL DEFAULT 1
    AFTER currency;
