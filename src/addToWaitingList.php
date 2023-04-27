<?php
$json = file_get_contents('php://input');
$data = json_decode($json);

$email = htmlspecialchars($data->email) . "\r";

# Create data folder if it doesn't exist
if (!file_exists("../../form-submissions-data")) {
    mkdir("../../form-submissions-data");
}

# Save user information to CSV
$file = "../../form-submissions-data/nye-waiting-list.csv";
if (is_file($file)) {
    file_put_contents($file, $email, FILE_APPEND);
} else {
    $csv = fopen($file, "w") or die("Unable to create CSV!");
    fwrite($csv, "Waiting list emails\r");
    fwrite($csv, $email);
    fclose($csv);
}