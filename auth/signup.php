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

// Read JSON raw body
$data = json_decode(file_get_contents("php://input"), true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Invalid JSON data"
    ]);
    exit;
}

$name = trim($data["name"] ?? ($data["fullName"] ?? ""));
$email = trim($data["email"] ?? "");
$password = $data["password"] ?? "";

if (!filter_var($email, FILTER_VALIDATE_EMAIL) ||
    !is_string($password) ||
    strlen($password) < 6) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Enter a valid email and password with at least 6 characters"
    ]);
    exit;
}

// Hash password before saving
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

// Check if 'name' column exists in users table
$colCheck = $conn->query("SHOW COLUMNS FROM `users` LIKE 'name'");
$hasNameCol = ($colCheck && $colCheck->num_rows > 0);

if ($hasNameCol) {
    $stmt = $conn->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Database query prepare failed: " . $conn->error]);
        exit;
    }
    $stmt->bind_param("sss", $name, $email, $hashedPassword);
} else {
    $stmt = $conn->prepare("INSERT INTO users (email, password) VALUES (?, ?)");
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Database query prepare failed: " . $conn->error]);
        exit;
    }
    $stmt->bind_param("ss", $email, $hashedPassword);
}

if ($stmt->execute()) {
    $newId = $stmt->insert_id;
    http_response_code(201);
    echo json_encode([
        "success" => true,
        "message" => "Signup successful",
        "data" => [
            "id" => $newId,
            "name" => $name,
            "email" => $email
        ]
    ]);
} else {
    if ($stmt->errno === 1062) {
        http_response_code(409);
        $message = "Email already registered";
    } else {
        http_response_code(500);
        $message = "Signup failed: " . $stmt->error;
    }

    echo json_encode([
        "success" => false,
        "message" => $message
    ]);
}

$stmt->close();
$conn->close();
?>
