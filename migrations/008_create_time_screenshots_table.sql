CREATE TABLE IF NOT EXISTS time_screenshots (
  id INT AUTO_INCREMENT PRIMARY KEY,
  session_id INT NOT NULL,
  developer_id INT NOT NULL,
  taken_at DATETIME NOT NULL,
  image_path VARCHAR(512) NOT NULL,
  activity_level TINYINT UNSIGNED NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_session_taken (session_id, taken_at),
  INDEX idx_dev_taken (developer_id, taken_at),
  CONSTRAINT fk_shot_session FOREIGN KEY (session_id) REFERENCES time_sessions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
