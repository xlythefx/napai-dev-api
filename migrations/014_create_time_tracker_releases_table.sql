CREATE TABLE IF NOT EXISTS time_tracker_releases (
  id INT AUTO_INCREMENT PRIMARY KEY,
  version VARCHAR(64) NOT NULL,
  platform VARCHAR(16) NOT NULL DEFAULT 'win',
  filename VARCHAR(255) NOT NULL,
  file_path VARCHAR(512) NOT NULL,
  file_size BIGINT NOT NULL DEFAULT 0,
  sha256 CHAR(64) NULL,
  notes TEXT NULL,
  is_current TINYINT(1) NOT NULL DEFAULT 0,
  uploaded_by INT NULL,
  uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  download_count INT NOT NULL DEFAULT 0,
  INDEX idx_platform_current (platform, is_current),
  INDEX idx_uploaded_at (uploaded_at),
  CONSTRAINT fk_release_uploader FOREIGN KEY (uploaded_by) REFERENCES developers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
