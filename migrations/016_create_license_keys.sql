CREATE TABLE IF NOT EXISTS license_keys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    serial VARCHAR(32) UNIQUE NOT NULL,
    android_id VARCHAR(128) NULL,
    activated_at DATETIME NULL,
    revoked TINYINT(1) NOT NULL DEFAULT 0,
    note VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_android (android_id),
    INDEX idx_revoked (revoked)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
