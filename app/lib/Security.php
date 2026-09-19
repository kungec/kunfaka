<?php
/**
 * 安全防护: 注册IP频次限制 / 登录爆破锁定
 * - 注册: 同一IP每24小时(滚动窗口)最多注册3次, 超出后需等待最早一次注册满24小时
 * - 登录: 同一(场景+账号+IP)一天内密码错误最多5次, 超限锁定60分钟;
 *         另含兜底: 同一(场景+IP)累计错误≥20次也锁定60分钟(防止轮换账号爆破)
 */
class Security
{
    const REG_LIMIT = 3;        // 每IP每日注册上限
    const REG_WINDOW = 86400;   // 注册限制窗口(24小时)
    const LOGIN_MAX_FAILS = 5;  // 每日最大密码错误次数
    const LOGIN_LOCK = 3600;    // 登录锁定时长(60分钟)
    const IP_MAX_FAILS = 20;    // 同IP兜底错误上限

    /**
     * 注册IP是否已被限制
     * @return ['blocked'=>bool, 'left'=>int 剩余秒数]
     */
    public static function regBlocked($ip)
    {
        $since = now() - self::REG_WINDOW;
        $cnt = (int)DB::value('SELECT COUNT(*) FROM registers WHERE ip = ? AND created_at > ?', [$ip, $since]);
        if ($cnt < self::REG_LIMIT) return ['blocked' => false, 'left' => 0];
        $first = (int)DB::value('SELECT MIN(created_at) FROM registers WHERE ip = ? AND created_at > ?', [$ip, $since]);
        return ['blocked' => true, 'left' => max(1, $first + self::REG_WINDOW - now())];
    }

    /** 注册成功后记录IP */
    public static function regRecord($ip)
    {
        DB::insert('registers', ['ip' => $ip, 'created_at' => now()]);
    }

    /**
     * 登录是否处于锁定状态
     * @return ['locked'=>bool, 'left'=>int 剩余秒数, 'fails'=>int 今日已错次数]
     */
    public static function loginLocked($scene, $account, $ip)
    {
        $todayStart = strtotime(date('Y-m-d'));
        $fails = (int)DB::value(
            'SELECT COUNT(*) FROM login_fails WHERE scene = ? AND account = ? AND ip = ? AND fail_at >= ?',
            [$scene, $account, $ip, $todayStart]
        );
        if ($fails >= self::LOGIN_MAX_FAILS) {
            $last = (int)DB::value(
                'SELECT MAX(fail_at) FROM login_fails WHERE scene = ? AND account = ? AND ip = ? AND fail_at >= ?',
                [$scene, $account, $ip, $todayStart]
            );
            $until = $last + self::LOGIN_LOCK;
            if (now() < $until) return ['locked' => true, 'left' => $until - now(), 'fails' => $fails];
        }
        // 同IP轮换账号爆破兜底
        $ipFails = (int)DB::value(
            'SELECT COUNT(*) FROM login_fails WHERE scene = ? AND ip = ? AND fail_at >= ?',
            [$scene, $ip, now() - self::LOGIN_LOCK]
        );
        if ($ipFails >= self::IP_MAX_FAILS) {
            return ['locked' => true, 'left' => self::LOGIN_LOCK, 'fails' => $ipFails];
        }
        return ['locked' => false, 'left' => 0, 'fails' => $fails];
    }

    /** 记录一次密码错误 */
    public static function loginFail($scene, $account, $ip)
    {
        DB::insert('login_fails', ['scene' => $scene, 'account' => mb_substr($account, 0, 50), 'ip' => $ip, 'fail_at' => now()]);
    }

    /** 登录成功后清除该账号的失败记录 */
    public static function loginClear($scene, $account, $ip)
    {
        DB::exec('DELETE FROM login_fails WHERE scene = ? AND account = ? AND ip = ?', [$scene, $account, $ip]);
    }
}
