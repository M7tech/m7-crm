<?php

declare(strict_types=1);

final class SmtpClient
{
    private $socket = null;

    public function __construct(private readonly array $settings)
    {
    }

    public function connect(): void
    {
        $host = $this->settings['host'];
        $port = (int) $this->settings['port'];
        $encryption = $this->settings['encryption'];
        $remote = ($encryption === 'ssl' ? 'ssl://' : 'tcp://').$host.':'.$port;
        $context = stream_context_create(['ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
            'peer_name' => $host,
        ]]);
        $socket = @stream_socket_client($remote, $errorNumber, $errorMessage, 15, STREAM_CLIENT_CONNECT, $context);
        if ($socket === false) {
            throw new RuntimeException('SMTP connection failed: '.$errorMessage.' ('.$errorNumber.')');
        }
        $this->socket = $socket;
        stream_set_timeout($this->socket, 20);
        $this->expect([220]);
        $this->command('EHLO localhost', [250]);

        if ($encryption === 'tls') {
            $this->command('STARTTLS', [220]);
            if (!stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('SMTP TLS encryption could not be started.');
            }
            $this->command('EHLO localhost', [250]);
        }

        if ($this->settings['username'] !== '') {
            $this->command('AUTH LOGIN', [334]);
            $this->command(base64_encode($this->settings['username']), [334], false);
            $this->command(base64_encode($this->settings['password']), [235], false);
        }
    }

    public function send(string $to, string $subject, string $body, ?array $attachment = null): void
    {
        if (!is_resource($this->socket)) {
            throw new RuntimeException('SMTP is not connected.');
        }
        $from = $this->settings['from_email'];
        $this->command('MAIL FROM:<'.$from.'>', [250]);
        $this->command('RCPT TO:<'.$to.'>', [250, 251]);
        $this->command('DATA', [354]);
        $boundary = '=_LocalMail_'.bin2hex(random_bytes(12));
        $headers = [
            'Date: '.date(DATE_RFC2822),
            'From: '.self::encodedHeader($this->settings['from_name']).' <'.$from.'>',
            'To: <'.$to.'>',
            'Subject: '.self::encodedHeader($subject),
            'Message-ID: <'.bin2hex(random_bytes(12)).'@'.preg_replace('/[^A-Za-z0-9.-]/', '', substr(strrchr($from, '@') ?: '@localhost', 1)).'>',
            'MIME-Version: 1.0',
            'Content-Type: multipart/mixed; boundary="'.$boundary.'"',
        ];
        $message = implode("\r\n", $headers)."\r\n\r\n";
        $message .= '--'.$boundary."\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
        $message .= chunk_split(base64_encode($body), 76, "\r\n");
        if ($attachment !== null) {
            $name = self::safeFileName($attachment['name']);
            $content = file_get_contents($attachment['path']);
            if ($content === false) {
                throw new RuntimeException('The attachment could not be read.');
            }
            $message .= '--'.$boundary."\r\nContent-Type: ".$attachment['mime'].'; name="'.$name."\r\n";
            $message .= 'Content-Disposition: attachment; filename="'.$name."\r\nContent-Transfer-Encoding: base64\r\n\r\n";
            $message .= chunk_split(base64_encode($content), 76, "\r\n");
        }
        $message .= '--'.$boundary."--\r\n";
        $message = preg_replace('/(?m)^\./', '..', $message);
        fwrite($this->socket, $message."\r\n.\r\n");
        $this->expect([250]);
    }

    public function reset(): void
    {
        if (is_resource($this->socket)) {
            try {
                $this->command('RSET', [250]);
            } catch (Throwable) {
            }
        }
    }

    public function close(): void
    {
        if (is_resource($this->socket)) {
            @fwrite($this->socket, "QUIT\r\n");
            fclose($this->socket);
        }
        $this->socket = null;
    }

    private function command(string $command, array $codes, bool $safeToExpose = true): void
    {
        if (!is_resource($this->socket) || fwrite($this->socket, $command."\r\n") === false) {
            throw new RuntimeException('The SMTP server connection was lost.');
        }
        try {
            $this->expect($codes);
        } catch (RuntimeException $exception) {
            throw new RuntimeException(($safeToExpose ? $command.': ' : 'SMTP authentication failed: ').$exception->getMessage());
        }
    }

    private function expect(array $codes): string
    {
        if (!is_resource($this->socket)) {
            throw new RuntimeException('SMTP is not connected.');
        }
        $response = '';
        do {
            $line = fgets($this->socket, 4096);
            if ($line === false) {
                $meta = stream_get_meta_data($this->socket);
                throw new RuntimeException($meta['timed_out'] ? 'SMTP response timed out.' : 'SMTP returned no response.');
            }
            $response .= $line;
        } while (strlen($line) >= 4 && $line[3] === '-');
        $code = (int) substr($response, 0, 3);
        if (!in_array($code, $codes, true)) {
            throw new RuntimeException(trim(preg_replace('/\s+/', ' ', $response)));
        }
        return $response;
    }

    private static function encodedHeader(string $value): string
    {
        $value = str_replace(["\r", "\n"], '', $value);
        return preg_match('/[^\x20-\x7E]/', $value) ? '=?UTF-8?B?'.base64_encode($value).'?=' : $value;
    }

    private static function safeFileName(string $name): string
    {
        return str_replace(['"', "\r", "\n", '\\'], '_', basename($name));
    }
}

function smtp_settings(): array
{
    return [
        'host' => setting('smtp_host'),
        'port' => setting('smtp_port', '587'),
        'encryption' => setting('smtp_encryption', 'tls'),
        'username' => setting('smtp_username'),
        'password' => decrypt_local_secret(setting('smtp_password')),
        'from_email' => setting('smtp_from_email'),
        'from_name' => setting('smtp_from_name'),
    ];
}

function smtp_is_configured(): bool
{
    $settings = smtp_settings();
    return $settings['host'] !== '' && filter_var($settings['from_email'], FILTER_VALIDATE_EMAIL) !== false;
}
