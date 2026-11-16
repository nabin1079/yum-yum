<?php
/*
 * Checks the email settings by sending one test message.
 * Run from a terminal (it refuses to run in a browser):
 *
 *   C:\xampp\php\php.exe C:\xampp\htdocs\yum-yum\tools\send-test-email.php you@example.com
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../config/config.php';
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/mailer.php';

$to = $argv[1] ?? MAIL_FROM;
echo "Driver:   " . MAIL_DRIVER . "\n";
echo "Server:   " . MAIL_HOST . ':' . MAIL_PORT . ' (' . MAIL_ENCRYPTION . ")\n";
echo "Login as: " . (MAIL_USERNAME ?: '(none)') . "\n";
echo "Sending to $to ...\n";

if (MAIL_DRIVER !== 'smtp') {
    echo "MAIL_DRIVER is '" . MAIL_DRIVER . "', so the email goes to storage/logs/mail.log, not a real inbox.\n";
    echo "Set MAIL_DRIVER to 'smtp' in config/config.local.php to send real email.\n";
}
if (str_contains(MAIL_USERNAME . MAIL_PASSWORD, 'YOUR.ADDRESS') || str_contains(MAIL_PASSWORD, 'PASTE')) {
    echo "The Gmail address or App Password in config/config.local.php is still the placeholder.\n";
    exit(1);
}

$ok = send_mail($to, 'Yum-Yum test email', "This is a test from your Yum-Yum site.\n\nIf you can read this, email is working.\n\nSent " . date('j M Y, g:i A'));
if ($ok) {
    echo MAIL_DRIVER === 'smtp' ? "Sent. Check the inbox (and spam folder) of $to.\n" : "Written to storage/logs/mail.log.\n";
    exit(0);
}
echo "Failed. The reason is in storage/logs/php-error.log (last line).\n";
echo "Common causes: wrong App Password, 2-Step Verification not on, or a typo in the Gmail address.\n";
exit(1);
