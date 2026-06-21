CREATE TABLE IF NOT EXISTS apk_versions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    version VARCHAR(32) NOT NULL,
    filename VARCHAR(255) NOT NULL,
    size_bytes BIGINT NOT NULL,
    sha256_b64 VARCHAR(64) NOT NULL,
    notes TEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 0,
    uploaded_by_email VARCHAR(255) NULL,
    uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_active (is_active),
    INDEX idx_uploaded (uploaded_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
