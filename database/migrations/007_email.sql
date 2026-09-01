CREATE TABLE IF NOT EXISTS email_templates (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    template_key VARCHAR(120) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    body_html MEDIUMTEXT NOT NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE customers ADD COLUMN email_notifications TINYINT(1) NOT NULL DEFAULT 1 AFTER internal_notes;

INSERT IGNORE INTO email_templates (template_key,name,subject,body_html) VALUES
('portal_welcome','Customer portal welcome','Your Veelox Digital portal account','<h1>Welcome, {{customer_name}}</h1><p>Your Veelox Digital customer portal is ready.</p><p><strong>Email:</strong> {{customer_email}}<br><strong>Temporary password:</strong> {{temporary_password}}</p><p><a href="{{portal_url}}">Sign in to your portal</a></p><p>Please change this temporary password after signing in.</p>'),
('order_created','Order confirmation','Order {{order_number}} has been created','<h1>Your order is confirmed</h1><p>Hello {{customer_name}},</p><p>We have created order <strong>{{order_number}}</strong> for {{order_description}}.</p><p><strong>Total:</strong> {{order_total}}<br><strong>Status:</strong> {{order_status}}</p><p><a href="{{portal_url}}">Open your customer portal</a></p>'),
('order_status','Order status update','Order {{order_number}} is now {{order_status}}','<h1>Order update</h1><p>Hello {{customer_name}},</p><p>Your order <strong>{{order_number}}</strong> is now <strong>{{order_status}}</strong>.</p><p><a href="{{portal_url}}">View your account</a></p>'),
('invoice_sent','Invoice issued','Invoice {{invoice_number}} from Veelox Digital','<h1>Invoice {{invoice_number}}</h1><p>Hello {{customer_name}},</p><p>A new invoice has been added to your account.</p><p><strong>Total:</strong> {{invoice_total}}<br><strong>Due:</strong> {{invoice_due_date}}</p><p><a href="{{invoice_url}}">View and pay your invoice</a></p>'),
('payment_received','Payment confirmation','Payment received for {{invoice_number}}','<h1>Thank you for your payment</h1><p>Hello {{customer_name}},</p><p>We received your payment of <strong>{{payment_amount}}</strong> for invoice <strong>{{invoice_number}}</strong>.</p><p><strong>Remaining balance:</strong> {{invoice_balance}}</p><p><a href="{{invoice_url}}">View your invoice</a></p>');
