-- Finance: bank/wallet accounts (current balances) and recurring monthly
-- income/expense items. Admin-scoped, mirrors finance_transactions (013).
-- fx_to_base converts each row's currency -> base currency (PHP) so combined
-- totals, runway and charts share a common unit without an external FX service.

CREATE TABLE IF NOT EXISTS finance_accounts (
    id          INT            AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(128)   NOT NULL,
    balance     DECIMAL(14,2)  NOT NULL DEFAULT 0,
    currency    VARCHAR(8)     NOT NULL DEFAULT 'PHP',
    fx_to_base  DECIMAL(16,6)  NOT NULL DEFAULT 1,   -- rate from `currency` -> PHP
    color       VARCHAR(16)    NULL,
    sort_order  INT            NOT NULL DEFAULT 0,
    created_by  INT            NULL,
    created_at  TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_acct_sort (sort_order),

    FOREIGN KEY (created_by) REFERENCES developers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS finance_recurring (
    id            INT            AUTO_INCREMENT PRIMARY KEY,
    type          ENUM('income','expense') NOT NULL,
    label         VARCHAR(128)   NOT NULL,
    category      VARCHAR(64)    NOT NULL DEFAULT 'subscription',
    amount        DECIMAL(14,2)  NOT NULL,
    currency      VARCHAR(8)     NOT NULL DEFAULT 'PHP',
    fx_to_base    DECIMAL(16,6)  NOT NULL DEFAULT 1,  -- rate from `currency` -> PHP
    day_of_month  TINYINT        NULL,                -- billing day (1-31), optional
    active        TINYINT(1)     NOT NULL DEFAULT 1,
    notes         TEXT           NULL,
    created_by    INT            NULL,
    created_at    TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_rec_type_active (type, active),

    FOREIGN KEY (created_by) REFERENCES developers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
