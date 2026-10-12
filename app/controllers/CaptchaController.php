<?php
/**
 * 前台图形验证码图片输出: index.php?s=/captcha/image&scope=user
 */
class CaptchaController
{
    public function actionImage()
    {
        // scope仅允许小写字母/下划线(仅作会话键后缀), 缺省user; 购买页scope=order必须原样保留
        $scope = strtolower(trim((string)($_GET['scope'] ?? 'user')));
        $scope = preg_replace('/[^a-z_]/', '', $scope);
        if ($scope === '') $scope = 'user';
        Captcha::image($scope);
    }
}
