CREATE TABLE IF NOT EXISTS developer_payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  developer_id INT NOT NULL,
  payment_date DATE NOT NULL,
  amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  currency VARCHAR(8) NOT NULL DEFAULT 'PHP',
  active_seconds INT NOT NULL DEFAULT 0,
  proof_path VARCHAR(512) NULL,
  note VARCHAR(500) NULL,
  paid_by INT NULL,
  paid_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_dev_date (developer_id, payment_date),
  INDEX idx_payment_date (payment_date),
  CONSTRAINT fk_payment_dev FOREIGN KEY (developer_id) REFERENCES developers(id) ON DELETE CASCADE,
  CONSTRAINT fk_payment_admin FOREIGN KEY (paid_by) REFERENCES developers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
