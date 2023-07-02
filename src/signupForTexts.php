<?php
require("./functions/isValidRecaptcha.php");
require("./functions/died.php");
require "../vendor/autoload.php";

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
if (!isset($data["phone"])) {
    died("Missing required fields");
}

$phone = htmlspecialchars($data["phone"]);

# Create data folder if it doesn't exist
if (!file_exists("../../form-submissions-data")) {
    mkdir("../../form-submissions-data");
}

# Save user information to CSV
$file = "../../form-submissions-data/signup-for-texts.csv";
if (is_file($file)) {
    file_put_contents($file, $phone . "\r", FILE_APPEND);
} else {
    $headers = "Phone\r";
    $csv = fopen($file, "w") or die("Unable to create CSV!");
    fwrite($csv, $headers);
    fwrite($csv, $phone . "\r");
    fclose($csv);
}

http_response_code(200);
header('Content-Type: application/json');
$response = array(
    'error' => false,
    'message' => 'Added to waiting list successfully',
);
echo json_encode($response);