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
    echo json_encode(["success" => false, "message" => "Valid user_id is required"]);
    exit;
}

$tableCheck = $conn->query("SHOW TABLES LIKE 'saved_articles'");
if (!$tableCheck || $tableCheck->num_rows === 0) {
    echo json_encode(["success" => true, "message" => "No saved articles found", "data" => []]);
    exit;
}

$stmt = $conn->prepare("SELECT id, title, summary, source, url, trust_score, saved_at FROM saved_articles WHERE user_id = ? ORDER BY id DESC");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

$articles = [];
while ($row = $result->fetch_assoc()) {
    $articles[] = [
        "id" => (int)$row["id"],
        "title" => $row["title"],
        "summary" => $row["summary"] ?? "",
        "source" => $row["source"] ?? "",
        "url" => $row["url"] ?? "",
        "trust_score" => (int)$row["trust_score"],
        "saved_at" => $row["saved_at"]
    ];
}

echo json_encode([
    "success" => true,
    "message" => "Saved articles retrieved successfully",
    "data" => $articles
]);

$stmt->close();
$conn->close();
?>

