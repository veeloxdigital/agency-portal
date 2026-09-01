CREATE TABLE IF NOT EXISTS ticket_attachments (
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

INSERT IGNORE INTO email_templates (template_key,name,subject,body_html) VALUES
('ticket_created','Support ticket created','Support ticket {{ticket_number}} received','<h1>We have received your ticket</h1><p>Hello {{customer_name}},</p><p>Your support request <strong>{{ticket_number}}</strong> — {{ticket_subject}} — is now open.</p><p><a href="{{ticket_url}}">View your ticket</a></p>'),
('ticket_reply','Support ticket reply','New reply on {{ticket_number}}','<h1>New support reply</h1><p>Hello {{customer_name}},</p><p>There is a new reply on <strong>{{ticket_number}}</strong> — {{ticket_subject}}.</p><p><a href="{{ticket_url}}">Read and reply</a></p>');
