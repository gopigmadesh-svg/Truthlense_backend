
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

$email = trim($_POST["email"] ?? "");
$password = $_POST["password"] ?? "";

if (!filter_var($email, FILTER_VALIDATE_EMAIL) ||
    strlen($password) < 8) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Enter a valid email and password with at least 8 characters"
    ]);
    exit;
}

$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

$stmt = $conn->prepare(
    "INSERT INTO users (email, password) VALUES (?, ?)"
);
$stmt->bind_param("ss", $email, $hashedPassword);

if ($stmt->execute()) {
    echo json_encode([
        "success" => true,
        "message" => "Signup successful"
    ]);
} else {
    if ($stmt->errno === 1062) {
        http_response_code(409);
        $message = "Email already registered";
    } else {
        http_response_code(500);
        $message = "Signup failed";
    }

    echo json_encode([
        "success" => false,
        "message" => $message
    ]);
}

$stmt->close();
$conn->close();
?>


