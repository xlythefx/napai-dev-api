-- Fast lookup for "has this android_id ever activated a trial?"
CREATE INDEX idx_android_kind ON license_keys (android_id, kind);
-- Fast lookup for the "expiring trials" admin view.
CREATE INDEX idx_kind_expires ON license_keys (kind, expires_at);
-- Rate-limit trial requests by buyer email.
CREATE INDEX idx_buyer_email ON license_keys (buyer_email);
