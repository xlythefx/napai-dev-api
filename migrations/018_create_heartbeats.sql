CREATE TABLE IF NOT EXISTS heartbeats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    serial VARCHAR(32) NOT NULL,
    android_id VARCHAR(128) NOT NULL,
    battery_pct INT NULL,
    app_version VARCHAR(32) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_serial_time (serial, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
