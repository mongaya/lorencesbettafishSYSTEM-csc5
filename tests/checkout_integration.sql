USE lorence_betta_fish;
ALTER TABLE orders ADD COLUMN payment_screenshot VARCHAR(255) NULL, ADD COLUMN courier VARCHAR(30) NULL, ADD COLUMN tracking_url VARCHAR(500) NULL;
CREATE TABLE shop_settings (setting_key VARCHAR(120) PRIMARY KEY, setting_value TEXT NOT NULL) ENGINE=InnoDB;
INSERT INTO shop_settings VALUES ('merchant_name','Test Merchant'),('gcash_number','09123456789'),('qr_image','images/test-qr.png');
INSERT INTO users (name,username,email,password,role,status) VALUES
('Test Customer','testbuyer','buyer@test.invalid','$2y$12$PXZ.Z.XfgkNohbNExtAXqOYySmmwKk9v09LC8ebRtzdYTOV2m806e','customer','active');
UPDATE users SET password='$2y$12$PXZ.Z.XfgkNohbNExtAXqOYySmmwKk9v09LC8ebRtzdYTOV2m806e' WHERE username='admin';
