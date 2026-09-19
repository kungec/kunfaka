<?php
/**
 * 前台图形验证码图片输出: index.php?s=/captcha/image&scope=user
 */
class CaptchaController
{
    public function actionImage()
    {
        $scope = (isset($_GET['scope']) && $_GET['scope'] === 'admin') ? 'admin' : 'user';
        Captcha::image($scope);
    }
}
