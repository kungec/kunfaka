<?php
/**
 * 插件/主题 加载器
 * 目录规范: plugins/{code}/{class}.php 与 {code}.json 元信息
 */
class Plugin
{
    /** 扫描本地插件元信息 type: payment|theme */
    public static function scan($type)
    {
        $dir = $type === 'theme' ? 'themes' : 'plugins';
        $out = [];
        foreach (glob(YF_ROOT . '/' . $dir . '/*/' . '*.json') as $file) {
            $json = json_decode((string)file_get_contents($file), true);
            if (!is_array($json) || empty($json['name'])) continue;
            if (($json['type'] ?? '') !== $type) continue;
            $json['_dir'] = basename(dirname($file));
            $out[$json['name']] = $json;
        }
        return $out;
    }

    /** 单个插件/主题元信息 */
    public static function meta($type, $name)
    {
        $name = preg_replace('/[^a-z0-9_\-]/', '', (string)$name);
        if ($name === '') return null;
        $dir = $type === 'theme' ? 'themes' : 'plugins';
        $file = YF_ROOT . '/' . $dir . '/' . $name . '/' . $name . '.json';
        if (!is_file($file)) return null;
        $json = json_decode((string)file_get_contents($file), true);
        if (!is_array($json)) return null;
        $json['_dir'] = $name;
        return $json;
    }

    /** 实例化支付插件 */
    public static function payment($code)
    {
        $meta = self::meta('payment', $code);
        if (!$meta || empty($meta['class'])) return null;
        // class名严格白名单, 防止拼接出目录穿越include
        $class = preg_replace('/[^A-Za-z0-9_]/', '', (string)$meta['class']);
        if ($class === '' || $class !== $meta['class']) return null;
        $file = YF_ROOT . '/plugins/' . $code . '/' . $class . '.php';
        if (!is_file($file)) return null;
        require_once $file;
        if (!class_exists($class)) return null;
        /** @var PaymentBase $obj */
        $obj = new $class();
        $obj->code = $code;
        $obj->name = isset($meta['title']) ? $meta['title'] : $code;
        $row = DB::fetch('SELECT enabled, config FROM apps WHERE name = ?', [$code]);
        $obj->enabled = $row && (int)$row['enabled'] === 1;
        $obj->config = $row && $row['config'] ? (array)json_decode($row['config'], true) : [];
        return $obj;
    }

    /** 安装插件/主题(写入apps表) */
    public static function install($type, $name)
    {
        $meta = self::meta($type, $name);
        if (!$meta) return false;
        $exists = DB::value('SELECT name FROM apps WHERE name = ?', [$name]);
        if ($exists) return true;
        DB::insert('apps', [
            'name' => $name,
            'type' => $type,
            'enabled' => 0,
            'config' => '{}',
            'installed_at' => now(),
        ]);
        return true;
    }
}
