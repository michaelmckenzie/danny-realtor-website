<?php
/**
 * contact.php — lead capture handler for danielhernandezhaygood.com
 *
 * Fully self-hosted. No third-party services, no API keys, no secrets.
 * Each submission is emailed to Daniel (with the lead's address as Reply-To so
 * he can answer the customer directly), and the lead gets an auto-acknowledgment
 * so the connection is opened in both directions.
 */

// ---- Configuration ----------------------------------------------------------
$LEAD_RECIPIENT = 'haygooddaniel@gmail.com';            // where leads are delivered
$FROM_ADDRESS   = 'noreply@danielhernandezhaygood.com'; // must be a domain mailbox for deliverability
$FROM_NAME      = 'Haygood Real Estate Website';
$SITE_NAME      = 'Daniel Hernandez Haygood · Real Estate';
$AGENT_PHONE    = '(210) 740-8692';
// -----------------------------------------------------------------------------

header('X-Content-Type-Options: nosniff');

$isAjax = (
  (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
  || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
);

function respond($ok, $message, $isAjax) {
  if ($isAjax) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array('ok' => $ok, 'message' => $message));
  } else {
    header('Location: /index.html?' . ($ok ? 'sent=1' : 'error=1') . '#contact');
  }
  exit;
}

// Strip CR/LF so user input can never inject extra mail headers.
function noheaders($v) {
  return str_replace(array("\r", "\n", "%0a", "%0d", "%0A", "%0D"), '', (string)$v);
}
// Trim + strip tags for safe plain-text email bodies.
function clean($v) {
  return trim(strip_tags((string)$v));
}

// Only accept POST
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  respond(false, 'Method not allowed.', $isAjax);
}

// Honeypot — bots fill hidden fields, humans don't. Silently accept and drop.
if (!empty($_POST['company'])) {
  respond(true, 'Thanks — your message has been received.', $isAjax);
}

$first = clean($_POST['first_name'] ?? '');
$last  = clean($_POST['last_name']  ?? '');
$email = clean($_POST['email']      ?? '');
$phone = clean($_POST['phone']      ?? '');
$goal  = clean($_POST['goal']       ?? '');
$msg   = clean($_POST['message']    ?? '');

// Validate
if ($first === '' && $last === '') {
  respond(false, 'Please add your name so Daniel knows who to reach.', $isAjax);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  respond(false, 'Please enter a valid email address so Daniel can reply.', $isAjax);
}

$name      = trim($first . ' ' . $last);
$safeName  = noheaders($name);
$safeEmail = noheaders($email);

// ---- 1) Lead notification to Daniel -----------------------------------------
$subject = noheaders('New website lead: ' . ($name !== '' ? $name : $safeEmail));

$body  = "New lead from the website contact form\n";
$body .= "----------------------------------------\n\n";
$body .= "Name:       " . ($name  !== '' ? $name  : '(not given)') . "\n";
$body .= "Email:      " . $safeEmail . "\n";
$body .= "Phone:      " . ($phone !== '' ? $phone : '(not given)') . "\n";
$body .= "Looking to: " . ($goal  !== '' ? $goal  : '(not given)') . "\n\n";
$body .= "Message:\n" . ($msg !== '' ? $msg : '(none)') . "\n\n";
$body .= "----------------------------------------\n";
$body .= "Reply directly to this email to respond to " . ($first !== '' ? $first : 'the lead') . ".\n";
$body .= "Received " . date('M j, Y g:i a') . "\n";

$replyTo  = $safeName !== '' ? ($safeName . ' <' . $safeEmail . '>') : $safeEmail;
$headers  = 'From: ' . $FROM_NAME . ' <' . $FROM_ADDRESS . '>' . "\r\n";
$headers .= 'Reply-To: ' . $replyTo . "\r\n";
$headers .= 'Content-Type: text/plain; charset=utf-8' . "\r\n";

$sent = @mail($LEAD_RECIPIENT, $subject, $body, $headers);

if (!$sent) {
  respond(false, 'Something went wrong sending your message. Please email Daniel directly at ' . $LEAD_RECIPIENT . '.', $isAjax);
}

// ---- 2) Auto-acknowledgment to the lead (maintain the connection) -----------
$ackSubject = 'Thanks for reaching out to Daniel Hernandez Haygood';
$ackBody  = "Hi " . ($first !== '' ? $first : 'there') . ",\n\n";
$ackBody .= "Thanks for reaching out — your message came through and I'll get back to you personally, usually within one business day.\n\n";
$ackBody .= "If it's time-sensitive (a PCS timeline, an offer deadline, or a deal you're weighing), call or text me at " . $AGENT_PHONE . " and we'll talk it through.\n\n";
$ackBody .= "Talk soon,\n";
$ackBody .= "Daniel Hernandez Haygood\n";
$ackBody .= "Licensed Texas Real Estate Agent · MCL Realty\n";
$ackBody .= $AGENT_PHONE . "\n";

$ackHeaders  = 'From: ' . $FROM_NAME . ' <' . $FROM_ADDRESS . '>' . "\r\n";
$ackHeaders .= 'Reply-To: ' . $LEAD_RECIPIENT . "\r\n";
$ackHeaders .= 'Content-Type: text/plain; charset=utf-8' . "\r\n";

@mail($safeEmail, $ackSubject, $ackBody, $ackHeaders);

respond(true, "Thanks — your message is on its way to Daniel. He'll be in touch personally, usually within a business day.", $isAjax);
