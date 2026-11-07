-- Finance: loaning power / borrowing capacity (e.g. "Hexagon CC", a credit card or
-- line of credit that can be drawn as cash). Each source has a `limit_amount`
-- (the total credit-to-cash it can provide) and a `used_amount` (already drawn).
-- available = limit - used. The combined available credit (converted to base PHP)
-- is added to the Spendable Balance to produce the Buying Power, computed in
-- controllers/finance/overview.php. Unlike capital allocations these amounts are
-- NOT money you own — they are borrowing capacity you could tap.
-- fx_to_base converts the source's currency -> base currency (PHP).

CREATE TABLE IF NOT EXISTS finance_loaning_power (
    id            INT            AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(128)   NOT NULL,                      -- "Hexagon CC", "BPI Cash Loan"
    icon          VARCHAR(32)    NOT NULL DEFAULT 'credit-card',-- lucide icon key: credit-card, banknote, coins...
    limit_amount  DECIMAL(16,2)  NOT NULL,                      -- total credit-to-cash limit
    used_amount   DECIMAL(16,2)  NOT NULL DEFAULT 0,            -- already drawn / outstanding
    currency      VARCHAR(8)     NOT NULL DEFAULT 'PHP',
    fx_to_base    DECIMAL(16,6)  NOT NULL DEFAULT 1,            -- rate from `currency` -> PHP
    color         VARCHAR(16)    NULL,
    notes         TEXT           NULL,
    sort_order    INT            NOT NULL DEFAULT 0,
    created_by    INT            NULL,
    created_at    TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_loan_sort (sort_order),

    FOREIGN KEY (created_by) REFERENCES developers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
