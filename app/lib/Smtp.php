<?php
/**
 * 轻量SMTP发信类(用于订单发货邮件通知)
 * 支持 SSL(465) / STARTTLS(587) / 明文(25) 三种加密形态, 由 smtp_ssl 设置控制: 0明文 1SSL 2STARTTLS
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
        $headers = 'From: ' . $this->encodeWord($fromName) . ' <' . $this->user . ">\r\n"
            . 'To: <' . $to . ">\r\n"
            . 'Subject: ' . $this->encodeWord($subject) . "\r\n"
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

    /** RFC2047 encoded-word: base64按≤60字符分段折行(整段超75字符会被严格网关拒信/截断) */
    protected function encodeWord($text)
    {
        $text = (string)$text;
        if ($text === '') return '';
        $encoded = base64_encode($text);
        $parts = str_split($encoded, 60);
        $out = [];
        foreach ($parts as $p) $out[] = '=?utf-8?B?' . $p . '?=';
        return implode("\r\n ", $out);
    }

    protected function connect()
    {
        $remote = ((int)$this->ssl === 1 ? 'ssl://' : '') . $this->host . ':' . $this->port;
        $this->socket = stream_socket_client($remote, $errno, $errstr, 10);
        if (!$this->socket) throw new Exception('SMTP连接失败: ' . $errstr);
        // 读超时: 防服务端挂起时请求被无限阻塞
        stream_set_timeout($this->socket, 10);
        $this->read();
        // STARTTLS: 先明文连接, 服务端支持时升级TLS(587端口标准形态)
        if ((int)$this->ssl === 2) {
            $resp = $this->cmd('STARTTLS', 220);
            if (strpos($resp, 'TLS') === false && strpos($resp, 'ready') === false && stripos($resp, 'go ahead') === false) {
                // 部分服务器返回什么不重要, 只要220即可继续
            }
            $ok = stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            if (!$ok) throw new Exception('SMTP STARTTLS 升级失败');
            $this->hello(); // TLS建立后需重新EHLO
        }
    }

    protected function hello()
    {
        // EHLO域名取站点主机名(去www), 不再硬编码
        $h = isset($_SERVER['HTTP_HOST']) ? strtolower(preg_replace('/:\d+$/', '', (string)$_SERVER['HTTP_HOST'])) : '';
        $h = trim(preg_replace('/^www\./i', '', $h));
        if ($h === '' || !preg_match('/^[a-z0-9.\-]+$/i', $h)) $h = 'localhost';
        $this->cmd('EHLO ' . $h, 250);
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
