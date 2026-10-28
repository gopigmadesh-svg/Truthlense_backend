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
$title = trim($data["title"] ?? "");
$summary = trim($data["summary"] ?? "");
$source = trim($data["source"] ?? "");
$url = trim($data["url"] ?? "");
$trustScore = isset($data["trust_score"]) ? (int)$data["trust_score"] : 75;

if ($userId <= 0 || empty($title)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "user_id and title are required"]);
    exit;
}

$stmt = $conn->prepare("INSERT INTO saved_articles (user_id, title, summary, source, url, trust_score) VALUES (?, ?, ?, ?, ?, ?)");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Prepare statement failed: " . $conn->error]);
    exit;
}

$stmt->bind_param("issssi", $userId, $title, $summary, $source, $url, $trustScore);

if ($stmt->execute()) {
    http_response_code(201);
    echo json_encode([
        "success" => true,
        "message" => "Article saved successfully",
        "data" => [
            "id" => $stmt->insert_id,
            "title" => $title,
            "trust_score" => $trustScore
        ]
    ]);
} else {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Save failed: " . $stmt->error]);
}

$stmt->close();
$conn->close();
?>

