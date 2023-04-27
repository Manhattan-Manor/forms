<?php
function died($error)
{
    http_response_code(400);
    header('Content-Type: application/json');
    $response = array(
        'error' => true,
        'message' => 'We are very sorry, but there were error(s) found with the form you submitted. These errors appear below.\r' . $error . '\rPlease go back and fix these errors.',
    );
    echo json_encode($response);
    die();
}
