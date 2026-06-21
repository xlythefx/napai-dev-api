CREATE TABLE IF NOT EXISTS activations_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    serial VARCHAR(32) NOT NULL,
    android_id VARCHAR(128) NOT NULL,
    ip VARCHAR(45) NULL,
    result VARCHAR(32) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_serial (serial),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
