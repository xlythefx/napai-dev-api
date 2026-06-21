-- Finance: capital allocations / reserved capital (e.g. "Property Down-payment",
-- "Locked BTC", earmarked investment). Each allocation has an `amount` that is
-- DEDUCTED from the combined Total Balance (sum of finance_accounts in base PHP)
-- to produce the Spendable Balance, computed in controllers/finance/overview.php.
-- fx_to_base converts the allocation's currency -> base currency (PHP) so an
-- allocation priced in USD/EUR can still be subtracted from the PHP total balance.

CREATE TABLE IF NOT EXISTS finance_capital_allocations (
    id            INT            AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(128)   NOT NULL,                      -- "Property Down-payment", "Locked BTC"
    icon          VARCHAR(32)    NOT NULL DEFAULT 'lock',       -- lucide icon key: lock, vault, home...
    amount        DECIMAL(16,2)  NOT NULL,                      -- reserved amount
    currency      VARCHAR(8)     NOT NULL DEFAULT 'PHP',
    fx_to_base    DECIMAL(16,6)  NOT NULL DEFAULT 1,            -- rate from `currency` -> PHP
    color         VARCHAR(16)    NULL,
    notes         TEXT           NULL,
    sort_order    INT            NOT NULL DEFAULT 0,
    created_by    INT            NULL,
    created_at    TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_alloc_sort (sort_order),

    FOREIGN KEY (created_by) REFERENCES developers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
