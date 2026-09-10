<?php

header("Content-Type: application/json; charset=UTF-8");

$host = "localhost";
$username = "root";
$password = "";
$database = "lorence_betta_fish";

$conn = new mysqli(
    $host,
    $username,
    $password,
    $database
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