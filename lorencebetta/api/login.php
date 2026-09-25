<?php

header("Content-Type: application/json; charset=UTF-8");

function login_response($success, $message, $user = null, $status = 200)
{
    http_response_code($status);

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

try {
    require_once __DIR__ . "/db.php";

    $raw = file_get_contents("php://input");
    $data = json_decode($raw, true);

    if (!is_array($data)) {
        login_response(false, "Invalid login request.", null, 400);
    }

    $username = trim($data["username"] ?? "");
    $password = $data["password"] ?? "";

    if ($username === "" || $password === "") {
        login_response(false, "Username and password are required.", null, 400);
    }

    // Phone and address are optional profile fields. They may not exist yet
    // on older hosted databases, so they must not prevent account login.
    $stmt = $conn->prepare(
        "SELECT id, name, username, email, password, role FROM users WHERE username = ? LIMIT 1"
    );

    if (!$stmt) {
        throw new RuntimeException("Unable to prepare the login query.");
    }

    $stmt->bind_param("s", $username);

    if (!$stmt->execute()) {
        throw new RuntimeException("Unable to execute the login query.");
    }

    $stmt->store_result();

    if ($stmt->num_rows !== 1) {
        $stmt->free_result();
        $stmt->close();
        login_response(false, "Invalid username or password.", null, 401);
    }

    $stmt->bind_result(
        $userId,
        $name,
        $storedUsername,
        $email,
        $passwordHash,
        $role
    );

    if (!$stmt->fetch()) {
        throw new RuntimeException("Unable to read the login result.");
    }

    $stmt->free_result();
    $stmt->close();

    if (!password_verify($password, $passwordHash)) {
        login_response(false, "Invalid username or password.", null, 401);
    }

    login_response(true, "Login successful.", [
        "id" => (int) $userId,
        "type" => $role === "admin" ? "admin" : "customer",
        "username" => $storedUsername,
        "name" => $name,
        "email" => $email,
        "phone" => "",
        "address" => ""
    ]);
} catch (Throwable $error) {
    login_response(
        false,
        "Login service is temporarily unavailable. Please try again.",
        null,
        500
    );
}
