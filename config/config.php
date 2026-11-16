<?php
/*
 * Application settings.
 *
 * Do not put real passwords in this file. Copy config/config.local.sample.php
 * to config/config.local.php (ignored by Git) and set the values for your
 * machine or hosting account there. Environment variables with the same
 * names also work, which is handy on hosts that support them.
 */

function cfg(string $key, $default = null) {
    static $local = null;
    if ($local === null) {
        $file  = __DIR__ . '/config.local.php';
        $local = is_file($file) ? (array)require $file : [];
    }
    if (array_key_exists($key, $local)) return $local[$key];
    $env = getenv($key);
    return $env !== false ? $env : $default;
}

// 'development' shows a notice when emails are written to the log instead of
// being sent. Use 'production' on a live server.
define('APP_ENV',  cfg('APP_ENV', 'development'));
define('APP_NAME', cfg('APP_NAME', 'Yum-Yum'));

// Full address of the site without a trailing slash, used in email links,
// e.g. https://yumyum.example.com  or  http://localhost/yum-yum
define('APP_URL',  rtrim(cfg('APP_URL', 'http://localhost/yum-yum'), '/'));

// Path of the site on the server, with leading and trailing slash.
// '/yum-yum/' for XAMPP, '/' when the site is the root of a domain.
define('BASE_PATH', cfg('BASE_PATH', '/yum-yum/'));

define('APP_TIMEZONE', cfg('APP_TIMEZONE', 'Australia/Brisbane'));

define('DB_HOST',    cfg('DB_HOST', 'localhost'));
define('DB_PORT',    (int)cfg('DB_PORT', 3306));
define('DB_NAME',    cfg('DB_NAME', 'yum_yum_db'));
define('DB_USER',    cfg('DB_USER', 'root'));
define('DB_PASS',    cfg('DB_PASS', ''));
define('DB_CHARSET', 'utf8mb4');

// Email. MAIL_DRIVER 'smtp' sends real email (e.g. Gmail with an app
// password); 'log' writes each email to storage/logs/mail.log instead.
define('MAIL_DRIVER',     cfg('MAIL_DRIVER', 'log'));
define('MAIL_HOST',       cfg('MAIL_HOST', 'smtp.gmail.com'));
define('MAIL_PORT',       (int)cfg('MAIL_PORT', 587));
define('MAIL_ENCRYPTION', cfg('MAIL_ENCRYPTION', 'tls'));   // 'tls' (port 587) or 'ssl' (port 465)
define('MAIL_USERNAME',   cfg('MAIL_USERNAME', ''));
// Gmail shows app passwords in groups of four; the spaces are not part of it.
define('MAIL_PASSWORD',   str_contains(MAIL_HOST, 'gmail.com')
    ? str_replace(' ', '', (string)cfg('MAIL_PASSWORD', ''))
    : (string)cfg('MAIL_PASSWORD', ''));
define('MAIL_FROM',       cfg('MAIL_FROM', 'no-reply@yumyum.local'));
define('MAIL_FROM_NAME',  cfg('MAIL_FROM_NAME', 'Yum-Yum'));

// Where contact form messages are sent (the team inbox).
define('CONTACT_TO', cfg('CONTACT_TO', MAIL_FROM));

define('STORAGE_PATH', __DIR__ . '/../storage');
