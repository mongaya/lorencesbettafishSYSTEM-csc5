LORENCE'S BETTA FISH - IMPROVED MYSQL/XAMPP

1. Keep Apache and MySQL running.
2. Existing database: lorence_betta_fish.
3. If you already imported the original database.sql, import upgrade.sql ONCE in phpMyAdmin.
4. Copy this project into C:\xampp\htdocs\lorencebetta.
5. The transparent logo is images\logo.png.
6. Product categories are no longer shown in the customer/admin UI.
7. Orders now wait for admin shipping-fee confirmation before the final total is shown. Bank Transfer is removed; payment methods are GCash and Cash on Delivery.
8. Forgot Password uses Gmail SMTP. Configure api\gmail_config.php with a Gmail App Password; never use your normal Gmail password.
9. Chat uses the chat_messages table and api/chat.php.
10. Profile now includes name, email, phone and address.


FIX INCLUDED: api/db.php now automatically applies the required V2 database migration (phone/address/payment/chat fields) on the first request. This fixes the invalid JSON/API response that occurred when only database.sql was imported.
