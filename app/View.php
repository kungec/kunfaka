<?php
/**
 * 坤发卡 - 视图渲染
 * 前台视图: themes/{主题}/views/*.php, 缺失时回退到默认主题 anime
 * 后台视图: app/views/admin/*.php
 */
class View
{
    /** 渲染前台页面 */
    public static function theme($tpl, $data = [])
    {
        $theme = active_theme();
        $dir = YF_ROOT . '/themes/' . $theme . '/views';
        $skin = '';
        if (!is_file($dir . '/' . $tpl . '.php')) {
            // 换肤型主题(无视图, 仅覆盖CSS): 视图回退anime, 资源仍指向所选主题
            $skin = $theme;
            $theme = 'anime';
            $dir = YF_ROOT . '/themes/anime/views';
        }
        $data['_theme'] = $theme;
        $data['_theme_url'] = site_url('themes/' . ($skin !== '' ? $skin : $theme) . '/');
        $content = self::capture($dir . '/' . $tpl . '.php', $data);
        $layout = $dir . '/layout.php';
        if (is_file($layout)) {
            self::includeData($layout, $data + ['content' => $content]);
        } else {
            echo $content;
        }
    }

    /** 渲染后台页面 */
    public static function admin($tpl, $data = [])
    {
        $dir = YF_ROOT . '/app/views/admin';
        $content = self::capture($dir . '/' . $tpl . '.php', $data);
        $layout = $dir . '/layout.php';
        if (is_file($layout) && $tpl !== 'login') {
            self::includeData($layout, $data + ['content' => $content]);
        } else {
            echo $content;
        }
    }

    private static function capture($__file, $__data)
    {
        ob_start();
        self::includeData($__file, $__data);
        return ob_get_clean();
    }

    private static function includeData($__file, $__data)
    {
        extract($__data, EXTR_SKIP);
        include $__file;
    }
}
