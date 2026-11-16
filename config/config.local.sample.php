<?php
/*
 * Copy this file to config/config.local.php and fill in your own values.
 * config.local.php is ignored by Git, so passwords never reach GitHub.
 */
return [
    'APP_ENV'   => 'development',            // 'production' on the live server
    'APP_URL'   => 'http://localhost/yum-yum',
    'BASE_PATH' => '/yum-yum/',              // '/' if the site is the root of the domain

    'DB_HOST' => 'localhost',
    'DB_NAME' => 'yum_yum_db',
    'DB_USER' => 'root',
    'DB_PASS' => '',

    // Gmail: turn on 2-Step Verification, create an App Password at
    // https://myaccount.google.com/apppasswords and paste it below.
    'MAIL_DRIVER'     => 'log',              // 'smtp' to send real email
    'MAIL_HOST'       => 'smtp.gmail.com',
    'MAIL_PORT'       => 587,
    'MAIL_ENCRYPTION' => 'tls',
    'MAIL_USERNAME'   => 'your.address@gmail.com',
    'MAIL_PASSWORD'   => 'your-16-char-app-password',
    'MAIL_FROM'       => 'your.address@gmail.com',
    'MAIL_FROM_NAME'  => 'Yum-Yum',

    'CONTACT_TO' => 'team.address@gmail.com',
];
