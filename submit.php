<?php
// ── Sockies Configurator — form handler with file attachment ──

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$to      = 'info@sockies.at';
$subject = isset($_POST['subject']) ? $_POST['subject'] : 'Socken-Konfigurator Anfrage';
$replyto = isset($_POST['replyto']) ? $_POST['replyto'] : 'noreply@sockies.at';
$fromName = isset($_POST['from_name']) ? $_POST['from_name'] : 'Sockies Configurator';

// Sanitize headers
$subject = preg_replace('/[\r\n]+/', ' ', $subject);
$replyto = preg_replace('/[\r\n]+/', ' ', $replyto);
$fromName = preg_replace('/[\r\n]+/', ' ', $fromName);

// Build the body — list all submitted fields except internals
$skipKeys = ['subject', 'replyto', 'from_name', 'access_key'];
$bodyLines = [];
foreach ($_POST as $key => $value) {
    if (in_array($key, $skipKeys, true)) continue;
    $bodyLines[] = $key . ': ' . $value;
}
$bodyText = implode("\r\n", $bodyLines);
$bodyText .= "\r\n\r\n---\r\nGesendet vom Sockies Konfigurator";

// Boundary for multipart message
$boundary = '==SockiesBoundary_' . md5(uniqid((string)mt_rand(), true));

$headers  = 'From: ' . $fromName . ' <noreply@sockies.at>' . "\r\n";
$headers .= 'Reply-To: ' . $replyto . "\r\n";
$headers .= 'MIME-Version: 1.0' . "\r\n";
$headers .= 'Content-Type: multipart/mixed; boundary="' . $boundary . '"' . "\r\n";

// Body part
$message  = '--' . $boundary . "\r\n";
$message .= 'Content-Type: text/plain; charset=utf-8' . "\r\n";
$message .= 'Content-Transfer-Encoding: 8bit' . "\r\n\r\n";
$message .= $bodyText . "\r\n\r\n";

// Attachment part (if file uploaded)
if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['attachment'];
    $fileName = basename($file['name']);
    $fileType = $file['type'] ?: 'application/octet-stream';
    $fileData = file_get_contents($file['tmp_name']);
    $encoded  = chunk_split(base64_encode($fileData));

    $message .= '--' . $boundary . "\r\n";
    $message .= 'Content-Type: ' . $fileType . '; name="' . $fileName . '"' . "\r\n";
    $message .= 'Content-Transfer-Encoding: base64' . "\r\n";
    $message .= 'Content-Disposition: attachment; filename="' . $fileName . '"' . "\r\n\r\n";
    $message .= $encoded . "\r\n\r\n";
}

$message .= '--' . $boundary . '--';

$ok = @mail($to, $subject, $message, $headers, '-f noreply@sockies.at');

if ($ok) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'mail() failed']);
}
