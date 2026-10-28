<?php
header("Content-Type: application/json; charset=UTF-8");

$host = "localhost";
$username = "root";
$password = "";
$database = "truthlense";

// Connect without database first to ensure database exists
$conn = @new mysqli($host, $username, $password);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Database server connection failed: " . $conn->connect_error
    ]);
    exit;
}

// 1. Create database
$conn->query("CREATE DATABASE IF NOT EXISTS `truthlense` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$conn->select_db($database);

// 2. Ensure users table exists with name column
$conn->query("
    CREATE TABLE IF NOT EXISTS `users` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(100) NULL DEFAULT '',
        `email` VARCHAR(191) NOT NULL UNIQUE,
        `password` VARCHAR(255) NOT NULL,
        `bio` TEXT NULL,
        `avatar_url` VARCHAR(255) NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

// Check if 'name' column exists in case users was created previously without it
$result = $conn->query("SHOW COLUMNS FROM `users` LIKE 'name'");
if ($result && $result->num_rows === 0) {
    $conn->query("ALTER TABLE `users` ADD COLUMN `name` VARCHAR(100) NULL DEFAULT '' AFTER `id`");
}

$resultBio = $conn->query("SHOW COLUMNS FROM `users` LIKE 'bio'");
if ($resultBio && $resultBio->num_rows === 0) {
    $conn->query("ALTER TABLE `users` ADD COLUMN `bio` TEXT NULL AFTER `password`");
}

$resultAvatar = $conn->query("SHOW COLUMNS FROM `users` LIKE 'avatar_url'");
if ($resultAvatar && $resultAvatar->num_rows === 0) {
    $conn->query("ALTER TABLE `users` ADD COLUMN `avatar_url` VARCHAR(255) NULL AFTER `bio`");
}

$resultPic = $conn->query("SHOW COLUMNS FROM `users` LIKE 'profile_picture'");
if ($resultPic && $resultPic->num_rows === 0) {
    $conn->query("ALTER TABLE `users` ADD COLUMN `profile_picture` VARCHAR(255) NULL AFTER `avatar_url`");
}

// 3. Ensure scans table exists
$conn->query("
    CREATE TABLE IF NOT EXISTS `scans` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NULL,
        `title` VARCHAR(255) NOT NULL,
        `content` TEXT NOT NULL,
        `scan_type` VARCHAR(50) DEFAULT 'news',
        `trust_score` INT NOT NULL DEFAULT 50,
        `verdict` VARCHAR(50) NOT NULL DEFAULT 'QUESTIONABLE',
        `reasoning` TEXT NULL,
        `source_credibility` VARCHAR(50) NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_scans_user (`user_id`),
        INDEX idx_scans_created (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

// 4. Ensure saved_articles table exists
$conn->query("
    CREATE TABLE IF NOT EXISTS `saved_articles` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `title` VARCHAR(255) NOT NULL,
        `summary` TEXT NULL,
        `source` VARCHAR(100) NULL,
        `url` VARCHAR(500) NULL,
        `trust_score` INT NOT NULL DEFAULT 70,
        `saved_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_saved_user (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

// 5. Ensure user_settings table exists
$conn->query("
    CREATE TABLE IF NOT EXISTS `user_settings` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL UNIQUE,
        `theme` VARCHAR(20) DEFAULT 'dark',
        `notifications_enabled` TINYINT(1) DEFAULT 1,
        `language` VARCHAR(50) DEFAULT 'English (US)',
        `interests` TEXT NULL,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_settings_user (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

echo json_encode([
    "success" => true,
    "message" => "TruthLens database and all tables verified successfully.",
    "tables" => ["users", "scans", "saved_articles", "user_settings"]
]);

$conn->close();
?>

