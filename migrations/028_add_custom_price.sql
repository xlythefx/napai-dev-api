-- Optional per-key custom price (PHP). NULL = use the tier's default price
-- from config (Plus 299, Pro 349, Max 499). Set when an admin gives a reseller
-- a discount on a bulk batch. Stored per-key (not per-batch) so future
-- promotions can target individual serials.
ALTER TABLE license_keys
  ADD COLUMN custom_price DECIMAL(10,2) NULL AFTER sell_type;
