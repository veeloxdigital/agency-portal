SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE roles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    slug VARCHAR(50) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    status ENUM('active','invited','suspended') NOT NULL DEFAULT 'active',
    last_login_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE customers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL UNIQUE,
    account_number VARCHAR(30) NOT NULL UNIQUE,
    type ENUM('individual','business') NOT NULL DEFAULT 'business',
    company_name VARCHAR(190) NULL,
    contact_name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL,
    phone VARCHAR(50) NULL,
    address_line_1 VARCHAR(190) NULL,
    address_line_2 VARCHAR(190) NULL,
    town_city VARCHAR(100) NULL,
    county VARCHAR(100) NULL,
    postcode VARCHAR(20) NULL,
    country_code CHAR(2) NOT NULL DEFAULT 'GB',
    status ENUM('lead','active','inactive','archived') NOT NULL DEFAULT 'active',
    internal_notes TEXT NULL,
    email_notifications TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_customers_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_customers_email (email),
    INDEX idx_customers_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE packages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    slug VARCHAR(170) NOT NULL UNIQUE,
    description TEXT NULL,
    billing_type ENUM('one_off','recurring') NOT NULL DEFAULT 'one_off',
    billing_interval ENUM('none','monthly','yearly') NOT NULL DEFAULT 'none',
    price_amount INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Minor currency units, e.g. pence',
    setup_fee_amount INT UNSIGNED NOT NULL DEFAULT 0,
    currency CHAR(3) NOT NULL DEFAULT 'GBP',
    is_public TINYINT(1) NOT NULL DEFAULT 1,
    status ENUM('active','inactive','archived') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE package_features (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    package_id BIGINT UNSIGNED NOT NULL,
    feature VARCHAR(255) NOT NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_package_features_package FOREIGN KEY (package_id) REFERENCES packages(id) ON DELETE CASCADE,
    INDEX idx_package_features_order (package_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE orders (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_number VARCHAR(30) NOT NULL UNIQUE,
    customer_id BIGINT UNSIGNED NOT NULL,
    package_id BIGINT UNSIGNED NULL,
    assigned_to BIGINT UNSIGNED NULL,
    status ENUM('draft','pending','awaiting_payment','paid','active','completed','cancelled','refunded') NOT NULL DEFAULT 'draft',
    billing_type ENUM('one_off','recurring') NOT NULL DEFAULT 'one_off',
    billing_interval ENUM('none','monthly','yearly') NOT NULL DEFAULT 'none',
    description VARCHAR(255) NOT NULL,
    subtotal_amount INT UNSIGNED NOT NULL DEFAULT 0,
    setup_fee_amount INT UNSIGNED NOT NULL DEFAULT 0,
    discount_amount INT UNSIGNED NOT NULL DEFAULT 0,
    tax_amount INT UNSIGNED NOT NULL DEFAULT 0,
    total_amount INT UNSIGNED NOT NULL DEFAULT 0,
    currency CHAR(3) NOT NULL DEFAULT 'GBP',
    starts_at DATE NULL,
    renews_at DATE NULL,
    internal_notes TEXT NULL,
    completed_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_orders_customer FOREIGN KEY (customer_id) REFERENCES customers(id),
    CONSTRAINT fk_orders_package FOREIGN KEY (package_id) REFERENCES packages(id) ON DELETE SET NULL,
    CONSTRAINT fk_orders_assignee FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_orders_status (status),
    INDEX idx_orders_renews (renews_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE invoices (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_number VARCHAR(40) NOT NULL UNIQUE,
    customer_id BIGINT UNSIGNED NOT NULL,
    order_id BIGINT UNSIGNED NULL,
    status ENUM('draft','sent','partially_paid','paid','overdue','void','refunded') NOT NULL DEFAULT 'draft',
    issue_date DATE NOT NULL,
    due_date DATE NOT NULL,
    subtotal_amount INT UNSIGNED NOT NULL DEFAULT 0,
    discount_amount INT UNSIGNED NOT NULL DEFAULT 0,
    tax_amount INT UNSIGNED NOT NULL DEFAULT 0,
    total_amount INT UNSIGNED NOT NULL DEFAULT 0,
    amount_paid INT UNSIGNED NOT NULL DEFAULT 0,
    balance_due INT UNSIGNED NOT NULL DEFAULT 0,
    currency CHAR(3) NOT NULL DEFAULT 'GBP',
    notes TEXT NULL,
    paid_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_invoices_customer FOREIGN KEY (customer_id) REFERENCES customers(id),
    CONSTRAINT fk_invoices_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL,
    INDEX idx_invoices_status_due (status, due_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE invoice_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_id BIGINT UNSIGNED NOT NULL,
    description VARCHAR(255) NOT NULL,
    quantity DECIMAL(10,2) NOT NULL DEFAULT 1.00,
    unit_price_amount INT UNSIGNED NOT NULL,
    line_total_amount INT UNSIGNED NOT NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    CONSTRAINT fk_invoice_items_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_id BIGINT UNSIGNED NOT NULL,
    customer_id BIGINT UNSIGNED NOT NULL,
    payment_reference VARCHAR(190) NULL,
    provider ENUM('stripe','bank_transfer','manual') NOT NULL,
    provider_payment_id VARCHAR(190) NULL UNIQUE,
    status ENUM('pending','succeeded','failed','refunded','partially_refunded') NOT NULL DEFAULT 'pending',
    amount INT UNSIGNED NOT NULL,
    refunded_amount INT UNSIGNED NOT NULL DEFAULT 0,
    currency CHAR(3) NOT NULL DEFAULT 'GBP',
    paid_at DATETIME NULL,
    metadata JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_payments_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id),
    CONSTRAINT fk_payments_customer FOREIGN KEY (customer_id) REFERENCES customers(id),
    INDEX idx_payments_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ticket_departments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(190) NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tickets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_number VARCHAR(30) NOT NULL UNIQUE,
    customer_id BIGINT UNSIGNED NOT NULL,
    department_id BIGINT UNSIGNED NOT NULL,
    assigned_to BIGINT UNSIGNED NULL,
    subject VARCHAR(190) NOT NULL,
    priority ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
    status ENUM('open','customer_reply','staff_reply','resolved','closed') NOT NULL DEFAULT 'open',
    last_reply_at DATETIME NULL,
    closed_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_tickets_customer FOREIGN KEY (customer_id) REFERENCES customers(id),
    CONSTRAINT fk_tickets_department FOREIGN KEY (department_id) REFERENCES ticket_departments(id),
    CONSTRAINT fk_tickets_assignee FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_tickets_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ticket_replies (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    message MEDIUMTEXT NOT NULL,
    is_internal_note TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_ticket_replies_ticket FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    CONSTRAINT fk_ticket_replies_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ticket_attachments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_id BIGINT UNSIGNED NOT NULL,
    reply_id BIGINT UNSIGNED NULL,
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(255) NOT NULL UNIQUE,
    mime_type VARCHAR(120) NOT NULL,
    file_size INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_ticket_attachments_ticket FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    CONSTRAINT fk_ticket_attachments_reply FOREIGN KEY (reply_id) REFERENCES ticket_replies(id) ON DELETE CASCADE,
    INDEX idx_ticket_attachments_ticket (ticket_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE settings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(120) NOT NULL UNIQUE,
    setting_value TEXT NULL,
    is_secret TINYINT(1) NOT NULL DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE email_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id BIGINT UNSIGNED NULL,
    recipient_email VARCHAR(190) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    template_key VARCHAR(120) NULL,
    status ENUM('queued','sent','failed') NOT NULL DEFAULT 'queued',
    error_message TEXT NULL,
    sent_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_email_logs_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE email_templates (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    template_key VARCHAR(120) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    body_html MEDIUMTEXT NOT NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE activity_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    action VARCHAR(120) NOT NULL,
    entity_type VARCHAR(100) NULL,
    entity_id BIGINT UNSIGNED NULL,
    description VARCHAR(255) NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_activity_logs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_activity_entity (entity_type, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE login_attempts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(190) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_login_attempts_lookup (email, ip_address, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE stripe_webhook_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    stripe_event_id VARCHAR(190) NOT NULL UNIQUE,
    event_type VARCHAR(120) NOT NULL,
    processed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE migrations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    migration VARCHAR(190) NOT NULL UNIQUE,
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO roles (name, slug) VALUES ('Administrator', 'admin'), ('Staff', 'staff'), ('Customer', 'customer');
INSERT INTO ticket_departments (name, email) VALUES ('General Support', NULL), ('Billing', NULL), ('Web Projects', NULL);
INSERT INTO migrations (migration) VALUES ('003_packages.sql'), ('004_orders.sql'), ('005_invoices.sql'), ('006_stripe.sql'), ('007_email.sql'), ('008_support.sql'), ('010_production.sql');

INSERT INTO settings (setting_key, setting_value, is_secret) VALUES
('invoice_prefix', 'INV', 0), ('invoice_due_days', '14', 0),
('agency_name','Veelox Digital',0), ('agency_email','',0), ('agency_phone','',0),
('agency_address','Eastbourne, East Sussex',0), ('agency_logo','',0), ('currency','GBP',0),
('invoice_footer','Thank you for your business.',0), ('maintenance_mode','0',0);

INSERT INTO email_templates (template_key,name,subject,body_html) VALUES
('portal_welcome','Customer portal welcome','Your Veelox Digital portal account','<h1>Welcome, {{customer_name}}</h1><p>Your customer portal is ready.</p><p>Email: {{customer_email}}<br>Temporary password: {{temporary_password}}</p><p><a href="{{portal_url}}">Sign in</a></p>'),
('order_created','Order confirmation','Order {{order_number}} has been created','<h1>Your order is confirmed</h1><p>Hello {{customer_name}}, order {{order_number}} for {{order_description}} has been created.</p><p>Total: {{order_total}} · Status: {{order_status}}</p>'),
('order_status','Order status update','Order {{order_number}} is now {{order_status}}','<h1>Order update</h1><p>Your order {{order_number}} is now {{order_status}}.</p>'),
('invoice_sent','Invoice issued','Invoice {{invoice_number}} from Veelox Digital','<h1>Invoice {{invoice_number}}</h1><p>Total: {{invoice_total}} · Due: {{invoice_due_date}}</p><p><a href="{{invoice_url}}">View and pay invoice</a></p>'),
('payment_received','Payment confirmation','Payment received for {{invoice_number}}','<h1>Thank you</h1><p>We received {{payment_amount}} for {{invoice_number}}. Remaining balance: {{invoice_balance}}.</p>');

INSERT INTO email_templates (template_key,name,subject,body_html) VALUES
('ticket_created','Support ticket created','Support ticket {{ticket_number}} received','<h1>We have received your ticket</h1><p>Your request {{ticket_number}} — {{ticket_subject}} — is now open.</p><p><a href="{{ticket_url}}">View ticket</a></p>'),
('ticket_reply','Support ticket reply','New reply on {{ticket_number}}','<h1>New support reply</h1><p>There is a new reply on {{ticket_number}} — {{ticket_subject}}.</p><p><a href="{{ticket_url}}">Read and reply</a></p>');

SET FOREIGN_KEY_CHECKS = 1;
