<?php
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . "/../config/db.php";

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "GET method required"]);
    exit;
}

$userId = isset($_GET["user_id"]) ? (int)$_GET["user_id"] : 0;
if ($userId <= 0) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Valid user_id required"]);
    exit;
}

$stmt = $conn->prepare("SELECT theme, notifications_enabled, language, interests FROM user_settings WHERE user_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $interests = !empty($row["interests"]) ? json_decode($row["interests"], true) : ["SCIENCE", "TECHNOLOGY", "HEALTH"];
    echo json_encode([
        "success" => true,
        "message" => "Settings retrieved",
        "data" => [
            "theme" => $row["theme"] ?? "dark",
            "notifications_enabled" => (bool)$row["notifications_enabled"],
            "language" => $row["language"] ?? "English (US)",
            "interests" => is_array($interests) ? $interests : ["SCIENCE", "TECHNOLOGY", "HEALTH"]
        ]
    ]);
} else {
    // Return default settings
    echo json_encode([
        "success" => true,
        "message" => "Default settings returned",
        "data" => [
            "theme" => "dark",
            "notifications_enabled" => true,
            "language" => "English (US)",
            "interests" => ["SCIENCE", "TECHNOLOGY", "HEALTH", "CURRENT AFFAIRS"]
        ]
    ]);
}

$stmt->close();
$conn->close();
?>

