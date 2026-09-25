<?php
/**
 * 人机验证: 极验GeeTest v4(可开关) / Cloudflare Turnstile(可开关) / GD图形验证码(默认兜底)
 * 应用范围: 前台会员注册/登录, 后台管理员登录
 * scope: 'admin' 后台 | 'user' 前台
 *
 * 极验接入方式(参考官方文档 docs.geetest.com/gt4):
 *   前端: gt4.js + initGeetest4({captchaId, product:'bind'}) → showCaptcha() → onSuccess getValidate()
 *   后端: POST gcaptcha4.geetest.com/validate?captcha_id=xx 二次校验
 *         sign_token = hmac_sha256(极验Key, lot_number)
 *   超时: 验证结果自 gen_time 起 X 秒内有效(后台可配, 默认120秒), 超时拒绝
 */
class Captcha
{
    /** 当前验证模式: geetest | turnstile | captcha | off */
    public static function mode()
    {
        $mode = setting('verify_mode');
        if ($mode === '') $mode = setting('turnstile_open') === '1' ? 'turnstile' : 'captcha'; // 兼容v1.1
        if ($mode === 'turnstile' && setting('turnstile_site_key') && setting('turnstile_secret_key')) return 'turnstile';
        if ($mode === 'geetest' && setting('geetest_id') && setting('geetest_key')) return 'geetest';
        // 所选模式缺少密钥时降级为图形验证码, 避免锁死登录
        if (setting('captcha_open', '1') === '1') return 'captcha';
        return 'off';
    }

    /** 渲染验证控件HTML */
    public static function render($scope)
    {
        $mode = self::mode();
        if ($mode === 'turnstile') {
            $siteKey = e(setting('turnstile_site_key'));
            return '<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>'
                . '<div class="cf-turnstile" data-sitekey="' . $siteKey . '" data-theme="auto" data-size="flexible" style="width:100%"></div>';
        }
        if ($mode === 'geetest') {
            return self::renderGeetest($scope);
        }
        if ($mode === 'off') return '';
        $src = e(u('captcha/image', ['scope' => $scope, 't' => time()]));
        return '<div class="captcha-row">'
            . '<img class="captcha-img" title="点击刷新" alt="验证码" src="' . $src . '" onclick="this.src=this.src.split(\'&t=\')[0]+\'&t=\'+Date.now()">'
            . '<input class="captcha-input" type="text" name="captcha" maxlength="4" placeholder="验证码" autocomplete="off" required>'
            . '</div>';
    }

    /** 极验v4 内联按钮模式: 页面直接渲染「点击按钮开始验证」, 一键通过后自动提交表单 */
    protected static function renderGeetest($scope)
    {
        static $gtjsLoaded = false;
        $id = e(setting('geetest_id'));
        $timeout = max(10, (int)setting('geetest_timeout', '120'));
        $js = '';
        if (!$gtjsLoaded) {
            $js .= '<script src="https://static.geetest.com/v4/gt4.js"></script>';
            $gtjsLoaded = true;
        }
        $js .= '<style>'
            . '.geetest-box{width:100%}.geetest-box>div,.geetest-box .geetest_holder,.geetest-box .geetest_btn,'
            . '.geetest-box [class*="geetest_holder"],.geetest-box [class*="geetest_btn"]{width:100%!important;box-sizing:border-box}'
            . '.geetest-box iframe{width:100%!important;max-width:100%}'
            . '</style>'
            . '<div class="geetest-box" data-scope="' . e($scope) . '"></div>
<script>
(function () {
    document.querySelectorAll(\'.geetest-box[data-scope="' . e($scope) . '"]\').forEach(function (box) {
        if (box.getAttribute("data-init")) return;
        box.setAttribute("data-init", "1");
        var form = box.closest("form");
        if (!form) return;
        initGeetest4({
            captchaId: "' . $id . '",
            product: "popup",
            language: "zho",
            timeout: ' . ($timeout * 1000) . '
        }, function (captcha) {
            captcha.onSuccess(function () {
                var r = captcha.getValidate();
                if (!r) return;
                form.querySelectorAll(".geetest-field").forEach(function (el) { el.remove(); });
                function mkField(n, v) {
                    var i = document.createElement("input");
                    i.type = "hidden"; i.name = n; i.value = v; i.className = "geetest-field";
                    form.appendChild(i);
                }
                mkField("captcha_lot_number", r.lot_number);
                mkField("captcha_output", r.captcha_output);
                mkField("captcha_pass_token", r.pass_token);
                mkField("captcha_gen_time", r.gen_time);
                // 前置校验必填项: 缺失时不自动提交(验证字段保留, 用户补填后可再次提交)
                var bad = null;
                form.querySelectorAll("[required]").forEach(function (el) {
                    if (!bad && (el.value === undefined || String(el.value).trim() === "")) bad = el;
                });
                if (bad) {
                    try { if (bad.reportValidity) bad.reportValidity(); else bad.focus(); } catch (e) {}
                    return;
                }
                form.submit();
            }).onError(function () {
                alert("验证组件加载失败, 请刷新页面重试");
            });
            captcha.appendTo(box);
        });
    });
})();
</script>';
        return $js;
    }

    /** 校验(校验后一次性失效) */
    public static function verify($scope, $req)
    {
        $mode = self::mode();
        if ($mode === 'turnstile') {
            $token = trim(arr_get($req, 'cf-turnstile-response'));
            if ($token === '') return false;
            return self::turnstileCheck($token);
        }
        if ($mode === 'geetest') {
            return self::geetestCheck($req);
        }
        if ($mode === 'off') return true;
        $code = isset($_SESSION['captcha_' . $scope]) ? $_SESSION['captcha_' . $scope] : '';
        unset($_SESSION['captcha_' . $scope]);
        $input = strtoupper(trim(arr_get($req, 'captcha')));
        return $code !== '' && hash_equals(strtoupper($code), $input);
    }

    /** Cloudflare siteverify 服务端二次校验 */
    protected static function turnstileCheck($token)
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'secret' => setting('turnstile_secret_key'),
                'response' => $token,
                'remoteip' => client_ip(),
            ]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
        ]);
        curl_tls($ch);
        $res = curl_exec($ch);
        curl_close($ch);
        if ($res === false) return false;
        $json = json_decode($res, true);
        return is_array($json) && !empty($json['success']);
    }

    /** 极验v4 服务端二次校验(含验证结果有效期控制); 失败原因记录日志便于排查 */
    protected static function geetestCheck($req)
    {
        $lotNumber = trim(arr_get($req, 'captcha_lot_number'));
        $captchaOutput = trim(arr_get($req, 'captcha_output'));
        $passToken = trim(arr_get($req, 'captcha_pass_token'));
        $genTime = (int)arr_get($req, 'captcha_gen_time');
        if ($lotNumber === '' || $captchaOutput === '' || $passToken === '' || $genTime <= 0) {
            self::logFail('params missing: lot=' . substr($lotNumber, 0, 8) . ' out=' . strlen($captchaOutput) . ' pass=' . strlen($passToken) . ' gen=' . $genTime);
            return false;
        }

        // 验证结果有效期: 超过后台配置的秒数视为超时拒绝
        $timeout = max(10, (int)setting('geetest_timeout', '120'));
        if (now() - $genTime > $timeout) {
            self::logFail('expired: gen=' . $genTime . ' now=' . now() . ' diff=' . (now() - $genTime) . 's > ' . $timeout);
            return false;
        }

        $signToken = hash_hmac('sha256', $lotNumber, setting('geetest_key'));
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://gcaptcha4.geetest.com/validate?captcha_id=' . urlencode(setting('geetest_id')),
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'lot_number' => $lotNumber,
                'captcha_output' => $captchaOutput,
                'pass_token' => $passToken,
                'gen_time' => (string)$genTime,
                'sign_token' => $signToken,
            ]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
        ]);
        curl_tls($ch);
        $res = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);
        if ($res === false || $httpCode !== 200) {
            self::logFail('validate http=' . $httpCode . ' curl=' . $curlErr . ' res=' . substr((string)$res, 0, 200));
            return false;
        }
        $json = json_decode($res, true);
        if (!is_array($json)) {
            self::logFail('validate non-json: ' . substr((string)$res, 0, 200));
            return false;
        }
        // 极验参数级异常(status=error)与校验不通过(result=fail)均视为未通过
        if (!isset($json['result']) || $json['result'] !== 'success') {
            self::logFail('geetest result=' . $json['result'] . ' reason=' . (string)($json['reason'] ?? '') . ' code=' . (string)($json['code'] ?? ''));
        }
        return isset($json['result']) && $json['result'] === 'success';
    }

    /** 极验校验失败日志(仅失败时记录, 便于定位密钥/域名白名单问题) */
    protected static function logFail($msg)
    {
        @file_put_contents(YF_DATA . '/geetest_fail.log', date('Y-m-d H:i:s') . ' ' . $msg . "\n", FILE_APPEND);
    }

    /** 输出图形验证码PNG(GD), 并写入session */
    public static function image($scope)
    {
        $code = '';
        $pool = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        for ($i = 0; $i < 4; $i++) $code .= $pool[random_int(0, strlen($pool) - 1)];
        $_SESSION['captcha_' . $scope] = $code;

        $w = 132; $h = 44;
        $img = imagecreatetruecolor($w, $h);
        $bg = imagecolorallocate($img, 248, 244, 252);
        imagefilledrectangle($img, 0, 0, $w, $h, $bg);
        // 干扰线
        for ($i = 0; $i < 4; $i++) {
            $c = imagecolorallocate($img, rand(150, 210), rand(150, 210), rand(180, 230));
            imageline($img, rand(0, $w), rand(0, $h), rand(0, $w), rand(0, $h), $c);
        }
        // 噪点
        for ($i = 0; $i < 50; $i++) {
            $c = imagecolorallocate($img, rand(120, 220), rand(120, 220), rand(140, 240));
            imagesetpixel($img, rand(0, $w - 1), rand(0, $h - 1), $c);
        }
        // 字符(带轻微上下抖动)
        for ($i = 0; $i < 4; $i++) {
            $c = imagecolorallocate($img, rand(40, 110), rand(40, 110), rand(120, 190));
            imagestring($img, 5, 12 + $i * 28, rand(8, 20), $code[$i], $c);
        }
        header('Content-Type: image/png');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
        imagepng($img);
        imagedestroy($img);
        exit;
    }
}
