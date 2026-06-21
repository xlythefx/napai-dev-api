-- Trial keys live in the same table as lifetime keys via a `kind` column.
-- expires_at is set on first activation for trials, NULL for lifetime.
-- buyer_email lets us auto-email QR codes when trials are requested.
ALTER TABLE license_keys ADD COLUMN kind VARCHAR(16) NOT NULL DEFAULT 'lifetime';
ALTER TABLE license_keys ADD COLUMN expires_at DATETIME NULL;
ALTER TABLE license_keys ADD COLUMN buyer_email VARCHAR(255) NULL;
ALTER TABLE license_keys ADD COLUMN buyer_ip VARCHAR(45) NULL;
