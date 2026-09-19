<?php
/**
 * 应用商店市场
 * 合并 本地内置应用 + 官方远程市场(可随时上架新的免费/付费插件与主题)
 */
class Market
{
    /** 官方市场地址 */
    public static function apiUrl()
    {
        return rtrim(setting('official_api', ''), '/');
    }

    /** 远程应用列表(10分钟缓存) */
    public static function remoteList($force = false)
    {
        $cache = setting('market_cache', '');
        if (!$force && $cache) {
            $arr = json_decode($cache, true);
            if (is_array($arr) && isset($arr['expire_at']) && $arr['expire_at'] > now()) {
                return is_array($arr['items']) ? $arr['items'] : [];
            }
        }
        $api = self::apiUrl();
        if (!$api) return [];
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $api . '/api/market');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $res = curl_exec($ch);
        curl_close($ch);
        if ($res === false) return [];
        $json = json_decode($res, true);
        $items = (is_array($json) && isset($json['data']['items']) && is_array($json['data']['items'])) ? $json['data']['items'] : [];
        setting_set('market_cache', json_encode(['items' => $items, 'expire_at' => now() + 600]));
        return $items;
    }

    /** 合并后的应用列表 type: payment|theme */
    public static function all($type)
    {
        $local = Plugin::scan($type);
        $installedRows = [];
        foreach (DB::fetchAll('SELECT name, enabled FROM apps') as $r) $installedRows[$r['name']] = (int)$r['enabled'];

        $list = [];
        foreach ($local as $name => $meta) {
            $list[$name] = [
                'name' => $name,
                'title' => isset($meta['title']) ? $meta['title'] : $name,
                'version' => isset($meta['version']) ? $meta['version'] : '1.0',
                'author' => isset($meta['author']) ? $meta['author'] : '未知',
                'desc' => isset($meta['desc']) ? $meta['desc'] : '',
                'price' => isset($meta['price']) ? (float)$meta['price'] : 0,
                'pro' => !empty($meta['pro']),
                'shot' => store_shot_url($type, $name, $meta),
                'local' => true,
                'installed' => isset($installedRows[$name]),
                'enabled' => isset($installedRows[$name]) && $installedRows[$name] === 1,
            ];
        }
        foreach (self::remoteList() as $item) {
            if (!isset($item['name'], $item['type']) || $item['type'] !== $type) continue;
            $name = $item['name'];
            // 远程条目标识白名单(字母数字中划线下划线), 防恶意市场下发路径注入名
            if (!is_string($name) || !preg_match('/^[a-z0-9_\-]{1,40}$/i', $name)) continue;
            if (isset($list[$name])) {
                $list[$name]['remote'] = true;
                $list[$name]['remote_version'] = isset($item['version']) ? $item['version'] : '';
                continue;
            }
            $list[$name] = [
                'name' => $name,
                'title' => isset($item['title']) ? $item['title'] : $name,
                'version' => isset($item['version']) ? $item['version'] : '1.0',
                'author' => isset($item['author']) ? $item['author'] : '未知',
                'desc' => isset($item['desc']) ? $item['desc'] : '',
                'price' => isset($item['price']) ? (float)$item['price'] : 0,
                'pro' => !empty($item['pro']),
                'shot' => isset($item['shot']) ? store_shot_url($type, $name, $item) : '',
                'local' => false,
                'installed' => isset($installedRows[$name]),
                'enabled' => isset($installedRows[$name]) && $installedRows[$name] === 1,
            ];
        }
        return array_values($list);
    }

    /**
     * 从官方市场下载安装远程应用
     * @return string 应用名
     */
    public static function download($type, $name)
    {
        $api = self::apiUrl();
        if (!$api) throw new Exception('未配置官方市场地址');
        // 标识二次白名单(防御纵深: 即使列表被污染也拒绝路径注入)
        if (!preg_match('/^[a-z0-9_\-]{1,40}$/i', (string)$name)) throw new Exception('应用标识非法');
        $domain = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
        $key = setting('license_key');
        if (!$key) throw new Exception('专业版应用需先在授权中心激活授权码');
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $api . '/api/download?type=' . urlencode($type) . '&name=' . urlencode($name)
            . '&key=' . urlencode($key) . '&domain=' . urlencode($domain));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $res = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($httpCode !== 200) {
            $json = json_decode((string)$res, true);
            throw new Exception($json && isset($json['msg']) ? $json['msg'] : '下载失败(HTTP ' . $httpCode . ')');
        }
        $dir = $type === 'theme' ? 'themes' : 'plugins';
        $tmp = YF_DATA . '/tmp_' . $name . '.zip';
        file_put_contents($tmp, $res);
        self::extractZip($tmp, YF_ROOT . '/' . $dir);
        @unlink($tmp);
        if (!Plugin::meta($type, $name)) throw new Exception('安装包内容无效');
        return $name;
    }

    public static function extractZip($zipFile, $toDir)
    {
        if (!class_exists('ZipArchive')) throw new Exception('请安装PHP zip扩展(宝塔PHP默认自带)');
        $zip = new ZipArchive();
        if ($zip->open($zipFile) !== true) throw new Exception('应用包解压失败');
        // Zip Slip防护: 拒绝路径穿越/绝对路径条目
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = $zip->getNameIndex($i);
            if ($entry === false) continue;
            if (strpos($entry, '..') !== false || strncmp($entry, '/', 1) === 0 || preg_match('#^[a-zA-Z]:#', $entry)) {
                $zip->close();
                throw new Exception('应用包包含非法路径条目, 已拒绝安装');
            }
        }
        $zip->extractTo($toDir);
        $zip->close();
    }
}
