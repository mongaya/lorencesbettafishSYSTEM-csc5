USE lorence_betta_fish;

-- Profile fields
ALTER TABLE users
    ADD COLUMN phone VARCHAR(30) NULL AFTER email,
    ADD COLUMN address TEXT NULL AFTER phone,
    ADD COLUMN reset_token_hash VARCHAR(255) NULL AFTER address,
    ADD COLUMN reset_expires_at DATETIME NULL AFTER reset_token_hash;

-- Order workflow: admin confirms shipping before final total/payment.
ALTER TABLE orders
    MODIFY COLUMN status ENUM('Pending Shipping Fee','Awaiting Payment','Pending','Confirmed','Preparing','Shipped','Delivered','Cancelled') NOT NULL DEFAULT 'Pending Shipping Fee',
    ADD COLUMN payment_status VARCHAR(40) NOT NULL DEFAULT 'Waiting for shipping fee' AFTER payment_method,
    ADD COLUMN payment_reference VARCHAR(120) NULL AFTER payment_status,
    ADD COLUMN shipping_confirmed_at DATETIME NULL AFTER payment_reference;

-- Customer/admin chat.
CREATE TABLE IF NOT EXISTS chat_messages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    username VARCHAR(50) NOT NULL,
    sender_role ENUM('customer','admin') NOT NULL,
    message TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_chat_user (user_id, created_at),
    CONSTRAINT fk_chat_user FOREIGN KEY (user_id) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;
