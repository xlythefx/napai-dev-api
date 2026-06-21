-- Per-IP throttle log for the public /controllers/tablet/request_trial.php
-- endpoint. Rows are written on every attempt (success AND refused) so the
-- limiter can be enforced cluster-wide. email is stored as a sha256 hex
-- digest, not plaintext, so the table doesn't become a juicy PII target.
CREATE TABLE IF NOT EXISTS trial_requests (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  ip           VARCHAR(45)  NOT NULL,
  email_hash   CHAR(64)     NOT NULL,
  result       VARCHAR(32)  NOT NULL,   -- 'ok' | 'rate_limit_ip_1h' | 'rate_limit_ip_24h' | 'rate_limit_email' | 'invalid_email' | etc.
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_ip_created (ip, created_at),
  KEY idx_email_hash (email_hash, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
