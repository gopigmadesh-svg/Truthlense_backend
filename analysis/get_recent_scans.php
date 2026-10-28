<?php
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . "/../config/db.php";

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "GET method required"]);
    exit;
}

$userId = isset($_GET["user_id"]) ? (int)$_GET["user_id"] : 0;
$limit = isset($_GET["limit"]) ? min(50, max(1, (int)$_GET["limit"])) : 10;

$tableCheck = $conn->query("SHOW TABLES LIKE 'scans'");
if (!$tableCheck || $tableCheck->num_rows === 0) {
    echo json_encode([
        "success" => true,
        "message" => "No scans found",
        "data" => []
    ]);
    exit;
}

if ($userId > 0) {
    $stmt = $conn->prepare("SELECT id, title, scan_type, trust_score, verdict, source_credibility, created_at FROM scans WHERE user_id = ? ORDER BY id DESC LIMIT ?");
    $stmt->bind_param("ii", $userId, $limit);
} else {
    $stmt = $conn->prepare("SELECT id, title, scan_type, trust_score, verdict, source_credibility, created_at FROM scans ORDER BY id DESC LIMIT ?");
    $stmt->bind_param("i", $limit);
}

$stmt->execute();
$result = $stmt->get_result();

$scans = [];
while ($row = $result->fetch_assoc()) {
    $scans[] = [
        "id" => (int)$row["id"],
        "title" => $row["title"],
        "scan_type" => $row["scan_type"] ?? "news",
        "trust_score" => (int)$row["trust_score"],
        "verdict" => $row["verdict"],
        "source_credibility" => $row["source_credibility"] ?? "Standard",
        "created_at" => $row["created_at"]
    ];
}

echo json_encode([
    "success" => true,
    "message" => "Recent scans retrieved",
    "data" => $scans
]);

$stmt->close();
$conn->close();
?>

