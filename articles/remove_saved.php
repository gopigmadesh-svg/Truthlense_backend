<?php
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . "/../config/db.php";

$method = $_SERVER["REQUEST_METHOD"];
if ($method !== "POST" && $method !== "DELETE") {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "POST or DELETE method required"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true) ?? [];
$articleId = (int)($data["article_id"] ?? ($_GET["article_id"] ?? 0));
$userId = (int)($data["user_id"] ?? ($_GET["user_id"] ?? 0));

if ($articleId <= 0 || $userId <= 0) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "article_id and user_id are required"]);
    exit;
}

$stmt = $conn->prepare("DELETE FROM saved_articles WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $articleId, $userId);

if ($stmt->execute()) {
    echo json_encode(["success" => true, "message" => "Article removed from saved"]);
} else {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Delete failed"]);
}

$stmt->close();
$conn->close();
?>

