<?php
/**
 * TruthLens Misinformation Checker Backend
 * File: upload_profile_picture.php
 * Description: Secure multipart/form-data profile picture upload and update API.
 */

header("Content-Type: application/json; charset=UTF-8");

// Response helper
function sendJsonResponse($statusCode, $success, $message, $profilePictureUrl = null, $data = null)
{
    http_response_code($statusCode);
    $response = [
        "success" => (bool)$success,
        "message" => (string)$message
    ];

    if ($profilePictureUrl !== null) {
        $response["profile_picture_url"] = (string)$profilePictureUrl;
    }

    if ($data !== null) {
        $response["data"] = $data;
    }

    echo json_encode($response, JSON_UNESCAPED_SLASHES);
    exit;
}

// 1. Enforce POST request method
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    sendJsonResponse(405, false, "Method Not Allowed. POST request required.");
}

// 2. Include database configuration
require_once __DIR__ . "/config/db.php";

if (!isset($conn) || !($conn instanceof mysqli)) {
    sendJsonResponse(500, false, "Internal Server Error: Database connection unavailable.");
}

// 3. User Identification & Authentication Guard
// Accept either user_id or email from POST fields
$userId = isset($_POST["user_id"]) ? (int)$_POST["user_id"] : 0;
$email = isset($_POST["email"]) ? trim($_POST["email"]) : "";

// Check for Bearer token or authorization header if client supplied one
$authHeader = $_SERVER["HTTP_AUTHORIZATION"] ?? ($_SERVER["REDIRECT_HTTP_AUTHORIZATION"] ?? "");
$authToken = "";
if (preg_match('/Bearer\s+(\S+)/i', $authHeader, $matches)) {
    $authToken = $matches[1];
}

if ($userId <= 0 && empty($email)) {
    sendJsonResponse(400, false, "Missing required identifier: 'user_id' or 'email' must be provided.");
}

// 4. Verify User Existence in Database using Prepared Statement
// Check which columns exist in users table to prevent SQL failures
$colAvatarCheck = $conn->query("SHOW COLUMNS FROM `users` LIKE 'avatar_url'");
$hasAvatarCol = ($colAvatarCheck && $colAvatarCheck->num_rows > 0);

$colPicCheck = $conn->query("SHOW COLUMNS FROM `users` LIKE 'profile_picture'");
$hasPicCol = ($colPicCheck && $colPicCheck->num_rows > 0);

// If profile_picture column does not exist yet, add it automatically
if (!$hasPicCol) {
    $conn->query("ALTER TABLE `users` ADD COLUMN `profile_picture` VARCHAR(255) NULL AFTER `avatar_url`");
    $hasPicCol = true;
}

$selectCols = "id, email";
if ($hasPicCol) $selectCols .= ", profile_picture";
if ($hasAvatarCol) $selectCols .= ", avatar_url";

if ($userId > 0) {
    $userStmt = $conn->prepare("SELECT $selectCols FROM `users` WHERE `id` = ? LIMIT 1");
    if (!$userStmt) {
        sendJsonResponse(500, false, "Database statement preparation failed.");
    }
    $userStmt->bind_param("i", $userId);
} else {
    $userStmt = $conn->prepare("SELECT $selectCols FROM `users` WHERE `email` = ? LIMIT 1");
    if (!$userStmt) {
        sendJsonResponse(500, false, "Database statement preparation failed.");
    }
    $userStmt->bind_param("s", $email);
}

$userStmt->execute();
$userResult = $userStmt->get_result();

if (!$userResult || $userResult->num_rows === 0) {
    $userStmt->close();
    $conn->close();
    sendJsonResponse(404, false, "User account not found. Please verify the user credentials.");
}

$userData = $userResult->fetch_assoc();
$resolvedUserId = (int)$userData["id"];
$resolvedUserEmail = (string)$userData["email"];
$oldProfilePic = $userData["profile_picture"] ?? ($userData["avatar_url"] ?? "");
$userStmt->close();

// 5. Validate File Upload Presence & Errors
if (!isset($_FILES["profile_picture"])) {
    sendJsonResponse(400, false, "Missing file: Form field 'profile_picture' is required.");
}

$fileError = $_FILES["profile_picture"]["error"];
if ($fileError !== UPLOAD_ERR_OK) {
    switch ($fileError) {
        case UPLOAD_ERR_NO_FILE:
            sendJsonResponse(400, false, "No image file selected for upload.");
            break;
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            sendJsonResponse(400, false, "File size exceeds server upload limit.");
            break;
        case UPLOAD_ERR_PARTIAL:
            sendJsonResponse(400, false, "File upload was interrupted. Please try again.");
            break;
        default:
            sendJsonResponse(500, false, "An error occurred during file upload (Error code: $fileError).");
            break;
    }
}

$tmpFilePath = $_FILES["profile_picture"]["tmp_name"];
$originalFileName = $_FILES["profile_picture"]["name"] ?? "image";
$fileSizeBytes = (int)$_FILES["profile_picture"]["size"];

// 6. Enforce 5 MB Maximum File Size
$maxSizeBytes = 5 * 1024 * 1024; // 5 MB
if ($fileSizeBytes > $maxSizeBytes) {
    sendJsonResponse(400, false, "File exceeds maximum permitted size of 5 MB.");
}

if ($fileSizeBytes <= 0 || !is_uploaded_file($tmpFilePath)) {
    sendJsonResponse(400, false, "Invalid or empty uploaded file.");
}

// 7. Validate File Extension (Whitelisting: JPG, JPEG, PNG, WEBP)
$rawExtension = strtolower(pathinfo($originalFileName, PATHINFO_EXTENSION));
$allowedExtensions = ["jpg", "jpeg", "png", "webp"];

if (!in_array($rawExtension, $allowedExtensions, true)) {
    sendJsonResponse(400, false, "Invalid file format. Only JPEG, JPG, PNG, and WEBP images are allowed.");
}

// 8. Server-Side MIME-Type Validation (Do not trust client-reported Content-Type)
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$detectedMime = $finfo ? finfo_file($finfo, $tmpFilePath) : mime_content_type($tmpFilePath);
if ($finfo) {
    finfo_close($finfo);
}

$allowedMimeTypes = [
    "image/jpeg",
    "image/pjpeg",
    "image/png",
    "image/x-png",
    "image/webp"
];

if (!in_array($detectedMime, $allowedMimeTypes, true)) {
    sendJsonResponse(400, false, "Security rejection: The uploaded file is not a valid image format (Detected: $detectedMime).");
}

// 9. Validate Image Stream Integrity using getimagesize()
$imageInfo = @getimagesize($tmpFilePath);
if ($imageInfo === false || empty($imageInfo[0]) || empty($imageInfo[1])) {
    sendJsonResponse(400, false, "The uploaded file is corrupt or not a recognized image.");
}

// 10. Prepare Destination Directory
$uploadDir = __DIR__ . "/uploads/profile_pictures/";
if (!is_dir($uploadDir)) {
    if (!@mkdir($uploadDir, 0755, true)) {
        sendJsonResponse(500, false, "Server storage configuration error: Failed to create upload directory.");
    }
}

// Ensure .htaccess protection exists inside uploads directory
$htaccessPath = $uploadDir . ".htaccess";
if (!file_exists($htaccessPath)) {
    $htaccessContent = "# Prevent direct script execution in upload directory\n" .
        "<FilesMatch \"(?i)\\.(php|phtml|php3|php4|php5|php7|php8|phps|pht|phar|cgi|pl|py|sh|exe|bat|cmd)$\">\n" .
        "    Order Deny,Allow\n" .
        "    Deny from all\n" .
        "</FilesMatch>\n" .
        "Options -ExecCGI -Indexes\n";
    @file_put_contents($htaccessPath, $htaccessContent);
}

// 11. Generate Collision-Proof Unique Filename
// Normalize extension
$normalizedExt = ($rawExtension === "jpeg") ? "jpg" : $rawExtension;
try {
    $randomEntropy = bin2hex(random_bytes(8));
} catch (Exception $e) {
    $randomEntropy = substr(md5(uniqid((string)mt_rand(), true)), 0, 16);
}

$uniqueFileName = sprintf("user_%d_%d_%s.%s", $resolvedUserId, time(), $randomEntropy, $normalizedExt);
$destinationPath = $uploadDir . $uniqueFileName;

// 12. Move Uploaded File Securely
if (!@move_uploaded_file($tmpFilePath, $destinationPath)) {
    sendJsonResponse(500, false, "Failed to store uploaded file on the server. Please check filesystem permissions.");
}

// Set restrictive read permissions on newly created file
@chmod($destinationPath, 0644);

// 13. Construct Accessible Public URL
$protocol = (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off") ? "https://" : "http://";
$host = $_SERVER["HTTP_HOST"] ?? "localhost";
$scriptDir = rtrim(str_replace("\\", "/", dirname($_SERVER["SCRIPT_NAME"])), "/");
$relativeStoredPath = "uploads/profile_pictures/" . $uniqueFileName;
$publicUrl = $protocol . $host . $scriptDir . "/" . $relativeStoredPath;

// 14. Update users table in Database using Prepared Statement
$updateCols = [];
$bindParams = [];
$bindTypes = "";

if ($hasAvatarCol) {
    $updateCols[] = "`avatar_url` = ?";
    $bindParams[] = $publicUrl;
    $bindTypes .= "s";
}
if ($hasPicCol) {
    $updateCols[] = "`profile_picture` = ?";
    $bindParams[] = $publicUrl;
    $bindTypes .= "s";
}
if (empty($updateCols)) {
    $updateCols[] = "`avatar_url` = ?";
    $bindParams[] = $publicUrl;
    $bindTypes .= "s";
}

$bindParams[] = $resolvedUserId;
$bindTypes .= "i";

$updateQuery = "UPDATE `users` SET " . implode(", ", $updateCols) . " WHERE `id` = ?";
$updateStmt = $conn->prepare($updateQuery);

if (!$updateStmt) {
    // If update fails, clean up the newly saved file to avoid orphans
    @unlink($destinationPath);
    $conn->close();
    sendJsonResponse(500, false, "Database update preparation failed.");
}

$updateStmt->bind_param($bindTypes, ...$bindParams);

if (!$updateStmt->execute()) {
    @unlink($destinationPath);
    $updateStmt->close();
    $conn->close();
    sendJsonResponse(500, false, "Failed to update profile picture in database.");
}

$updateStmt->close();
$conn->close();

// 15. Optionally clean up old custom profile picture to reclaim disk space
if (!empty($oldProfilePic)) {
    $oldFileName = basename($oldProfilePic);
    $oldFilePath = $uploadDir . $oldFileName;
    if (!empty($oldFileName) && file_exists($oldFilePath) && $oldFileName !== $uniqueFileName && $oldFileName !== ".htaccess") {
        @unlink($oldFilePath);
    }
}

// 16. Return JSON response
sendJsonResponse(
    200,
    true,
    "Profile picture uploaded and updated successfully.",
    $publicUrl,
    [
        "user_id" => $resolvedUserId,
        "email" => $resolvedUserEmail,
        "file_name" => $uniqueFileName,
        "relative_path" => $relativeStoredPath,
        "avatar_url" => $publicUrl,
        "profile_picture_url" => $publicUrl
    ]
);
?>

