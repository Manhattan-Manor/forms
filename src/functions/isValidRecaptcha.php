<?php
function isValidRecaptcha(string $client_response)
{
    $url = 'https://www.google.com/recaptcha/api/siteverify';
    $data = array(
        'secret' => $_ENV["G_RECAPTCHA_SECRET"],
        'response' => $client_response
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
        return false;
    }

    return true;
}
