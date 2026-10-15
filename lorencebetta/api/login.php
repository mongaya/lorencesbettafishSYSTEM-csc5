<?php

header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . "/db.php";
require_once __DIR__ . "/auth.php";

// Never retain an earlier authenticated session after a failed login attempt.
$_SESSION = [];

function login_result($success, $message, $user = null)
{
    $response = [
        "success" => $success,
        "message" => $message
    ];

    if ($user !== null) {
        $response["user"] = $user;
    }

    echo json_encode($response);
    exit;
}

$data = json_input();

$username = trim($data["username"] ?? "");
$password = $data["password"] ?? "";

if ($username === "" || $password === "") {
    login_result(false, "Username and password are required.");
}

$stmt = $conn->prepare(
    "SELECT id, name, username, email, password, phone, address, role, status
     FROM users
     WHERE username = ? OR email = ?
     ORDER BY CASE WHEN username = ? THEN 0 ELSE 1 END
     LIMIT 1"
);

if (!$stmt) {
    login_result(false, "Unable to prepare login.");
}

$stmt->bind_param("sss", $username, $username, $username);

if (!$stmt->execute()) {
    $stmt->close();
    login_result(false, "Unable to process login.");
}

$user = stmt_fetch_assoc_compat($stmt);
$stmt->close();

if (!$user) {
    login_result(false, "Invalid username or password.");
}

if (isset($user["status"]) && strtolower((string)$user["status"]) !== "active") {
    login_result(false, "This account is not active.");
}

if (!password_verify($password, $user["password"])) {
    login_result(false, "Invalid username or password.");
}

session_regenerate_id(true);
$_SESSION["user_id"] = (int)$user["id"];
$_SESSION["role"] = strtolower((string)$user["role"]) === "admin" ? "admin" : "customer";
session_write_close();

login_result(true, "Login successful.", [
    "id" => (int)$user["id"],
    "type" => strtolower($user["role"]) === "admin" ? "admin" : "customer",
    "username" => $user["username"],
    "name" => $user["name"],
    "email" => $user["email"] ?? "",
    "phone" => $user["phone"] ?? "",
    "address" => $user["address"] ?? ""
]);
