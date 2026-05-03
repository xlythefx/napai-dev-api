CREATE TABLE IF NOT EXISTS finance_transactions (
    id               INT           AUTO_INCREMENT PRIMARY KEY,
    type             ENUM('income','expense') NOT NULL,
    category         VARCHAR(64)   NOT NULL,
    subcategory      VARCHAR(128)  NULL,
    amount           DECIMAL(12,2) NOT NULL,
    currency         VARCHAR(8)    NOT NULL DEFAULT 'PHP',
    transaction_date DATE          NOT NULL,
    description      TEXT          NULL,
    priority         ENUM('high','mid','low') NOT NULL DEFAULT 'mid',
    payment_method   VARCHAR(64)   NULL,
    reference_id     INT           NULL,
    reference_type   VARCHAR(64)   NULL,
    tags             VARCHAR(512)  NULL,
    created_by       INT           NULL,
    created_at       TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_tx_date     (transaction_date),
    INDEX idx_type_cat    (type, category),
    INDEX idx_priority    (priority),
    UNIQUE KEY uniq_reference (reference_type, reference_id),

    FOREIGN KEY (created_by) REFERENCES developers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
