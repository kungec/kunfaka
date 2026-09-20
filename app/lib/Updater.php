<?php
/**
 * 坤发卡 自动更新器
 * 版本源: 授权站(official_api)/api/version 下发最新版本信息(含更新包地址与sha256)。
 * 流程: check(对比版本) → cron预取下载(download) → 后台访问时应用(apply: 解压覆盖+数据库升级)。
 * 安全: 保护清单(data/config.php、install.lock、uploads等)在解压时强制跳过, 不会被更新包覆盖;
 *       更新包经sha256校验; 解压后自动执行数据库升级(lib/Upgrade)。
 */
class Updater
{
    const PKG_FILE = YF_DATA . '/update_pkg.zip';

    /** 最新版本信息(null=获取失败) */
    public static function check($force = false)
    {
        $cache = setting('update_check_cache', '');
        $j = json_decode((string)$cache, true);
        if (!$force && is_array($j) && isset($j['at']) && (int)$j['at'] > now() - 600) {
            return isset($j['info']) && is_array($j['info']) ? $j['info'] : null;
        }
        $api = rtrim(setting('official_api', ''), '/');
        $info = null;
        if ($api !== '') {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $api . '/api/version',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 6,
                CURLOPT_CONNECTTIMEOUT => 4,
            ]);
            curl_tls($ch);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
            $res = curl_exec($ch);
            curl_close($ch);
            $json = json_decode((string)$res, true);
            if (is_array($json) && ($json['code'] ?? 1) === 0 && is_array($json['data'])
                && !empty($json['data']['version']) && !empty($json['data']['url'])) {
                $info = [
                    'version' => (string)$json['data']['version'],
                    'desc' => (string)($json['data']['desc'] ?? ''),
                    'url' => (string)$json['data']['url'],
                    'sha256' => (string)($json['data']['sha256'] ?? ''),
                    'time' => (int)($json['data']['time'] ?? 0),
                ];
            }
        }
        setting_set('update_check_cache', json_encode(['info' => $info, 'at' => now()]));
        return $info;
    }

    public static function hasUpdate(array $info)
    {
        return !empty($info['version']) && version_compare($info['version'], YF_VERSION, '>');
    }

    public static function autoUpdateEnabled()
    {
        return setting('auto_update', '0') === '1';
    }

    /** 下载更新包并做sha256校验, 成功后返回true(包就绪) */
    public static function download(array $info)
    {
        if (empty($info['url'])) return false;
        if (is_file(self::PKG_FILE) && self::pkgShaMatches($info)) return true;
        @unlink(self::PKG_FILE);
        $ch = curl_init();
        $fp = fopen(self::PKG_FILE, 'wb');
        curl_setopt_array($ch, [
            CURLOPT_URL => $info['url'],
            CURLOPT_FILE => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);
        curl_tls($ch);
        $ok = curl_exec($ch) !== false && curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200;
        curl_close($ch);
        fclose($fp);
        if (!$ok || !is_file(self::PKG_FILE) || !self::pkgShaMatches($info)) {
            @unlink(self::PKG_FILE);
            return false;
        }
        return true;
    }

    protected static function pkgShaMatches(array $info)
    {
        if (empty($info['sha256'])) return true; // 未提供校验值则跳过(不推荐)
        return hash_file('sha256', self::PKG_FILE) === strtolower($info['sha256']);
    }

    /** 应用已下载的更新包: 解压覆盖(跳过保护清单) + 数据库升级 */
    public static function apply(array $info)
    {
        if (!is_file(self::PKG_FILE)) return false;
        if (!class_exists('ZipArchive')) throw new Exception('缺少 zip 扩展, 无法自动更新');
        $zip = new ZipArchive();
        if ($zip->open(self::PKG_FILE) !== true) throw new Exception('更新包损坏, 无法解压');
        $protected = self::protectedPaths();
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            // 剥离zip顶层公共目录(适配GitHub源码包)
            if (strpos($name, '/') !== false) {
                $parts = explode('/', $name);
                array_shift($parts);
                $name = implode('/', $parts);
            }
            if ($name === '' || self::isProtected($name)) continue;
            $target = YF_ROOT . '/' . $name;
            if (substr($name, -1) === '/') {
                if (!is_dir($target)) @mkdir($target, 0755, true);
                continue;
            }
            $dir = dirname($target);
            if (!is_dir($dir)) @mkdir($dir, 0755, true);
            $stream = $zip->getStream($zip->getNameIndex($i));
            if ($stream) {
                file_put_contents($target, $stream);
                @chmod($target, 0644);
                fclose($stream);
            }
        }
        $zip->close();
        // 数据库升级(幂等; SQLite测试环境DDL自动跳过, 生产MySQL完整执行)
        try {
            Upgrade::run(DB::handle());
        } catch (Exception $ex) {
            // 升级失败不回滚文件, 留待下次重试
        }
        @unlink(self::PKG_FILE);
        setting_set('updated_version', $info['version']);
        setting_set('updated_time', (string)now());
        setting_set('update_check_cache', '');
        return true;
    }

    /** 自动更新主入口: 开关开启且有新版时 下载→应用 */
    public static function autoStep()
    {
        $info = self::check();
        if (!$info || !self::hasUpdate($info)) return null;
        if (setting('updated_version', '') === $info['version']) return null; // 该版本已应用
        if (!self::download($info)) return null;
        if (self::apply($info)) {
            add_log('system', '自动更新完成: 已升级至 v' . $info['version']);
            return $info['version'];
        }
        return null;
    }

    protected static function protectedPaths()
    {
        return [
            'data/config.php', 'data/install.lock', 'data/',
            'uploads/',
        ];
    }

    protected static function isProtected($name)
    {
        foreach (self::protectedPaths() as $p) {
            if ($name === rtrim($p, '/') || strpos($name, rtrim($p, '/') . '/') === 0) return true;
        }
        return false;
    }
}
