<?php
// Configure CONTACT_RECIPIENT in the server environment before enabling this demo form.
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function respond($status, $message, $field = null) {
    http_response_code($status);
    $result = array(
        'response' => $status === 200 ? 'success' : 'error',
        'message' => $message,
    );
    if ($field !== null) {
        $result['empty'] = $field;
    }
    echo json_encode($result);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    respond(405, 'Only POST requests are supported.');
}

$recipient = getenv('CONTACT_RECIPIENT');
if (!$recipient || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
    respond(503, 'The contact form is not configured.');
}

$fields = array();
foreach (array('name', 'email', 'subject', 'phone', 'message') as $field) {
    $value = isset($_POST[$field]) && is_string($_POST[$field]) ? trim($_POST[$field]) : '';
    if (strlen($value) > 5000) {
        respond(400, 'Please shorten your input.');
    }
    $fields[$field] = $value;
}

foreach (array('name', 'email', 'message') as $required) {
    if ($fields[$required] === '') {
        respond(400, 'Please provide ' . $required . '.', $required);
    }
}

if (!filter_var($fields['email'], FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $fields['email'])) {
    respond(400, 'Please enter a valid email address.');
}

$subject = preg_replace('/[\r\n\x00-\x1f\x7f]+/', ' ', $fields['subject']);
$subject = $subject !== '' ? $subject : 'Website contact form';
$templatePath = __DIR__ . '/email-templates/simple.html';
$template = is_file($templatePath) ? file_get_contents($templatePath) : false;
if ($template === false) {
    respond(503, 'The contact form template is unavailable.');
}

$replacements = array();
foreach ($fields as $field => $value) {
    $escaped = htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $replacements['{{' . $field . '}}'] = $field === 'message' ? nl2br($escaped) : $escaped;
}
$replacements['{{subject}}'] = htmlspecialchars($subject, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$body = strtr($template, $replacements);

// Never use untrusted user input as the sender address.
$headers = "Reply-To: " . $fields['email'] . "\r\n";
$headers .= "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: text/html; charset=UTF-8\r\n";

if (!mail($recipient, $subject, $body, $headers)) {
    respond(503, 'Unable to send your message at this time.');
}
respond(200, 'Thank you! Your message has been sent.');
