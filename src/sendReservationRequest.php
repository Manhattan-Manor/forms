<?php
require '../vendor/autoload.php';

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

$firstname = isset($data['firstname']) ? $data['firstname'] : '';
$lastname = isset($data['lastname']) ? $data['lastname'] : '';
$company = isset($data['company']) ? $data['company'] : '';
$phone = isset($data['phone']) ? $data['phone'] : '';
$eventdate = isset($data['eventdate']) ? $data['eventdate'] : '';
$eventtime = isset($data['eventtime']) ? $data['eventtime'] : '';
$guests = isset($data['guests']) ? $data['guests'] : '';
$eventtype = isset($data['eventtype']) ? $data['eventtype'] : '';
$notes = isset($data['notes']) ? $data['notes'] : '';
$subscribe = isset($data['subscribe']);
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
$email_message .= "<tr><td>First Name&nbsp;</td><td>" . clean_string($firstname) . "</td></tr>";
$email_message .= "<tr><td>Last Name&nbsp;</td><td>" . clean_string($lastname) . "</td></tr>";
$email_message .= "<tr><td>Company&nbsp;</td><td>" . clean_string($company) . "</td></tr>";
$email_message .= "<tr><td>Email&nbsp;</td><td>" . clean_string($email_replyto) . "</td></tr>";
$email_message .= "<tr><td>Phone&nbsp;</td><td>" . clean_string($phone) . "</td></tr>";
$email_message .= "<tr><td>Event Date&nbsp;</td><td>" . clean_string($eventdate) . "</td></tr>";
$email_message .= "<tr><td>Event Time&nbsp;</td><td>" . clean_string($eventtime) . "</td></tr>";
$email_message .= "<tr><td>Guests&nbsp;</td><td>" . clean_string($guests) . "</td></tr>";
$email_message .= "<tr><td>Event Type&nbsp;</td><td>" . clean_string($eventtype) . "</td></tr>";
$email_message .= "<tr><td>Notes&nbsp;</td><td>" . clean_string($notes) . "</td></tr>";
$email_message .= '<tr><td>Subscribe&nbsp;</td><td>' . (($subscribe) ? 'Yes' : 'No') . "</td></tr>";
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
    $data = array(
        'date' => date("Y-m-d"),
        'time' => date("H:i:s"),
        'ip' => $_SERVER['REMOTE_ADDR'],
        'domain' => $_SERVER['HTTP_HOST'],
        'firstname' => $firstname,
        'lastname' => $lastname,
        'company' => $company,
        'email' => $email_replyto,
        'phone' => $phone,
        'eventdate' => $eventdate,
        'eventtime' => $eventtime,
        'guests' => $guests,
        'eventtype' => $eventtype,
        'notes' => $notes,
        'subscribe' => $subscribe,
    );
    $data = array_map(function ($value) {
        return '"' . str_replace('"', '""', $value) . '"';
    }, $data);
    $data = implode(",", $data) . "\n";
    $data_header = array(
        'date',
        'time',
        'ip',
        'domain',
        'firstname',
        'lastname',
        'company',
        'email',
        'phone',
        'eventdate',
        'eventtime',
        'guests',
        'eventtype',
        'notes',
        'subscribe',
    );

    # Create data folder if it doesn't exist
    if (!file_exists("../../form-submissions-data")) {
        mkdir("../../form-submissions-data");
    }

    $file = fopen("../../form-submissions-data/reservation-requests.csv", "a") or die("Unable to open file!");
    if (filesize("../../form-submissions-data/reservation-requests.csv") == 0) {
        $data_header = array_map(function ($value) {
            return '"' . str_replace('"', '""', $value) . '"';
        }, $data_header);
        $data_header = implode(",", $data_header) . "\n";
        fwrite($file, $data_header);
    }
    fwrite($file, $data);
    fclose($file);
    
    # END Save data to CSV file

    http_response_code(200);
    header('Content-Type: application/json');
    $response = array(
        'error' => false,
        'message' => 'Message sent successfully',
    );
    echo json_encode($response);
} catch (\Throwable $th) {
    echo 'Message could not be sent. Mailer Error: ', $mail->ErrorInfo;
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
