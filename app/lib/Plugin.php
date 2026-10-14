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
        // 保留大小写: 市场侧白名单允许大写(/i), 此处若吞掉大写字母会导致目录对不上而"下载成功安装失败"
        $name = preg_replace('/[^A-Za-z0-9_\-]/', '', (string)$name);
        if ($name === '') return null;
        $dir = $type === 'theme' ? 'themes' : 'plugins';
        $file = YF_ROOT . '/' . $dir . '/' . $name . '/' . $name . '.json';
        if (!is_file($file)) return null;
        $json = json_decode((string)file_get_contents($file), true);
        if (!is_array($json)) return null;
        $json['_dir'] = $name;
        return $json;
    }

    /** 实例化支付插件($row 可传入已查询的 apps 行, 免去重复查询) */
    public static function payment($code, ?array $row = null)
    {
        $meta = self::meta('payment', $code);
        if (!$meta || empty($meta['class'])) return null;
        // 付费应用运行时门控: 密文文件强制校验专业版授权+域名绑定, 失败视同不存在(明文源码为开发模式豁免)
        if (Protector::isProApp('payment', $code) && !Protector::gate('payment', $code)) return null;
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
        if ($row === null) {
            $row = DB::fetch('SELECT enabled, config FROM apps WHERE name = ?', [$code]);
        }
        $obj->enabled = $row && (int)$row['enabled'] === 1;
        $obj->config = $row && $row['config'] ? (array)json_decode($row['config'], true) : [];
        return $obj;
    }

    /** 实例化营销插件(coupons等): 与payment同机制, 类型独立不继承PaymentBase */
    public static function marketing($code, ?array $row = null)
    {
        $meta = self::meta('marketing', $code);
        if (!$meta || empty($meta['class'])) return null;
        if (Protector::isProApp('marketing', $code) && !Protector::gate('marketing', $code)) return null;
        $class = preg_replace('/[^A-Za-z0-9_]/', '', (string)$meta['class']);
        if ($class === '' || $class !== $meta['class']) return null;
        $file = YF_ROOT . '/plugins/' . $code . '/' . $class . '.php';
        if (!is_file($file)) return null;
        require_once $file;
        if (!class_exists($class)) return null;
        $obj = new $class();
        $obj->code = $code;
        $obj->name = isset($meta['title']) ? $meta['title'] : $code;
        if ($row === null) {
            $row = DB::fetch('SELECT enabled, config FROM apps WHERE name = ?', [$code]);
        }
        $obj->enabled = $row && (int)$row['enabled'] === 1;
        $obj->config = $row && $row['config'] ? (array)json_decode($row['config'], true) : [];
        return $obj;
    }

    /** 首个已启用的营销插件(当前营销策略为单插件生效; 不做静态缓存——常驻worker下apps启用状态变更需即时生效) */
    public static function marketingActive()
    {
        $row = DB::fetch("SELECT name, enabled FROM apps WHERE type = 'marketing' AND enabled = 1 ORDER BY name ASC LIMIT 1");
        if (!$row) return null;
        $obj = self::marketing($row['name'], $row);
        return ($obj && $obj->enabled) ? $obj : null;
    }

    /** 安装插件/主题(写入apps表); 插件类实现install()时一并调用(建表等初始化) */
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
        // 营销插件安装时执行其初始化(建表等)
        if ($type === 'marketing') {
            $obj = self::marketing($name);
            if ($obj && method_exists($obj, 'install')) $obj->install();
        }
        return true;
    }
}
