<?php
/**
 * 轻量SMTP发信类(用于订单发货邮件通知)
 */
class Smtp
{
    protected $host, $port, $ssl, $user, $pass, $socket;
    protected $debug = false;

    public function __construct($host, $port, $ssl, $user, $pass)
    {
        $this->host = $host;
        $this->port = $port ?: 465;
        $this->ssl = $ssl;
        $this->user = $user;
        $this->pass = $pass;
    }

    public function send($to, $subject, $body, $fromName = '')
    {
        // 防SMTP头注入: 收件地址禁止任何换行(CRLF), 命中即拒发(调用方静默兜底, 不影响发卡)
        if (!is_string($to) || preg_match('/[\r\n]/', $to)) {
            throw new Exception('收件邮箱格式非法');
        }
        $this->connect();
        $this->hello();
        $this->auth();
        $this->cmd('MAIL FROM: <' . $this->user . '>', 250);
        $this->cmd('RCPT TO: <' . $to . '>', [250, 251]);
        $this->cmd('DATA', 354);
        $headers = 'From: =?utf-8?B?' . base64_encode($fromName) . '?= <' . $this->user . ">\r\n"
            . 'To: <' . $to . ">\r\n"
            . 'Subject: =?utf-8?B?' . base64_encode($subject) . "?=\r\n"
            . 'Date: ' . date('r') . "\r\n"
            . "MIME-Version: 1.0\r\n"
            . "Content-Type: text/plain; charset=utf-8\r\n"
            . "Content-Transfer-Encoding: base64\r\n\r\n";
        $msg = $headers . chunk_split(base64_encode($body)) . "\r\n.";
        $this->cmd($msg, 250);
        $this->cmd('QUIT', 221);
        fclose($this->socket);
        return true;
    }

    protected function connect()
    {
        $remote = ($this->ssl ? 'ssl://' : '') . $this->host . ':' . $this->port;
        $this->socket = stream_socket_client($remote, $errno, $errstr, 15);
        if (!$this->socket) throw new Exception('SMTP连接失败: ' . $errstr);
        $this->read();
    }

    protected function hello()
    {
        $this->cmd('EHLO yunfaka', 250);
    }

    protected function auth()
    {
        $this->cmd('AUTH LOGIN', 334);
        $this->cmd(base64_encode($this->user), 334);
        $this->cmd(base64_encode($this->pass), 235);
    }

    protected function cmd($cmd, $expect)
    {
        fwrite($this->socket, $cmd . "\r\n");
        $resp = $this->read();
        $code = (int)substr($resp, 0, 3);
        $ok = is_array($expect) ? in_array($code, $expect) : $code === $expect;
        if (!$ok) throw new Exception('SMTP错误[' . $code . ']: ' . $resp);
        return $resp;
    }

    protected function read()
    {
        $data = '';
        while (($line = fgets($this->socket, 512)) !== false) {
            $data .= $line;
            if (strlen($line) < 4 || $line[3] === ' ') break;
        }
        return $data;
    }
}
