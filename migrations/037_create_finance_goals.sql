-- Finance: financial goals / net-worth milestones (e.g. "My First Million",
-- "Civic FC", a motorcycle). Each goal has a target amount; progress is measured
-- against the combined Total Balance (sum of finance_accounts in base PHP),
-- computed in controllers/finance/overview.php.
-- fx_to_base converts the goal's currency -> base currency (PHP) so a goal priced
-- in USD/EUR can still be compared against the PHP total balance.

CREATE TABLE IF NOT EXISTS finance_goals (
    id            INT            AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(128)   NOT NULL,                      -- "My First Million", "Civic FC"
    icon          VARCHAR(32)    NOT NULL DEFAULT 'target',     -- lucide icon key: car, bike, home, trophy...
    target_amount DECIMAL(16,2)  NOT NULL,
    currency      VARCHAR(8)     NOT NULL DEFAULT 'PHP',
    fx_to_base    DECIMAL(16,6)  NOT NULL DEFAULT 1,            -- rate from `currency` -> PHP
    color         VARCHAR(16)    NULL,
    target_date   DATE           NULL,                          -- optional "by when"
    notes         TEXT           NULL,
    sort_order    INT            NOT NULL DEFAULT 0,
    created_by    INT            NULL,
    created_at    TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_goal_sort (sort_order),

    FOREIGN KEY (created_by) REFERENCES developers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
