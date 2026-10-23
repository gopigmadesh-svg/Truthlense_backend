
<?php
$host = "localhost";
$username = "root";
$password = "";
$database = "truthlens_db"; // Replace with your MySQL database name

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    http_response_code(500);
    die(json_encode([
        "success" => false,
        "message" => "Database connection failed"
    ]));
}

$conn->set_charset("utf8mb4");
?>
