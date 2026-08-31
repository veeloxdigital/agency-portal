ALTER TABLE orders
    ADD COLUMN setup_fee_amount INT UNSIGNED NOT NULL DEFAULT 0 AFTER subtotal_amount,
    ADD COLUMN internal_notes TEXT NULL AFTER renews_at;
