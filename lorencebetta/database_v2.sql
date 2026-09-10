CREATE DATABASE IF NOT EXISTS lorence_betta_fish
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE lorence_betta_fish;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','customer') NOT NULL DEFAULT 'customer',
    status ENUM('active','archived','blocked') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS products (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_code VARCHAR(30) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    category VARCHAR(50) NOT NULL,
    price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    stock INT NOT NULL DEFAULT 0,
    description TEXT NULL,
    image VARCHAR(255) NULL,
    status ENUM('available','out_of_stock','deleted') NOT NULL DEFAULT 'available',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_code VARCHAR(40) NOT NULL UNIQUE,
    user_id INT UNSIGNED NOT NULL,
    customer_name VARCHAR(120) NOT NULL,
    phone VARCHAR(30) NOT NULL,
    address TEXT NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    shipping DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    payment_method VARCHAR(50) NOT NULL,
    status ENUM('Pending','Confirmed','Preparing','Shipped','Delivered','Cancelled')
        NOT NULL DEFAULT 'Pending',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_orders_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS order_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    product_name VARCHAR(150) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    quantity INT NOT NULL,
    CONSTRAINT fk_order_items_order
        FOREIGN KEY (order_id) REFERENCES orders(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_order_items_product
        FOREIGN KEY (product_id) REFERENCES products(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Default admin account:
-- username: admin
-- password: admin123
-- The password hash below is generated for "admin123".
INSERT INTO users (name, username, email, password, role, status)
SELECT
    'Administrator',
    'admin',
    'admin@lorencebetta.local',
    '$2y$12$ZPHJmjDVcTXYj6R1X5IF.OM8t2q0MZnxU1n97NEgDVD31pWeVDTPe',
    'admin',
    'active'
WHERE NOT EXISTS (
    SELECT 1 FROM users WHERE username = 'admin'
);

-- Optional starter products. They intentionally have no image;
-- add real fish pictures from the Admin > Products page.
INSERT INTO products
(product_code, name, category, price, stock, description, image, status)
SELECT 'P001','Royal Blue Halfmoon','Halfmoon',450,8,
       'Beautiful royal blue Halfmoon Betta with wide flowing fins.',
       NULL,'available'
WHERE NOT EXISTS (SELECT 1 FROM products WHERE product_code='P001');

INSERT INTO products
(product_code, name, category, price, stock, description, image, status)
SELECT 'P002','Red Galaxy Betta','Galaxy',550,6,
       'Colorful red galaxy Betta with amazing metallic patterns.',
       NULL,'available'
WHERE NOT EXISTS (SELECT 1 FROM products WHERE product_code='P002');

INSERT INTO products
(product_code, name, category, price, stock, description, image, status)
SELECT 'P003','Fancy Koi Betta','Koi',650,5,
       'Unique koi-pattern Betta with vibrant orange and white colors.',
       NULL,'available'
WHERE NOT EXISTS (SELECT 1 FROM products WHERE product_code='P003');

INSERT INTO products
(product_code, name, category, price, stock, description, image, status)
SELECT 'P004','Black Samurai Plakat','Plakat',500,7,
       'Strong black Samurai Plakat with metallic scales.',
       NULL,'available'
WHERE NOT EXISTS (SELECT 1 FROM products WHERE product_code='P004');

INSERT INTO products
(product_code, name, category, price, stock, description, image, status)
SELECT 'P005','Emerald Crowntail','Crowntail',475,9,
       'Emerald green Crowntail Betta with sharp elegant fins.',
       NULL,'available'
WHERE NOT EXISTS (SELECT 1 FROM products WHERE product_code='P005');

INSERT INTO products
(product_code, name, category, price, stock, description, image, status)
SELECT 'P006','Blue Marble Plakat','Plakat',400,10,
       'Beautiful blue marble Plakat Betta.',
       NULL,'available'
WHERE NOT EXISTS (SELECT 1 FROM products WHERE product_code='P006');

INSERT INTO products
(product_code, name, category, price, stock, description, image, status)
SELECT 'P007','Purple Galaxy Halfmoon','Halfmoon',600,4,
       'Premium purple galaxy Halfmoon Betta.',
       NULL,'available'
WHERE NOT EXISTS (SELECT 1 FROM products WHERE product_code='P007');

INSERT INTO products
(product_code, name, category, price, stock, description, image, status)
SELECT 'P008','Candy Koi Betta','Koi',580,5,
       'Sweet candy-colored koi Betta with unique markings.',
       NULL,'available'
WHERE NOT EXISTS (SELECT 1 FROM products WHERE product_code='P008');


-- V2 extensions
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
