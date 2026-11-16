<?php
/*
 * Sends email. With MAIL_DRIVER = 'smtp' it talks to an SMTP server directly
 * (Gmail, Outlook or the hosting provider's mail server) using STARTTLS or
 * SSL and AUTH LOGIN. With MAIL_DRIVER = 'log' it writes the message to
 * storage/logs/mail.log, which is useful during development.
 *
 * send_mail() never throws: it returns false and logs the reason, so a mail
 * problem never breaks the page the user is on.
 */

function send_mail(string $to, string $subject, string $text, ?string $reply_to = null): bool {
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        error_log("send_mail: invalid recipient '$to'");
        return false;
    }
    // Stop header injection through subject or reply-to.
    $subject  = trim(preg_replace('/[\r\n]+/', ' ', $subject));
    $reply_to = $reply_to && filter_var($reply_to, FILTER_VALIDATE_EMAIL) ? $reply_to : null;

    $date       = date('r');
    $message_id = '<' . bin2hex(random_bytes(12)) . '@' . (parse_url(APP_URL, PHP_URL_HOST) ?: 'localhost') . '>';
    $headers = [
        'Date: ' . $date,
        'From: ' . mail_encode_header(MAIL_FROM_NAME) . ' <' . MAIL_FROM . '>',
        'To: <' . $to . '>',
        'Subject: ' . mail_encode_header($subject),
        'Message-ID: ' . $message_id,
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: base64',
    ];
    if ($reply_to) {
        $headers[] = 'Reply-To: <' . $reply_to . '>';
    }
    $body = chunk_split(base64_encode($text), 76, "\r\n");

    if (MAIL_DRIVER !== 'smtp') {
        $entry = "==== $date\nTo: $to\nSubject: $subject\n" . ($reply_to ? "Reply-To: $reply_to\n" : '') . "\n$text\n\n";
        return file_put_contents(STORAGE_PATH . '/logs/mail.log', $entry, FILE_APPEND | LOCK_EX) !== false;
    }

    try {
        smtp_send($to, implode("\r\n", $headers) . "\r\n\r\n" . $body);
        return true;
    } catch (Throwable $e) {
        error_log('send_mail failed: ' . $e->getMessage());
        return false;
    }
}

function mail_encode_header(string $value): string {
    return preg_match('/[^\x20-\x7E]/', $value) ? '=?UTF-8?B?' . base64_encode($value) . '?=' : $value;
}

function smtp_send(string $to, string $data): void {
    $host   = MAIL_ENCRYPTION === 'ssl' ? 'ssl://' . MAIL_HOST : MAIL_HOST;
    $socket = @stream_socket_client($host . ':' . MAIL_PORT, $errno, $errstr, 15);
    if (!$socket) {
        throw new RuntimeException("cannot connect to " . MAIL_HOST . ":" . MAIL_PORT . " ($errstr)");
    }
    stream_set_timeout($socket, 15);

    $read = function () use ($socket): string {
        $reply = '';
        while (($line = fgets($socket, 515)) !== false) {
            $reply .= $line;
            if (strlen($line) < 4 || $line[3] === ' ') break;   // last line of a multi-line reply
        }
        return $reply;
    };
    $cmd = function (string $command, array $expect) use ($socket, $read): string {
        if ($command !== '') fwrite($socket, $command . "\r\n");
        $reply = $read();
        if (!in_array((int)substr($reply, 0, 3), $expect, true)) {
            $shown = str_starts_with($command, 'AUTH') || strlen($command) > 60 ? '[hidden]' : $command;
            throw new RuntimeException("SMTP '$shown' failed: " . trim($reply));
        }
        return $reply;
    };

    $me = parse_url(APP_URL, PHP_URL_HOST) ?: 'localhost';
    try {
        $cmd('', [220]);
        $cmd("EHLO $me", [250]);
        if (MAIL_ENCRYPTION === 'tls') {
            $cmd('STARTTLS', [220]);
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) {
                throw new RuntimeException('could not start TLS');
            }
            $cmd("EHLO $me", [250]);
        }
        if (MAIL_USERNAME !== '') {
            $cmd('AUTH LOGIN', [334]);
            $cmd(base64_encode(MAIL_USERNAME), [334]);
            $cmd(base64_encode(MAIL_PASSWORD), [235]);
        }
        $cmd('MAIL FROM:<' . MAIL_FROM . '>', [250]);
        $cmd('RCPT TO:<' . $to . '>', [250, 251]);
        $cmd('DATA', [354]);
        // Dot-stuffing: a line starting with "." must be doubled.
        $data = preg_replace('/^\./m', '..', $data);
        $cmd($data . "\r\n.", [250]);
        $cmd('QUIT', [221]);
    } finally {
        fclose($socket);
    }
}
