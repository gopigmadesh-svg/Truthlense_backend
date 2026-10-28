<?php
/**
 * TruthLens Misinformation Checker Backend
 * Endpoint: GET /profile/get_profile.php (or /auth/profile.php)
 * Description: Retrieves complete user profile including stats, picture, and preferences.
 */

header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . "/../config/db.php";

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "GET method required"]);
    exit;
}

$userId = isset($_GET["user_id"]) ? (int)$_GET["user_id"] : 0;
$email = isset($_GET["email"]) ? trim($_GET["email"]) : "";

if ($userId <= 0 && empty($email)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "user_id or email is required"]);
    exit;
}

// 1. Fetch user core row
$colAvatarCheck = $conn->query("SHOW COLUMNS FROM `users` LIKE 'avatar_url'");
$hasAvatarCol = ($colAvatarCheck && $colAvatarCheck->num_rows > 0);

$colPicCheck = $conn->query("SHOW COLUMNS FROM `users` LIKE 'profile_picture'");
$hasPicCol = ($colPicCheck && $colPicCheck->num_rows > 0);

$selectFields = "id, name, email, bio, created_at";
if ($hasAvatarCol) $selectFields .= ", avatar_url";
if ($hasPicCol) $selectFields .= ", profile_picture";

if ($userId > 0) {
    $stmt = $conn->prepare("SELECT $selectFields FROM users WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $userId);
} else {
    $stmt = $conn->prepare("SELECT $selectFields FROM users WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
}

$stmt->execute();
$result = $stmt->get_result();

if (!$result || $result->num_rows === 0) {
    $stmt->close();
    $conn->close();
    http_response_code(404);
    echo json_encode(["success" => false, "message" => "User account not found"]);
    exit;
}

$user = $result->fetch_assoc();
$resolvedUserId = (int)$user["id"];
$stmt->close();

// Resolve image URL
$avatarUrl = $user["avatar_url"] ?? ($user["profile_picture"] ?? "");
if (empty($avatarUrl)) {
    $avatarUrl = $user["profile_picture"] ?? "";
}

// 2. Fetch Checks Count from scans table
$checksCount = 0;
$tableScans = $conn->query("SHOW TABLES LIKE 'scans'");
if ($tableScans && $tableScans->num_rows > 0) {
    $scanStmt = $conn->prepare("SELECT COUNT(*) AS total_checks FROM scans WHERE user_id = ?");
    if ($scanStmt) {
        $scanStmt->bind_param("i", $resolvedUserId);
        $scanStmt->execute();
        $scanRes = $scanStmt->get_result()->fetch_assoc();
        $checksCount = (int)($scanRes["total_checks"] ?? 0);
        $scanStmt->close();
    }
}

// 3. Fetch Saved Articles Count from saved_articles table
$savedCount = 0;
$tableSaved = $conn->query("SHOW TABLES LIKE 'saved_articles'");
if ($tableSaved && $tableSaved->num_rows > 0) {
    $savedStmt = $conn->prepare("SELECT COUNT(*) AS total_saved FROM saved_articles WHERE user_id = ?");
    if ($savedStmt) {
        $savedStmt->bind_param("i", $resolvedUserId);
        $savedStmt->execute();
        $savedRes = $savedStmt->get_result()->fetch_assoc();
        $savedCount = (int)($savedRes["total_saved"] ?? 0);
        $savedStmt->close();
    }
}

// 4. Fetch Preferences from user_settings table
$interests = ["SCIENCE", "TECHNOLOGY", "HEALTH", "CURRENT AFFAIRS"];
$language = "English (US)";
$notificationsEnabled = true;

$tableSettings = $conn->query("SHOW TABLES LIKE 'user_settings'");
if ($tableSettings && $tableSettings->num_rows > 0) {
    $setStmt = $conn->prepare("SELECT theme, notifications_enabled, language, interests FROM user_settings WHERE user_id = ? LIMIT 1");
    if ($setStmt) {
        $setStmt->bind_param("i", $resolvedUserId);
        $setStmt->execute();
        $setRes = $setStmt->get_result()->fetch_assoc();
        if ($setRes) {
            $language = !empty($setRes["language"]) ? $setRes["language"] : "English (US)";
            $notificationsEnabled = (bool)($setRes["notifications_enabled"] ?? 1);
            if (!empty($setRes["interests"])) {
                $decoded = json_decode($setRes["interests"], true);
                if (is_array($decoded) && !empty($decoded)) {
                    $interests = $decoded;
                }
            }
        }
        $setStmt->close();
    }
}

$conn->close();

http_response_code(200);
echo json_encode([
    "success" => true,
    "message" => "Profile retrieved successfully",
    "data" => [
        "id" => $resolvedUserId,
        "name" => !empty($user["name"]) ? $user["name"] : "Alex Morgan",
        "email" => $user["email"],
        "bio" => !empty($user["bio"]) ? $user["bio"] : "Senior Digital Media Researcher",
        "avatar_url" => $avatarUrl,
        "profile_picture" => $avatarUrl,
        "account_type" => "Standard Account • Active",
        "checks_count" => $checksCount,
        "saved_count" => $savedCount,
        "topics_count" => count($interests),
        "interests" => $interests,
        "language" => $language,
        "notifications_enabled" => $notificationsEnabled,
        "created_at" => $user["created_at"] ?? null
    ]
], JSON_UNESCAPED_SLASHES);
?>
