<?php

header("Content-Type: application/json; charset=UTF-8");

/*
 * InfinityFree MySQL connection. Environment variables can override these
 * values on another server. Keep the password out of this public repository:
 * set DB_PASSWORD when supported, or enter it only in the hosted copy.
 */
$host = getenv("DB_HOST") ?: "sql200.infinityfree.com";
$username = getenv("DB_USER") ?: "if0_42882922";
$password = getenv("DB_PASSWORD") ?: "";
$database = getenv("DB_NAME") ?: "if0_42882922_lorencebetta";
$port = (int) (getenv("DB_PORT") ?: 3306);

/*
 * PHP 8.1 may throw before connect_error can be checked. Disable automatic
 * MySQLi exceptions so every API failure remains a valid JSON response.
 */
if (function_exists("mysqli_report")) {
    mysqli_report(MYSQLI_REPORT_OFF);
}

$conn = new mysqli(
    $host,
    $username,
    $password,
    $database,
    $port
);

if ($conn->connect_error) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Database connection failed: " .
                     $conn->connect_error
    ]);

    exit;
}

$conn->set_charset("utf8mb4");


/* =========================================================
   AUTOMATIC DATABASE MIGRATION
   Keeps installations created from database.sql compatible
   with the current PHP endpoints.
   ========================================================= */

function db_column_exists($conn, $table, $column)
{
    $stmt = $conn->prepare(
        "SELECT 1
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = ?
           AND COLUMN_NAME = ?
         LIMIT 1"
    );

    if (!$stmt) {
        throw new Exception("Could not inspect the database schema.");
    }

    $stmt->bind_param("ss", $table, $column);
    $stmt->execute();
    $stmt->store_result();
    $exists = $stmt->num_rows > 0;
    $stmt->close();

    return $exists;
}

function db_add_column_if_missing($conn, $table, $column, $definition)
{
    if (!db_column_exists($conn, $table, $column)) {
        if (!$conn->query(
            "ALTER TABLE `" . $table . "` ADD COLUMN `" .
            $column . "` " . $definition
        )) {
            throw new Exception(
                "Could not add " . $table . "." . $column . "."
            );
        }
    }
}

try {
    db_add_column_if_missing(
        $conn,
        "users",
        "phone",
        "VARCHAR(30) NULL AFTER `email`"
    );
    db_add_column_if_missing(
        $conn,
        "users",
        "address",
        "TEXT NULL AFTER `phone`"
    );
    db_add_column_if_missing(
        $conn,
        "users",
        "reset_token_hash",
        "VARCHAR(255) NULL AFTER `address`"
    );
    db_add_column_if_missing(
        $conn,
        "users",
        "reset_expires_at",
        "DATETIME NULL AFTER `reset_token_hash`"
    );

    db_add_column_if_missing(
        $conn,
        "orders",
        "payment_status",
        "VARCHAR(40) NOT NULL DEFAULT 'Waiting for shipping fee' AFTER `payment_method`"
    );
    db_add_column_if_missing(
        $conn,
        "orders",
        "payment_reference",
        "VARCHAR(120) NULL AFTER `payment_status`"
    );
    db_add_column_if_missing(
        $conn,
        "orders",
        "shipping_confirmed_at",
        "DATETIME NULL AFTER `payment_reference`"
    );

    if (!$conn->query(
        "ALTER TABLE `orders`
         MODIFY COLUMN `status`
         ENUM(
             'Pending Shipping Fee',
             'Awaiting Payment',
             'Pending',
             'Confirmed',
             'Preparing',
             'Shipped',
             'Delivered',
             'Cancelled'
         ) NOT NULL DEFAULT 'Pending Shipping Fee'"
    )) {
        throw new Exception("Could not update the order status options.");
    }

    if (!$conn->query(
        "CREATE TABLE IF NOT EXISTS `chat_messages` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT UNSIGNED NOT NULL,
            `username` VARCHAR(50) NOT NULL,
            `sender_role` ENUM('customer','admin') NOT NULL,
            `message` TEXT NOT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_chat_user` (`user_id`, `created_at`),
            CONSTRAINT `fk_chat_user`
                FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
                ON UPDATE CASCADE ON DELETE CASCADE
        ) ENGINE=InnoDB"
    )) {
        throw new Exception("Could not create the chat_messages table.");
    }
} catch (Throwable $error) {
    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" =>
            "Database setup failed. Import upgrade.sql in phpMyAdmin, " .
            "then try again."
    ]);

    exit;
}


/* =========================================================
   JSON INPUT
   ========================================================= */

function json_input()
{
    $raw = file_get_contents("php://input");

    $data = json_decode(
        $raw,
        true
    );

    return is_array($data)
        ? $data
        : [];
}


/* =========================================================
   JSON RESPONSE
   ========================================================= */

function respond(
    $success,
    $message = "",
    $extra = [],
    $status = 200
) {

    http_response_code($status);

    echo json_encode(
        array_merge(
            [
                "success" => $success,
                "message" => $message
            ],
            $extra
        )
    );

    exit;
}


/* =========================================================
   FETCH ONE ROW
   Compatible with PHP versions without mysqlnd
   ========================================================= */

function stmt_fetch_assoc_compat($stmt)
{
    if (!$stmt) {
        return null;
    }

    if (!$stmt->store_result()) {
        return null;
    }

    $meta = $stmt->result_metadata();

    if (!$meta) {
        $stmt->free_result();
        return null;
    }

    $fields = [];

    while ($field = $meta->fetch_field()) {
        $fields[] = $field->name;
    }

    $meta->free();

    $values = [];

    $refs = [];

    foreach ($fields as $field) {
        $values[$field] = null;
        $refs[] = &$values[$field];
    }

    call_user_func_array(
        [$stmt, "bind_result"],
        $refs
    );

    $row = null;

    if ($stmt->fetch()) {

        $row = [];

        foreach ($fields as $field) {
            $row[$field] = $values[$field];
        }
    }

    /*
     * IMPORTANT:
     * Free the result before another query is prepared.
     * This prevents:
     * "Commands out of sync"
     */

    $stmt->free_result();

    return $row;
}


/* =========================================================
   FETCH ALL ROWS
   ========================================================= */

function stmt_fetch_all_assoc_compat($stmt)
{
    if (!$stmt) {
        return [];
    }

    if (!$stmt->store_result()) {
        return [];
    }

    $meta = $stmt->result_metadata();

    if (!$meta) {
        $stmt->free_result();
        return [];
    }

    $fields = [];

    while ($field = $meta->fetch_field()) {
        $fields[] = $field->name;
    }

    $meta->free();

    $values = [];

    $refs = [];

    foreach ($fields as $field) {
        $values[$field] = null;
        $refs[] = &$values[$field];
    }

    call_user_func_array(
        [$stmt, "bind_result"],
        $refs
    );

    $rows = [];

    while ($stmt->fetch()) {

        $row = [];

        foreach ($fields as $field) {
            $row[$field] = $values[$field];
        }

        $rows[] = $row;
    }

    /*
     * IMPORTANT:
     * Free the result before another query.
     */

    $stmt->free_result();

    return $rows;
}


/* =========================================================
   CHECK IF STATEMENT HAS ROWS
   ========================================================= */

function stmt_has_rows_compat($stmt)
{
    if (!$stmt) {
        return false;
    }

    if (!$stmt->store_result()) {
        return false;
    }

    $hasRows = ($stmt->num_rows > 0);

    $stmt->free_result();

    return $hasRows;
}

?>