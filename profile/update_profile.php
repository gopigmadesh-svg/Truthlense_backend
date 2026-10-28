<?php
/**
 * TruthLens Misinformation Checker Backend
 * Endpoint: POST /profile/update_profile.php
 * Description: Updates user profile information (name, bio, avatar, language, interests, notifications)
 *              and returns the complete updated profile data.
 */

header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . "/../config/db.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "POST method required"]);
    exit;
}

// 1. Parse JSON or Form POST payload
$rawInput = file_get_contents("php://input");
$jsonData = json_decode($rawInput, true);

if (is_array($jsonData)) {
    $data = $jsonData;
} else {
    $data = $_POST;
}

$userId = isset($data["user_id"]) ? (int)$data["user_id"] : 0;
$email = isset($data["email"]) ? trim($data["email"]) : "";

if ($userId <= 0 && empty($email)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "user_id or email is required"]);
    exit;
}

// 2. Identify and verify user
$colAvatarCheck = $conn->query("SHOW COLUMNS FROM `users` LIKE 'avatar_url'");
$hasAvatarCol = ($colAvatarCheck && $colAvatarCheck->num_rows > 0);

$colPicCheck = $conn->query("SHOW COLUMNS FROM `users` LIKE 'profile_picture'");
$hasPicCol = ($colPicCheck && $colPicCheck->num_rows > 0);

$colBioCheck = $conn->query("SHOW COLUMNS FROM `users` LIKE 'bio'");
$hasBioCol = ($colBioCheck && $colBioCheck->num_rows > 0);

$colNameCheck = $conn->query("SHOW COLUMNS FROM `users` LIKE 'name'");
$hasNameCol = ($colNameCheck && $colNameCheck->num_rows > 0);

$findQuery = ($userId > 0)
    ? "SELECT id, email FROM users WHERE id = ? LIMIT 1"
    : "SELECT id, email FROM users WHERE email = ? LIMIT 1";

$findStmt = $conn->prepare($findQuery);
if ($userId > 0) {
    $findStmt->bind_param("i", $userId);
} else {
    $findStmt->bind_param("s", $email);
}
$findStmt->execute();
$findRes = $findStmt->get_result();

if (!$findRes || $findRes->num_rows === 0) {
    $findStmt->close();
    $conn->close();
    http_response_code(404);
    echo json_encode(["success" => false, "message" => "User account not found"]);
    exit;
}

$userRow = $findRes->fetch_assoc();
$resolvedUserId = (int)$userRow["id"];
$findStmt->close();

// 3. Update `users` table if name, bio, or avatar provided
$userUpdateFields = [];
$userParams = [];
$userTypes = "";

if (isset($data["name"]) && $hasNameCol) {
    $userUpdateFields[] = "`name` = ?";
    $userParams[] = trim((string)$data["name"]);
    $userTypes .= "s";
}

if (isset($data["bio"]) && $hasBioCol) {
    $userUpdateFields[] = "`bio` = ?";
    $userParams[] = trim((string)$data["bio"]);
    $userTypes .= "s";
}

$avatarVal = $data["avatar_url"] ?? ($data["profile_picture"] ?? null);
if ($avatarVal !== null) {
    $avatarClean = trim((string)$avatarVal);
    if ($hasAvatarCol) {
        $userUpdateFields[] = "`avatar_url` = ?";
        $userParams[] = $avatarClean;
        $userTypes .= "s";
    }
    if ($hasPicCol) {
        $userUpdateFields[] = "`profile_picture` = ?";
        $userParams[] = $avatarClean;
        $userTypes .= "s";
    }
}

if (!empty($userUpdateFields)) {
    $userParams[] = $resolvedUserId;
    $userTypes .= "i";
    $sql = "UPDATE `users` SET " . implode(", ", $userUpdateFields) . " WHERE `id` = ?";
    $uStmt = $conn->prepare($sql);
    if ($uStmt) {
        $uStmt->bind_param($userTypes, ...$userParams);
        $uStmt->execute();
        $uStmt->close();
    }
}

// 4. Update `user_settings` table if language, interests, or notifications provided
$hasSettingsUpdate = (isset($data["language"]) || isset($data["interests"]) || isset($data["notifications_enabled"]) || isset($data["theme"]));

if ($hasSettingsUpdate) {
    // Ensure table exists
    $tableSettings = $conn->query("SHOW TABLES LIKE 'user_settings'");
    if ($tableSettings && $tableSettings->num_rows > 0) {
        // Fetch current settings
        $currStmt = $conn->prepare("SELECT theme, notifications_enabled, language, interests FROM user_settings WHERE user_id = ? LIMIT 1");
        $currTheme = "dark";
        $currNotif = 1;
        $currLang = "English (US)";
        $currInterests = json_encode(["SCIENCE", "TECHNOLOGY", "HEALTH", "CURRENT AFFAIRS"]);

        if ($currStmt) {
            $currStmt->bind_param("i", $resolvedUserId);
            $currStmt->execute();
            $currRes = $currStmt->get_result()->fetch_assoc();
            if ($currRes) {
                $currTheme = $currRes["theme"] ?? "dark";
                $currNotif = isset($currRes["notifications_enabled"]) ? (int)$currRes["notifications_enabled"] : 1;
                $currLang = $currRes["language"] ?? "English (US)";
                $currInterests = $currRes["interests"] ?? $currInterests;
            }
            $currStmt->close();
        }

        if (isset($data["theme"])) {
            $currTheme = trim((string)$data["theme"]);
        }
        if (isset($data["notifications_enabled"])) {
            $currNotif = (int)(bool)$data["notifications_enabled"];
        }
        if (isset($data["language"])) {
            $currLang = trim((string)$data["language"]);
        }
        if (isset($data["interests"])) {
            if (is_array($data["interests"])) {
                $currInterests = json_encode(array_values($data["interests"]));
            } else if (is_string($data["interests"])) {
                // If already JSON string or comma-separated
                $decoded = json_decode($data["interests"], true);
                if (is_array($decoded)) {
                    $currInterests = json_encode(array_values($decoded));
                } else {
                    $parts = array_map("trim", explode(",", $data["interests"]));
                    $currInterests = json_encode(array_filter($parts));
                }
            }
        }

        $insStmt = $conn->prepare("
            INSERT INTO user_settings (user_id, theme, notifications_enabled, language, interests)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                theme = VALUES(theme),
                notifications_enabled = VALUES(notifications_enabled),
                language = VALUES(language),
                interests = VALUES(interests)
        ");
        if ($insStmt) {
            $insStmt->bind_param("isiss", $resolvedUserId, $currTheme, $currNotif, $currLang, $currInterests);
            $insStmt->execute();
            $insStmt->close();
        }
    }
}

// 5. Query Full Updated Profile Data for Response
$selectFields = "id, name, email, bio, created_at";
if ($hasAvatarCol) $selectFields .= ", avatar_url";
if ($hasPicCol) $selectFields .= ", profile_picture";

$stmt = $conn->prepare("SELECT $selectFields FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $resolvedUserId);
$stmt->execute();
$fullUser = $stmt->get_result()->fetch_assoc();
$stmt->close();

$finalAvatarUrl = $fullUser["avatar_url"] ?? ($fullUser["profile_picture"] ?? "");
if (empty($finalAvatarUrl)) {
    $finalAvatarUrl = $fullUser["profile_picture"] ?? "";
}

// Counts from scans and saved_articles
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

// User settings
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
    "message" => "Profile updated successfully",
    "data" => [
        "id" => $resolvedUserId,
        "name" => !empty($fullUser["name"]) ? $fullUser["name"] : "Alex Morgan",
        "email" => $fullUser["email"],
        "bio" => !empty($fullUser["bio"]) ? $fullUser["bio"] : "Senior Digital Media Researcher",
        "avatar_url" => $finalAvatarUrl,
        "profile_picture" => $finalAvatarUrl,
        "account_type" => "Standard Account • Active",
        "checks_count" => $checksCount,
        "saved_count" => $savedCount,
        "topics_count" => count($interests),
        "interests" => $interests,
        "language" => $language,
        "notifications_enabled" => $notificationsEnabled,
        "created_at" => $fullUser["created_at"] ?? null
    ]
], JSON_UNESCAPED_SLASHES);
?>
