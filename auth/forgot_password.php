<?php
header("Content-Type: application/json");

require_once __DIR__ . "/../config/db.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode([
        "success" => false,
        "message" => "POST method required"
    ]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Invalid JSON data"
    ]);
    exit;
}

$email = trim($data["email"] ?? "");
$newPassword = $data["new_password"] ?? "";
$confirmPassword = $data["confirm_password"] ?? "";

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Enter a valid email address"
    ]);
    exit;
}

if (!is_string($newPassword) ||
    !is_string($confirmPassword) ||
    strlen($newPassword) < 8) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Password must contain at least 8 characters"
    ]);
    exit;
}

if ($newPassword !== $confirmPassword) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "New password and confirm password do not match"
    ]);
    exit;
}

$hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

$stmt = $conn->prepare(
    "UPDATE users SET password = ? WHERE email = ?"
);

if (!$stmt) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Database operation failed"
    ]);
    exit;
}

$stmt->bind_param("ss", $hashedPassword, $email);

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Password reset failed"
    ]);
} elseif ($stmt->affected_rows > 0) {
    echo json_encode([
        "success" => true,
        "message" => "Password updated successfully"
    ]);
} else {
    http_response_code(404);
    echo json_encode([
        "success" => false,
        "message" => "Email not found or password is unchanged"
    ]);
}

$stmt->close();
$conn->close();
?>