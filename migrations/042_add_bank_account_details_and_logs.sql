-- Finance: richer bank-account details + a change/audit trail.
-- Adds free-text notes and an archive status to finance_accounts, and a
-- per-account log table that records create / update / archive / restore /
-- delete events (with balance before/after/delta and an optional reason note)
-- so every change to a bank account is fully traceable.

ALTER TABLE finance_accounts
    ADD COLUMN notes  TEXT NULL AFTER color,
    ADD COLUMN status ENUM('active','archived') NOT NULL DEFAULT 'active' AFTER notes,
    ADD INDEX idx_acct_status (status);

CREATE TABLE IF NOT EXISTS finance_account_logs (
    id               INT            AUTO_INCREMENT PRIMARY KEY,
    account_id       INT            NULL,           -- nullable: log survives a hard delete
    account_name     VARCHAR(128)   NULL,           -- snapshot of the name at event time
    action           ENUM('create','update','archive','restore','delete') NOT NULL,
    changes          TEXT           NULL,           -- JSON array: [{field, from, to}]
    balance_before   DECIMAL(14,2)  NULL,
    balance_after    DECIMAL(14,2)  NULL,
    balance_delta    DECIMAL(14,2)  NULL,
    currency         VARCHAR(8)     NULL,
    note             VARCHAR(500)   NULL,           -- optional reason supplied at change time
    changed_by       INT            NULL,
    changed_by_email VARCHAR(190)   NULL,
    changed_at       TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_aclog_account (account_id, changed_at),
    INDEX idx_aclog_changed_at (changed_at),

    FOREIGN KEY (account_id) REFERENCES finance_accounts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
