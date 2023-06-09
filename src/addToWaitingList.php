<?php
require("./functions/isValidRecaptcha.php");
require("./functions/died.php");
require '../vendor/autoload.php';

// Allow cors requests
header("Access-Control-Allow-Origin: *");

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$data = json_decode(file_get_contents('php://input'), true);
$captcha = $data["g_captcha"];

$isValidRecaptcha = isValidRecaptcha($captcha);
if (!$isValidRecaptcha) {
    died("Invalid recaptcha");
}
if (!isset($data["email"]) || !isset($data["name"]) || !isset($data["lastname"])) {
    died("Missing required fields");
}

$email = htmlspecialchars($data["email"]);
$name = htmlspecialchars($data["name"]);
$lastname = htmlspecialchars($data["lastname"]);
$content = $email . "," . $name . "," . $lastname . "\r";

# Create data folder if it doesn't exist
if (!file_exists("../../form-submissions-data")) {
    mkdir("../../form-submissions-data");
}

# Save user information to CSV
$file = "../../form-submissions-data/nye-waiting-list.csv";
if (is_file($file)) {
    file_put_contents($file, $content, FILE_APPEND);
} else {
    $headers = "Email,Name,Lastname\r";
    $csv = fopen($file, "w") or die("Unable to create CSV!");
    fwrite($csv, $headers);
    fwrite($csv, $content);
    fclose($csv);
}

http_response_code(200);
header('Content-Type: application/json');
$response = array(
    'error' => false,
    'message' => 'Added to waiting list successfully',
);
echo json_encode($response);
