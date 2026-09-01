CREATE TABLE IF NOT EXISTS login_attempts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(190) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_login_attempts_lookup (email, ip_address, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO settings (setting_key,setting_value,is_secret) VALUES
('agency_name','Veelox Digital',0),
('agency_email','',0),
('agency_phone','',0),
('agency_address','Eastbourne, East Sussex',0),
('agency_logo','',0),
('currency','GBP',0),
('invoice_footer','Thank you for your business.',0),
('maintenance_mode','0',0);
