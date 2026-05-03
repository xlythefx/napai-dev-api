ALTER TABLE developers ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS time_sessions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  developer_id INT NOT NULL,
  task VARCHAR(255) NULL,
  started_at DATETIME NOT NULL,
  ended_at DATETIME NULL,
  active_seconds INT NOT NULL DEFAULT 0,
  idle_seconds INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_dev_started (developer_id, started_at),
  CONSTRAINT fk_session_dev FOREIGN KEY (developer_id) REFERENCES developers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
