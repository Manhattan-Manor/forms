<?php
require '../vendor/autoload.php';
//Test comment

// Allow cors requests
header("Access-Control-Allow-Origin: *");

use PHPMailer\PHPMailer\PHPMailer;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$data = json_decode(file_get_contents('php://input'), true);
if (!isset($data['email'])) {
    died('The form data was not received.');
}

define("NEWSLETTER_CATEGORY", 3);

$email_to = $_ENV["EMAILS_TO"];
$email_subject = "Manhattan Manor - Web Contact";
$email_from = $_ENV["EMAILS_FROM"];
$email_psw = $_ENV["EMAILS_FROM_PASSWORD"];
$email_replyto = $data['email'];

$name = isset($data['name']) ? $data['name'] : '';
$phone = isset($data['phone']) ? $data['phone'] : '';
$subscribe = isset($data['subscribe']);
$livechat = isset($data['livechat']);
$language = isset($data['language']) ? $data['language'] : "en";
$category = isset($data['category']) ? $data['category'] : '';

if ($category == NEWSLETTER_CATEGORY) {
    $email_subject = "MCNYC - Newsletter Subscription";
}

$error_message = "";
$email_exp = '/^[A-Za-z0-9._%-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,4}$/';

if (!preg_match($email_exp, $email_replyto)) {
    $error_message .= 'The Email Address you entered does not appear to be valid.<br />';
}

$string_exp = "/^[A-Za-z .'-]+$/";

if (strlen($error_message) > 0) {
    died($error_message);
}

$email_message = '';
$email_message .= "<table>";
$email_message .= "<tr><td>Name&nbsp;</td><td>" . clean_string($name) . "</td></tr>";
$email_message .= "<tr><td>Email&nbsp;</td><td>" . clean_string($email_replyto) . "</td></tr>";
$email_message .= "<tr><td>Phone&nbsp;</td><td>" . clean_string($phone) . "</td></tr>";
$email_message .= '<tr><td>Subscribe&nbsp;</td><td>' . (($subscribe) ? 'Yes' : 'No') . "</td></tr>";
if ($livechat == "on") {
    $email_message .= "<tr><td style='font-weight: bold'>This user has requested a live chat</td></tr>";
}
$email_message .= "</table>";

// RECAPTCHA
// Validate recaptcha server-side
$response = $data["g_captcha"];
$url = 'https://www.google.com/recaptcha/api/siteverify';
$data = array(
    'secret' => $_ENV["G_RECAPTCHA_SECRET"],
    'response' => $data["g_captcha"]
);
$options = array(
    'http' => array(
        'method' => 'POST',
        'content' => http_build_query($data)
    )
);
$context = stream_context_create($options);
$verify = file_get_contents($url, false, $context);
$captcha_success = json_decode($verify);
if ($captcha_success->success == false) {
    died("Captcha error");
}
// END RECAPTCHA

try {
    // * Mail to Manhattan Manor
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->SMTPAuth = true;
    $mail->SMTPDebug = false;
    $mail->Host = 'smtp.gmail.com';
    $mail->Username = $email_from;
    $mail->Password = $email_psw;
    $mail->SMTPSecure = 'tls';
    $mail->Port = 587;

    $mail->setFrom($email_from, 'Manhattan Manor');
    $mail->addAddress($email_to);

    $mail->addAddress($_ENV["EMAILS_CC"]);
    $mail->addReplyTo($email_replyto);

    $mail->isHTML(true);
    $mail->CharSet = 'UTF-8';
    $mail->Subject = $email_subject;
    $mail->Body = $email_message;

    // * Mail to user
    $html_template_path = "";
    if ($language === "en") {
        $html_template_path = "./email_templates/autorresponder.html";
    } else {
        $html_template_path = "./email_templates/autorresponder_es.html";
    }
    $html_template = fopen($html_template_path, "r") or die("Unable to open html template for autorresponder");
    $html_content = fread($html_template, filesize($html_template_path));

    $user_mail = new PHPMailer(true);
    $user_mail->isSMTP();
    $user_mail->SMTPAuth = true;
    $user_mail->SMTPDebug = false;
    $user_mail->Host = 'smtp.gmail.com';
    $user_mail->Username = $email_from;
    $user_mail->Password = $email_psw;
    $user_mail->SMTPSecure = 'tls';
    $user_mail->Port = 587;

    $user_mail->setFrom($email_from, 'Manhattan Manor');
    $user_mail->addAddress($email_replyto);
    $user_mail->addReplyTo($email_replyto);

    $user_mail->isHTML(true);
    $user_mail->CharSet = 'UTF-8';
    $user_mail->Subject = "Manhattan Manor - Request received";
    $user_mail->Body = $html_content;

    $mail->send();
    $user_mail->send();

    # Save data to CSV file

    # Create data folder if it doesn't exist
    if (!file_exists("../../form-submissions-data")) {
        mkdir("../../form-submissions-data");
    }

    $data_file = fopen("../../form-submissions-data/contact-form.csv", "a") or die("Unable to open data file");
    $data_headers = array("date", "time", "ip", "domain", "name", "email", "phone", "subscribe", "livechat");
    if (filesize("../../form-submissions-data/contact-form.csv") == 0) {
        fputcsv($data_file, $data_headers);
    }
    $data_row = array(
        date("Y-m-d"),
        date("H:i:s"),
        $_SERVER["REMOTE_ADDR"],
        $_SERVER["HTTP_HOST"],
        $name,
        $email_replyto,
        $phone,
        $subscribe,
        $livechat,
    );
    fputcsv($data_file, $data_row);
    fclose($data_file);

    # End save data to CSV file

    http_response_code(200);
    header('Content-Type: application/json');
    $response = array(
        'error' => false,
        'message' => 'Message sent successfully',
    );
    echo json_encode($response);
} catch (\Throwable $th) {
    echo $th->getMessage();
}

function died($error)
{
    http_response_code(500);
    header('Content-Type: application/json');
    $response = array(
        'error' => true,
        'message' => 'We are very sorry, but there were error(s) found with the form you submitted. These errors appear below.\r' . $error . '\rPlease go back and fix these errors.',
    );
    echo json_encode($response);
    die();
}

function clean_string($string)
{
    $bad = array("content-type", "bcc:", "to:", "cc:", "href");
    return str_replace($bad, "", $string);
}
