<?php
/**
 * TruthLens Misinformation Checker Backend
 * Endpoint: GET or POST /auth/profile.php
 * Description: Unified profile gateway forwarder to profile/get_profile.php and profile/update_profile.php
 */

header("Content-Type: application/json; charset=UTF-8");

$method = $_SERVER["REQUEST_METHOD"] ?? "GET";

if ($method === "GET") {
    require __DIR__ . "/../profile/get_profile.php";
} elseif ($method === "POST" || $method === "PUT") {
    require __DIR__ . "/../profile/update_profile.php";
} else {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Method not allowed. GET or POST required."]);
    exit;
}
?>
