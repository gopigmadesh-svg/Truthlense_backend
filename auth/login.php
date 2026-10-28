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
$password = $data["password"] ?? "";

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || empty($password)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Enter a valid email address and password"
    ]);
    exit;
}

// Check if name column exists
$colCheck = $conn->query("SHOW COLUMNS FROM `users` LIKE 'name'");
$selectCols = ($colCheck && $colCheck->num_rows > 0) ? "id, name, email, password, created_at" : "id, email, password, created_at";

$stmt = $conn->prepare("SELECT $selectCols FROM users WHERE email = ? LIMIT 1");
if (!$stmt) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Database query failed"
    ]);
    exit;
}

$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    if (password_verify($password, $row["password"])) {
        http_response_code(200);
        echo json_encode([
            "success" => true,
            "message" => "Login successful",
            "data" => [
                "id" => (int)$row["id"],
                "name" => $row["name"] ?? "",
                "email" => $row["email"],
                "created_at" => $row["created_at"] ?? null
            ]
        ]);
    } else {
        http_response_code(401);
        echo json_encode([
            "success" => false,
            "message" => "Invalid email or password"
        ]);
    }
} else {
    http_response_code(401);
    echo json_encode([
        "success" => false,
        "message" => "Account not found with this email"
    ]);
}

$stmt->close();
$conn->close();
?>

