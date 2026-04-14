<?php
/**
 * Minauras contact form handler.
 * Receives POST from index.html and sends email via Gmail SMTP (Google Workspace).
 *
 * REQUIREMENTS:
 *   - PHPMailer installed via composer (see composer.json)
 *   - Gmail App Password for hello@minauras.com (see README below)
 *   - config.php with SMTP_PASSWORD constant (kept outside git)
 */

// ---- CORS / method guard ----
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

// ---- Load dependencies ----
require __DIR__ . '/vendor/autoload.php';

// Secrets: prefer env vars (Docker/Dokploy), fall back to config.php (shared hosting)
if (file_exists(__DIR__ . '/config.php')) {
    require __DIR__ . '/config.php';
}
$SMTP_PASSWORD    = defined('SMTP_PASSWORD')    ? SMTP_PASSWORD    : (getenv('SMTP_PASSWORD')    ?: '');
$TURNSTILE_SECRET = defined('TURNSTILE_SECRET') ? TURNSTILE_SECRET : (getenv('TURNSTILE_SECRET') ?: '');

if ($SMTP_PASSWORD === '' || $TURNSTILE_SECRET === '') {
    http_response_code(500);
    error_log('send-mail.php: missing SMTP_PASSWORD or TURNSTILE_SECRET env vars');
    echo json_encode(['ok' => false, 'error' => 'Server misconfigured. Please email hello@minauras.com directly.']);
    exit;
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ---- Read & sanitise input ----
$firstName = trim($_POST['firstName'] ?? '');
$lastName  = trim($_POST['lastName']  ?? '');
$email     = trim($_POST['email']     ?? '');
$company   = trim($_POST['company']   ?? '');
$service   = trim($_POST['service']   ?? '');
$message   = trim($_POST['message']   ?? '');

// Honeypot (bots fill hidden fields)
if (!empty($_POST['website'])) {
    echo json_encode(['ok' => true]); // silently accept & drop
    exit;
}

// Cloudflare Turnstile verification
$tsToken = $_POST['cf-turnstile-response'] ?? '';
if ($tsToken === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Verification challenge missing.']);
    exit;
}

$tsResp = file_get_contents(
    'https://challenges.cloudflare.com/turnstile/v0/siteverify',
    false,
    stream_context_create(['http' => [
        'method'  => 'POST',
        'header'  => 'Content-Type: application/x-www-form-urlencoded',
        'content' => http_build_query([
            'secret'   => $TURNSTILE_SECRET,
            'response' => $tsToken,
            'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
        ]),
        'timeout' => 5,
    ]])
);

$tsData = json_decode($tsResp, true);
if (!$tsData || empty($tsData['success'])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Verification failed. Please try again.']);
    exit;
}

// ---- Validate ----
$errors = [];
if ($firstName === '') $errors[] = 'First name required';
if ($lastName  === '') $errors[] = 'Last name required';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email required';

if ($errors) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => implode(', ', $errors)]);
    exit;
}

// ---- Build email ----
$safe = function($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); };

$bodyHtml = "
<div style='font-family:Arial,sans-serif;color:#1A1916;max-width:600px;'>
  <h2 style='color:#1D4D39;border-bottom:2px solid #C8542A;padding-bottom:8px;'>New Project Inquiry</h2>
  <table cellpadding='8' style='border-collapse:collapse;width:100%;'>
    <tr><td style='background:#F5F1E8;font-weight:bold;'>Name</td><td>{$safe($firstName)} {$safe($lastName)}</td></tr>
    <tr><td style='background:#F5F1E8;font-weight:bold;'>Email</td><td><a href='mailto:{$safe($email)}'>{$safe($email)}</a></td></tr>
    <tr><td style='background:#F5F1E8;font-weight:bold;'>Company</td><td>" . ($company ? $safe($company) : '<em>Not provided</em>') . "</td></tr>
    <tr><td style='background:#F5F1E8;font-weight:bold;'>Service</td><td>" . ($service ? $safe($service) : '<em>Not specified</em>') . "</td></tr>
  </table>
  <h3 style='color:#1D4D39;margin-top:24px;'>Message</h3>
  <div style='background:#F5F1E8;padding:16px;border-left:4px solid #1D4D39;white-space:pre-wrap;'>" . ($message ? $safe($message) : '<em>No message provided</em>') . "</div>
  <p style='color:#8A8679;font-size:12px;margin-top:24px;'>Sent from minauras.com contact form</p>
</div>";

$bodyText =
    "New Project Inquiry\n\n" .
    "Name: $firstName $lastName\n" .
    "Email: $email\n" .
    "Company: " . ($company ?: 'Not provided') . "\n" .
    "Service: " . ($service ?: 'Not specified') . "\n\n" .
    "Message:\n" . ($message ?: 'No message provided') . "\n";

// ---- Send via Gmail SMTP ----
$mail = new PHPMailer(true);

try {
    // SMTP settings (Google Workspace)
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'hello@minauras.com';
    $mail->Password   = $SMTP_PASSWORD;       // 16-char app password
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    $mail->CharSet    = 'UTF-8';

    // Headers
    $mail->setFrom('hello@minauras.com', 'Minauras Contact Form');
    $mail->addAddress('hello@minauras.com', 'Minauras Team');
    $mail->addReplyTo($email, "$firstName $lastName");

    // Content
    $mail->isHTML(true);
    $mail->Subject = "New Inquiry: $firstName $lastName" . ($company ? " ($company)" : '');
    $mail->Body    = $bodyHtml;
    $mail->AltBody = $bodyText;

    $mail->send();

    echo json_encode(['ok' => true]);
} catch (Exception $e) {
    http_response_code(500);
    error_log('Mail send failed: ' . $mail->ErrorInfo);
    echo json_encode(['ok' => false, 'error' => 'Unable to send. Please email hello@minauras.com directly.']);
}
