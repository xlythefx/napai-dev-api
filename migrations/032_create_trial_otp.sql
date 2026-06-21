-- Short-lived one-time codes mailed to trial requesters. The user proves
-- email ownership by round-tripping the code before request_trial.php will
-- mint a serial. Codes are stored as SHA-256 hash (never plaintext).
-- A row's lifecycle: send → user enters → consumed=1 OR attempts>=5 OR expired.
CREATE TABLE IF NOT EXISTS trial_otp (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  email      VARCHAR(255) NOT NULL,
  code_hash  CHAR(64)     NOT NULL,
  ip         VARCHAR(45)  NULL,
  expires_at DATETIME     NOT NULL,
  attempts   INT          NOT NULL DEFAULT 0,
  consumed   TINYINT(1)   NOT NULL DEFAULT 0,
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_email_created (email, created_at),
  KEY idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
