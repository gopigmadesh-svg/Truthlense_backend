<?php
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . "/../config/db.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "POST method required"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Invalid JSON data"]);
    exit;
}

$userId = (int)($data["user_id"] ?? 0);
if ($userId <= 0) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Valid user_id required"]);
    exit;
}

$theme = trim($data["theme"] ?? "dark");
$notifications = isset($data["notifications_enabled"]) ? (int)(bool)$data["notifications_enabled"] : 1;
$language = trim($data["language"] ?? "English (US)");
$interestsJson = isset($data["interests"]) ? json_encode($data["interests"]) : json_encode(["SCIENCE", "TECHNOLOGY"]);

$stmt = $conn->prepare("
    INSERT INTO user_settings (user_id, theme, notifications_enabled, language, interests)
    VALUES (?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        theme = VALUES(theme),
        notifications_enabled = VALUES(notifications_enabled),
        language = VALUES(language),
        interests = VALUES(interests)
");

if (!$stmt) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Prepare statement failed: " . $conn->error]);
    exit;
}

$stmt->bind_param("isiss", $userId, $theme, $notifications, $language, $interestsJson);

if ($stmt->execute()) {
    echo json_encode([
        "success" => true,
        "message" => "Settings updated successfully",
        "data" => [
            "theme" => $theme,
            "notifications_enabled" => (bool)$notifications,
            "language" => $language,
            "interests" => json_decode($interestsJson, true)
        ]
    ]);
} else {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Update failed: " . $stmt->error]);
}

$stmt->close();
$conn->close();
?>

