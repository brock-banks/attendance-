<?php
/**
 * Minimal dependency-free SMTP client (AUTH LOGIN, SSL or STARTTLS).
 * Enough to send HTML alert emails via Gmail/Outlook/custom SMTP.
 */
class Mailer
{
    /**
     * SMTP config, read from the DB settings (editable in the UI) and
     * falling back to config.php constants where a setting is blank.
     */
    public static function config(): array
    {
        $get = function (string $key, string $const, string $def = '') {
            $v = (string)Shift::setting('smtp_' . $key, '');
            if ($v !== '') return $v;
            return defined($const) ? (string)constant($const) : $def;
        };
        return [
            'host'      => trim($get('host', 'SMTP_HOST')),
            'port'      => (int)$get('port', 'SMTP_PORT', '587'),
            'secure'    => $get('secure', 'SMTP_SECURE', 'tls'),
            'user'      => trim($get('user', 'SMTP_USER')),
            'pass'      => $get('pass', 'SMTP_PASS'),
            'from'      => trim($get('from', 'SMTP_FROM')),
            'from_name' => $get('from_name', 'SMTP_FROM_NAME', 'Attendance'),
        ];
    }

    public static function configured(): bool
    {
        return self::config()['host'] !== '';
    }

    /**
     * @param string      $to      recipient email
     * @param string|null $error   filled with the failure reason on false
     */
    public static function send(string $to, string $subject, string $html, ?string &$error = null): bool
    {
        $cfg = self::config();
        if ($cfg['host'] === '') { $error = 'SMTP host is not set (Settings → Email alerts).'; return false; }

        $host   = $cfg['host'];
        $port   = (int)$cfg['port'];
        $secure = $cfg['secure'];
        $transport = $secure === 'ssl' ? "ssl://$host:$port" : "tcp://$host:$port";

        $ctx = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
        $fp  = @stream_socket_client($transport, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
        if (!$fp) { $error = "connect failed: $errstr ($errno)"; return false; }
        stream_set_timeout($fp, 15);

        $read = function () use ($fp) {
            $data = '';
            while (($line = fgets($fp, 515)) !== false) {
                $data .= $line;
                if (strlen($line) < 4 || $line[3] === ' ') break;  // last line of reply
            }
            return $data;
        };
        $cmd = function ($c) use ($fp, $read) { fwrite($fp, $c . "\r\n"); return $read(); };
        $ok  = function ($resp, $code) use (&$error) {
            if (strpos((string)$resp, $code) !== 0) { $error = trim((string)$resp); return false; }
            return true;
        };

        try {
            if (!$ok($read(), '220')) return self::bail($fp);
            if (!$ok($cmd('EHLO attendance.local'), '250')) return self::bail($fp);

            if ($secure === 'tls') {
                if (!$ok($cmd('STARTTLS'), '220')) return self::bail($fp);
                $crypto = STREAM_CRYPTO_METHOD_TLS_CLIENT;
                if (!@stream_socket_enable_crypto($fp, true, $crypto)) { $error = 'STARTTLS negotiation failed'; return self::bail($fp); }
                if (!$ok($cmd('EHLO attendance.local'), '250')) return self::bail($fp);
            }

            if ($cfg['user'] !== '') {
                if (!$ok($cmd('AUTH LOGIN'), '334')) return self::bail($fp);
                if (!$ok($cmd(base64_encode($cfg['user'])), '334')) return self::bail($fp);
                if (!$ok($cmd(base64_encode($cfg['pass'])), '235')) return self::bail($fp);
            }

            $from = $cfg['from'] !== '' ? $cfg['from'] : $cfg['user'];
            if (!$ok($cmd("MAIL FROM:<$from>"), '250')) return self::bail($fp);
            if (!$ok($cmd("RCPT TO:<$to>"), '250')) return self::bail($fp);
            if (!$ok($cmd('DATA'), '354')) return self::bail($fp);

            $headers  = 'From: ' . self::enc($cfg['from_name']) . " <$from>\r\n";
            $headers .= "To: <$to>\r\n";
            $headers .= 'Subject: ' . self::enc($subject) . "\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            $headers .= 'Date: ' . date('r') . "\r\n";

            // Normalise to CRLF and dot-stuff.
            $body = preg_replace('/\r\n|\r|\n/', "\r\n", $html);
            $body = preg_replace('/^\./m', '..', $body);

            if (!$ok($cmd($headers . "\r\n" . $body . "\r\n."), '250')) return self::bail($fp);
            $cmd('QUIT');
            fclose($fp);
            return true;
        } catch (Throwable $ex) {
            $error = $ex->getMessage();
            return self::bail($fp);
        }
    }

    private static function bail($fp): bool { @fclose($fp); return false; }
    private static function enc(string $s): string { return '=?UTF-8?B?' . base64_encode($s) . '?='; }
}
