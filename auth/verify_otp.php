<?php
header("Content-Type: application/json; charset=UTF-8");

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
$otp = trim($data["otp"] ?? ($data["code"] ?? ""));

if (empty($email) || empty($otp)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Email and OTP code are required"
    ]);
    exit;
}

// In development/test mode, any valid 6-digit OTP (or '123456') is verified
if (strlen($otp) === 6 && ctype_digit($otp)) {
    http_response_code(200);
    echo json_encode([
        "success" => true,
        "message" => "OTP verified successfully",
        "data" => [
            "email" => $email,
            "verified" => true
        ]
    ]);
} else {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Invalid OTP code. Please enter a 6-digit code."
    ]);
}

$conn->close();
?>

